<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Http\Requests\Pagos\StorePagoRequest;
use App\Mail\RequisicionPagadaMail;
use App\Mail\RequisicionPagoAutorizadoMail;
use App\Models\Pago;
use App\Models\Requisicion;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class RequisicionPagoController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function create(Request $request, Requisicion $requisicion)
    {
        $this->authorize('viewPayments', $requisicion);
        $user = $request->user();

        $requisicion->load(['proveedor', 'concepto', 'solicitante']);

        $pagos = $requisicion->pagos()->latest('id')->get();

        $pagado = (float) $pagos->sum('monto');
        $total = (float) $requisicion->monto_total;
        $pendiente = max(0, $total - $pagado);
        $cantidadPagos = $pagos->count();
        $puedeDefinirFechaGeneral = $cantidadPagos > 0
            && ($pagado + 0.00001 >= $total)
            && $pendiente <= 0.00001;
        $benef = $this->buildBeneficiario($requisicion);
        $canDownload = $user->can('downloadPayment', $requisicion);
        $pagosShape = $pagos->map(function ($p) use ($benef, $canDownload) {
            // Archivo por la ruta protegida (respeta "Descargar comprobantes de pago").
            $url = $canDownload && ! empty($p->archivo_path) ? route('pagos.archivo', $p->id) : null;

            return [
                'id' => (int) $p->id,
                'fecha_pago' => optional($p->fecha_pago)->format('Y-m-d'),
                'tipo_pago' => (string) ($p->tipo_pago ?? ''),
                'monto' => (float) ($p->monto ?? 0),
                'referencia' => $p->referencia ?? null,
                'archivo' => $url ? [
                    'label' => $p->archivo_original ?: 'Ver archivo',
                    'url' => $url,
                    'kind' => \App\Support\FileKind::of($p->archivo_original ?: $p->archivo_path),
                ] : null,
                // NO sale de pagos (porque no existe en pagos). Sale de la requisición/proveedor.
                'beneficiario' => [
                    'nombre' => $benef['nombre'] ?? null,
                    'rfc' => $benef['rfc'] ?? null,
                    'clabe' => $benef['clabe'] ?? null,
                    'banco' => $benef['banco'] ?? null,
                ],
            ];
        })->values();

        return Inertia::render('Requisiciones/Pagar', [
            'requisicion' => [
                'data' => [
                    'id' => $requisicion->id,
                    'folio' => $requisicion->folio,
                    'concepto' => $requisicion->concepto?->nombre ?? null,
                    'monto_total' => (float) $requisicion->monto_total,
                    'solicitante_nombre' => $this->safeNombre($requisicion->solicitante),
                    'beneficiario' => $benef,
                    'status' => (string) ($requisicion->status ?? ''),
                    'fecha_solicitud' => optional($requisicion->fecha_solicitud)->format('Y-m-d'),
                    'fecha_pago_esperada' => optional($requisicion->fecha_pago_esperada)->format('Y-m-d'),
                    'fecha_autorizacion' => optional($requisicion->fecha_autorizacion)->format('Y-m-d'),
                    'fecha_pago_programada' => optional($requisicion->fecha_pago)->format('Y-m-d'),
                    'cantidad_pagos' => $cantidadPagos,
                    'puede_definir_fecha_pago_general' => $puedeDefinirFechaGeneral,
                ],
            ],
            'pagos' => [
                'data' => $pagosShape,
            ],
            'totales' => [
                'pagado' => $pagado,
                'pendiente' => $pendiente,
            ],
            'tipoPagoOptions' => [
                ['id' => 'TRANSFERENCIA', 'nombre' => 'Transferencia'],
                ['id' => 'EFECTIVO', 'nombre' => 'Efectivo'],
                ['id' => 'TARJETA', 'nombre' => 'Tarjeta'],
                ['id' => 'CHEQUE', 'nombre' => 'Cheque'],
                ['id' => 'OTRO', 'nombre' => 'Otro'],
            ],
            'can' => [
                'autorizar' => $user->can('authorizePayment', $requisicion),
                'rechazar' => $user->can('rejectPayment', $requisicion),
                'registrar' => $user->can('registerPayment', $requisicion),
                'editar' => $user->can('editPayment', $requisicion),
                'descargar' => $canDownload,
            ],
        ]);
    }

    public function authorizePago(Request $request, Requisicion $requisicion)
    {
        $this->authorize('authorizePayment', $requisicion);
        $data = $request->validate([
            'fecha_pago' => ['required', 'date_format:Y-m-d'],
        ], [
            'fecha_pago.required' => 'Indica la fecha programada de pago.',
            'fecha_pago.date_format' => 'La fecha de pago debe tener formato AAAA-MM-DD.',
        ]);

        // fecha_autorizacion registra el momento REAL de la autorización (auditoría).
        $autorizada = DB::transaction(fn () => Requisicion::query()
            ->whereKey($requisicion->id)
            ->where('status', 'CAPTURADA')
            ->update([
                'fecha_autorizacion' => now(),
                'pago_autorizado_por_id' => $request->user()->id,
                'fecha_pago' => $data['fecha_pago'], // fecha programada
                'status' => 'PAGO_AUTORIZADO',
            ]) === 1);

        if (! $autorizada) {
            return back()->with('error', 'La requisición no se puede autorizar en su estado actual.');
        }

        // La actualización atómica no dispara eventos del modelo: se registra explícitamente.
        $requisicion->auditLog('CAMBIO_ESTATUS', "Pago autorizado: {$requisicion->folio}.", [
            'status' => ['CAPTURADA', 'PAGO_AUTORIZADO'],
            'fecha_pago' => [optional($requisicion->fecha_pago)->format('Y-m-d'), $data['fecha_pago']],
        ]);

        $requisicion->refresh()->load(['solicitante.user', 'creadaPor']);
        $this->notifications->notify(
            topic: NotificationTopic::Pagos,
            event: 'pago.autorizado',
            title: "Pago autorizado: {$requisicion->folio}",
            message: 'Pago programado para el '.Carbon::parse($data['fecha_pago'])->format('d/m/Y').'.',
            severity: 'success',
            url: route('requisiciones.show', $requisicion->id, false),
            direct: [$requisicion->solicitante?->user, $requisicion->creadaPor],
            actor: $request->user(),
            canSee: fn ($u) => $u->can('viewPayments', $requisicion),
        );

        $colaborador = $requisicion->solicitante?->user;
        if ($colaborador?->email) {
            $this->notifications->mail($colaborador->email, new RequisicionPagoAutorizadoMail($requisicion, $data['fecha_pago']));
        }

        return back()->with('success', 'Pago autorizado correctamente.');
    }

    public function store(StorePagoRequest $request, Requisicion $requisicion)
    {
        $this->authorize('registerPayment', $requisicion);
        $requisicion->load(['proveedor', 'solicitante.user', 'creadaPor']);

        $resultado = DB::transaction(function () use ($request, $requisicion) {
            $pagadoActual = (float) $requisicion->pagos()->sum('monto');
            $montoTotal = (float) $requisicion->monto_total;
            $pendiente = max(0, $montoTotal - $pagadoActual);
            $monto = round((float) $request->input('monto'), 2);
            if ($pendiente > 0 && $monto > ($pendiente + 0.00001)) {
                return back()->withErrors([
                    'monto' => "El monto ($monto) excede lo pendiente ($pendiente).",
                ]);
            }
            if ($pendiente <= 0 && abs($monto) > 0.00001) {
                return back()->withErrors([
                    'monto' => 'Pendiente en 0. Solo se permite monto 0.00.',
                ]);
            }
            $file = $request->file('archivo');
            $folder = "requisiciones/{$requisicion->id}/pagos";
            $stored = null;
            try {
                $stored = $file->storePublicly($folder, 'public');
                $benef = $this->buildBeneficiario($requisicion);
                (new Pago)->forceFill([
                    'requisicion_id' => $requisicion->id,
                    'beneficiario_nombre' => $benef['nombre'] ?? '—',
                    'tipo_pago' => $request->input('tipo_pago'),
                    'monto' => $monto,
                    'fecha_pago' => $request->input('fecha_pago'), // fecha real del pago
                    'archivo_path' => $stored,
                    'archivo_original' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'referencia' => $request->input('referencia'),
                    'user_carga_id' => (int) auth()->id(),
                ])->save();
                $pendienteDespues = max(0, $pendiente - $monto);
                $nuevoStatus = ($pendienteDespues <= 0.00001) ? 'PAGADA' : 'PAGO_AUTORIZADO';
                $requisicion->update([
                    'status' => $nuevoStatus,
                ]);

                return $nuevoStatus;
            } catch (\Throwable $e) {
                if ($stored) {
                    Storage::disk('public')->delete($stored);
                }
                throw $e;
            }
        });

        if (! is_string($resultado)) {
            return $resultado; // error de validación de montos
        }

        // Notificaciones fuera de la transacción: un fallo SMTP no revierte el pago.
        if ($resultado === 'PAGADA') {
            $this->notifications->notify(
                topic: NotificationTopic::Pagos,
                event: 'pago.completado',
                title: "Pago completado: {$requisicion->folio}",
                message: 'Se registró el pago total de $'.number_format((float) $requisicion->monto_total, 2).'. Ya puedes cargar tus comprobantes.',
                severity: 'success',
                url: route('requisiciones.comprobar', $requisicion->id, false),
                direct: [$requisicion->solicitante?->user, $requisicion->creadaPor],
                actor: $request->user(),
                canSee: fn ($u) => $u->can('viewPayments', $requisicion),
            );

            $colaborador = $requisicion->solicitante?->user;
            if ($colaborador?->email) {
                $this->notifications->mail($colaborador->email, new RequisicionPagadaMail($requisicion));
            }
        }

        return back()->with('success', 'Pago registrado correctamente.');
    }

    /**
     * Rechaza el pago de una requisición CAPTURADA (queda en PAGO_RECHAZADO).
     * El motivo es obligatorio, queda en la bitácora y se avisa al solicitante.
     */
    public function rejectPago(Request $request, Requisicion $requisicion)
    {
        $this->authorize('rejectPayment', $requisicion);
        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'motivo.required' => 'Escribe el motivo del rechazo.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no debe exceder 2,000 caracteres.',
        ]);

        $rechazada = DB::transaction(fn () => Requisicion::query()
            ->whereKey($requisicion->id)
            ->where('status', 'CAPTURADA')
            ->update(['status' => 'PAGO_RECHAZADO']) === 1);

        if (! $rechazada) {
            return back()->with('error', 'Solo se puede rechazar el pago de una requisición capturada.');
        }

        $motivo = trim($data['motivo']);
        $requisicion->auditLog('CAMBIO_ESTATUS', "Pago rechazado: {$requisicion->folio}. Motivo: {$motivo}", [
            'status' => ['CAPTURADA', 'PAGO_RECHAZADO'],
        ]);

        $requisicion->refresh()->load(['solicitante.user', 'creadaPor']);
        $this->notifications->notify(
            topic: NotificationTopic::Pagos,
            event: 'pago.rechazado',
            title: "Pago rechazado: {$requisicion->folio}",
            message: "{$request->user()->name} rechazó el pago. Motivo: ".str($motivo)->limit(160),
            severity: 'danger',
            url: route('requisiciones.show', $requisicion->id, false),
            direct: [$requisicion->solicitante?->user, $requisicion->creadaPor],
            actor: $request->user(),
            canSee: fn ($u) => $u->can('viewPayments', $requisicion),
        );

        return back()->with('success', 'Pago rechazado. Se avisó al solicitante.');
    }

    public function updateFechaPagoGeneral(Request $request, Requisicion $requisicion)
    {
        $this->authorize('editPayment', $requisicion);

        $data = $request->validate([
            'fecha_pago' => ['required', 'date'],
        ]);

        $cantidadPagos = $requisicion->pagos()->count();
        $totalPagado = (float) $requisicion->pagos()->sum('monto');
        $montoReq = (float) $requisicion->monto_total;
        $pendiente = max(0, $montoReq - $totalPagado);

        if ($cantidadPagos === 0 || $totalPagado + 0.00001 < $montoReq || $pendiente > 0.00001) {
            return back()->withErrors([
                'fecha_pago' => 'La fecha general de pago solo puede registrarse cuando exista al menos un pago y el monto total de la requisición esté completamente cubierto.',
            ]);
        }

        $requisicion->update(['fecha_pago' => $data['fecha_pago']]);

        return back()->with('success', 'Fecha de pago de la requisición guardada correctamente.');
    }

    private function safeNombre($model): string
    {
        if (! $model) {
            return '—';
        }
        foreach (['nombre_completo', 'nombreCompleto', 'name', 'nombre'] as $k) {
            if (! empty($model->{$k})) {
                return (string) $model->{$k};
            }
        }
        $parts = [];
        foreach (['nombre', 'nombres', 'apellido_paterno', 'apellido_materno', 'apellidoPaterno', 'apellidoMaterno'] as $k) {
            if (! empty($model->{$k})) {
                $parts[] = $model->{$k};
            }
        }
        $txt = trim(implode(' ', $parts));

        return $txt !== '' ? $txt : '—';
    }

    private function buildBeneficiario(Requisicion $requisicion): array
    {
        // Nota: si hay proveedor, pagamos a proveedor. Si no, es reembolso al solicitante.
        if ($requisicion->proveedor) {
            return [
                'nombre' => $requisicion->proveedor->razon_social ?? '—',
                'rfc' => $requisicion->proveedor->rfc ?? null,
                'clabe' => $requisicion->proveedor->clabe ?? null,
                'banco' => $requisicion->proveedor->banco ?? null,
            ];
        }

        $s = $requisicion->solicitante;

        return [
            'nombre' => $this->safeNombre($s),
            'rfc' => $s->rfc ?? null,
            'clabe' => $s->clabe ?? null,
            'banco' => $s->banco ?? null,
        ];
    }
}
