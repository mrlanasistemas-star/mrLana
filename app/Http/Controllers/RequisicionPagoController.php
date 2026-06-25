<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pagos\StorePagoRequest;
use App\Models\Pago;
use App\Models\Requisicion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class RequisicionPagoController extends Controller {

    public function create(Requisicion $requisicion) {
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
        $pagosShape = $pagos->map(function ($p) use ($benef) {
            $url = null;
            if (!empty($p->archivo_path)) {
                $url = Storage::disk('public')->url($p->archivo_path);
            }
            return [
                'id' => (int) $p->id,
                'fecha_pago' => optional($p->fecha_pago)->format('Y-m-d'),
                'tipo_pago' => (string) ($p->tipo_pago ?? ''),
                'monto' => (float) ($p->monto ?? 0),
                'referencia' => $p->referencia ?? null,
                'archivo' => $url ? [
                    'label' => $p->archivo_original ?: 'Ver archivo',
                    'url' => $url,
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
                    'fecha_autorizacion' => optional($requisicion->fecha_autorizacion)->format('Y-m-d'),
                    'fecha_pago_programada' => optional($requisicion->fecha_pago)->format('Y-m-d'),
                    'cantidad_pagos'                    => $cantidadPagos,
                    'puede_definir_fecha_pago_general'  => $puedeDefinirFechaGeneral,
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
        ]);
    }

    public function authorizePago(Request $request, Requisicion $requisicion) {
        $rol = strtoupper((string) (auth()->user()->rol ?? 'COLABORADOR'));
        abort_unless(in_array($rol, ['ADMIN', 'CONTADOR'], true), 403);
        $data = $request->validate([
            'fecha_pago' => ['required', 'date'],
        ]);
        if (strtoupper((string) $requisicion->status) !== 'CAPTURADA') {
            return back()->with('error', 'La requisición no se puede autorizar en su estado actual.');
        }
        $requisicion->update([
            'fecha_autorizacion' => now(),
            'fecha_pago'         => $data['fecha_pago'], // fecha posible/programada
            'status'             => 'PAGO_AUTORIZADO',
        ]);
        $requisicion->load(['solicitante.user']);
        $colaborador = $requisicion->solicitante?->user;
        if ($colaborador && $colaborador->email) {
            Mail::to($colaborador->email)
                ->send(new \App\Mail\RequisicionPagoAutorizadoMail($requisicion, $data['fecha_pago']));
        }
        return back()->with('success', 'Pago autorizado correctamente.');
    }

    public function store(StorePagoRequest $request, Requisicion $requisicion) {
        $requisicion->load(['proveedor', 'solicitante.user']);
        return DB::transaction(function () use ($request, $requisicion) {
            $pagadoActual = (float) $requisicion->pagos()->sum('monto');
            $montoTotal   = (float) $requisicion->monto_total;
            $pendiente    = max(0, $montoTotal - $pagadoActual);
            $monto = round((float) $request->input('monto'), 2);
            if ($pendiente > 0 && $monto > ($pendiente + 0.00001)) {
                return back()->withErrors([
                    'monto' => "El monto ($monto) excede lo pendiente ($pendiente).",
                ]);
            }
            if ($pendiente <= 0 && abs($monto) > 0.00001) {
                return back()->withErrors([
                    'monto' => "Pendiente en 0. Solo se permite monto 0.00.",
                ]);
            }
            $file   = $request->file('archivo');
            $folder = "requisiciones/{$requisicion->id}/pagos";
            $stored = null;
            try {
                $stored = $file->storePublicly($folder, 'public');
                $benef = $this->buildBeneficiario($requisicion);
                (new Pago())->forceFill([
                    'requisicion_id'      => $requisicion->id,
                    'beneficiario_nombre' => $benef['nombre'] ?? '—',
                    'tipo_pago'           => $request->input('tipo_pago'),
                    'monto'               => $monto,
                    'fecha_pago'          => $request->input('fecha_pago'), // fecha real del pago
                    'archivo_path'        => $stored,
                    'archivo_original'    => $file->getClientOriginalName(),
                    'mime'                => $file->getClientMimeType(),
                    'size'                => $file->getSize(),
                    'referencia'          => $request->input('referencia'),
                    'user_carga_id'       => (int) auth()->id(),
                ])->save();
                $pendienteDespues = max(0, $pendiente - $monto);
                $nuevoStatus = ($pendienteDespues <= 0.00001) ? 'PAGADA' : 'PAGO_AUTORIZADO';
                $requisicion->update([
                    'status' => $nuevoStatus,
                ]);
                if ($nuevoStatus === 'PAGADA') {
                    $colaborador = $requisicion->solicitante?->user;
                    if ($colaborador && $colaborador->email) {
                        Mail::to($colaborador->email)
                            ->send(new \App\Mail\RequisicionPagadaMail($requisicion));
                    }
                }
                return back()->with('success', 'Pago registrado correctamente.');
            } catch (\Throwable $e) {
                if ($stored) {
                    Storage::disk('public')->delete($stored);
                }
                throw $e;
            }
        });
    }

    public function updateFechaPagoGeneral(Request $request, Requisicion $requisicion) {
        $rol = strtoupper((string) (auth()->user()->rol ?? 'COLABORADOR'));
        abort_unless(in_array($rol, ['ADMIN', 'CONTADOR'], true), 403);

        $data = $request->validate([
            'fecha_pago' => ['required', 'date'],
        ]);

        $cantidadPagos = $requisicion->pagos()->count();
        $totalPagado   = (float) $requisicion->pagos()->sum('monto');
        $montoReq      = (float) $requisicion->monto_total;
        $pendiente     = max(0, $montoReq - $totalPagado);

        if ($cantidadPagos === 0 || $totalPagado + 0.00001 < $montoReq || $pendiente > 0.00001) {
            return back()->withErrors([
                'fecha_pago' => 'La fecha general de pago solo puede registrarse cuando exista al menos un pago y el monto total de la requisición esté completamente cubierto.',
            ]);
        }

        $requisicion->update(['fecha_pago' => $data['fecha_pago']]);

        return back()->with('success', 'Fecha de pago de la requisición guardada correctamente.');
    }

    private function safeNombre($model): string {
        if (!$model) return '—';
        foreach (['nombre_completo', 'nombreCompleto', 'name', 'nombre'] as $k) {
            if (!empty($model->{$k})) return (string) $model->{$k};
        }
        $parts = [];
        foreach (['nombre', 'nombres', 'apellido_paterno', 'apellido_materno', 'apellidoPaterno', 'apellidoMaterno'] as $k) {
            if (!empty($model->{$k})) $parts[] = $model->{$k};
        }
        $txt = trim(implode(' ', $parts));
        return $txt !== '' ? $txt : '—';
    }

    private function buildBeneficiario(Requisicion $requisicion): array {
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
