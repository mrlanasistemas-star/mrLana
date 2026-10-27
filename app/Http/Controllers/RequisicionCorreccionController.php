<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Models\Concepto;
use App\Models\Corporativo;
use App\Models\Empleado;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Corrección administrativa de los datos de cabecera de una requisición ya
 * registrada (concepto, solicitante, sucursal, corporativo, proveedor y
 * observaciones), con permiso «requisiciones.corregir».
 *
 * A diferencia de la captura, los catálogos se ofrecen completos (activos e
 * inactivos, de cualquier sucursal o corporativo): es una corrección, no una
 * captura nueva. No toca folio, estatus, montos ni items, salvo la sucursal de
 * los items si se pide explícitamente. Cada corrección queda en la bitácora
 * con valores anteriores y nuevos, y se notifica a los suscritos a
 * Requisiciones y a quien creó la requisición.
 */
class RequisicionCorreccionController extends Controller
{
    /** Campos corregibles → etiqueta legible. */
    public const FIELDS = [
        'comprador_corp_id' => 'Corporativo comprador',
        'sucursal_id' => 'Sucursal',
        'solicitante_id' => 'Solicitante',
        'concepto_id' => 'Concepto',
        'proveedor_id' => 'Proveedor',
        'observaciones' => 'Observaciones',
    ];

    public function __construct(private NotificationService $notifications) {}

    public function edit(Request $request, Requisicion $requisicion): Response
    {
        $this->authorize('correct', $requisicion);

        $requisicion->loadCount('detalles');

        return Inertia::render('Requisiciones/Corregir', [
            'requisicion' => [
                'id' => $requisicion->id,
                'folio' => $requisicion->folio,
                'status' => $requisicion->status,
                'monto_total' => (float) $requisicion->monto_total,
                'comprador_corp_id' => $requisicion->comprador_corp_id,
                'sucursal_id' => $requisicion->sucursal_id,
                'solicitante_id' => $requisicion->solicitante_id,
                'concepto_id' => $requisicion->concepto_id,
                'proveedor_id' => $requisicion->proveedor_id,
                'observaciones' => $requisicion->observaciones,
                'items_total' => (int) $requisicion->detalles_count,
                'items_misma_sucursal' => $requisicion->detalles()->where('sucursal_id', $requisicion->sucursal_id)->count(),
            ],
            'catalogos' => $this->catalogos(),
        ]);
    }

    public function update(Request $request, Requisicion $requisicion): RedirectResponse
    {
        $this->authorize('correct', $requisicion);

        $data = $request->validate([
            'comprador_corp_id' => ['required', 'integer', Rule::exists('corporativos', 'id')],
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursals', 'id')],
            'solicitante_id' => ['required', 'integer', Rule::exists('empleados', 'id')],
            'concepto_id' => ['required', 'integer', Rule::exists('conceptos', 'id')],
            // Si la requisición ya tenía proveedor no se puede dejar sin él.
            'proveedor_id' => [$requisicion->proveedor_id ? 'required' : 'nullable', 'integer', Rule::exists('proveedors', 'id')],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
            'actualizar_items_sucursal' => ['sometimes', 'boolean'],
        ], [
            'comprador_corp_id.required' => 'Selecciona el corporativo comprador.',
            'sucursal_id.required' => 'Selecciona la sucursal.',
            'solicitante_id.required' => 'Selecciona el solicitante.',
            'concepto_id.required' => 'Selecciona el concepto.',
            'proveedor_id.required' => 'Selecciona el proveedor.',
            '*.exists' => 'El registro seleccionado no existe.',
            'observaciones.max' => 'Las observaciones no deben exceder 5,000 caracteres.',
            'motivo.required' => 'Escribe el motivo de la corrección.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no debe exceder 500 caracteres.',
        ]);

        $sucursalCorp = Sucursal::query()->whereKey($data['sucursal_id'])->value('corporativo_id');
        if ((int) $sucursalCorp !== (int) $data['comprador_corp_id']) {
            throw ValidationException::withMessages(['sucursal_id' => 'La sucursal no pertenece al corporativo seleccionado.']);
        }

        $motivo = trim($data['motivo']);
        $nuevos = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $value = $data[$field] ?? null;
            $nuevos[$field] = $field === 'observaciones'
                ? (($value = trim((string) $value)) === '' ? null : $value)
                : ($value === null ? null : (int) $value);
        }

        $antes = $requisicion->only(array_keys(self::FIELDS));
        $cambios = array_filter(
            $nuevos,
            fn ($value, $field) => (string) ($antes[$field] ?? '') !== (string) ($value ?? ''),
            ARRAY_FILTER_USE_BOTH,
        );

        $oldSucursal = $requisicion->sucursal_id;
        $moverItems = (bool) ($data['actualizar_items_sucursal'] ?? false) && isset($cambios['sucursal_id']);

        if ($cambios === []) {
            return back()->with('warning','No hubo cambios que guardar.');
        }

        $user = $request->user();

        $itemsMovidos = DB::transaction(function () use ($requisicion, $cambios, $antes, $motivo, $moverItems, $oldSucursal, $user) {
            // Guardado sin el registro automático: la bitácora se escribe abajo,
            // una sola vez, con nombres legibles y el motivo.
            $requisicion->fill($cambios)->saveQuietly();

            $movidos = 0;
            if ($moverItems) {
                foreach ($requisicion->detalles()->where('sucursal_id', $oldSucursal)->get() as $detalle) {
                    $detalle->sucursal_id = $cambios['sucursal_id'];
                    $detalle->saveQuietly();
                    $movidos++;
                }
            }

            $log = [];
            $lineas = [];
            foreach ($cambios as $field => $new) {
                $old = $antes[$field] ?? null;
                $log[$field] = [$this->describe($field, $old), $this->describe($field, $new)];
                $lineas[] = '- '.self::FIELDS[$field].': '.$this->short($log[$field][0]).' → '.$this->short($log[$field][1]);
            }
            if ($movidos > 0) {
                $log['items_sucursal'] = [$this->describe('sucursal_id', $oldSucursal), $this->describe('sucursal_id', $cambios['sucursal_id'])." ({$movidos} item(s))"];
                $lineas[] = "- Sucursal de {$movidos} item(s) actualizada.";
            }

            $requisicion->auditLog(
                'CORRECCION',
                "{$user->name} corrigió datos de {$requisicion->folio}. Motivo: {$motivo}\nCambios:\n".implode("\n", $lineas),
                $log,
            );

            return $movidos;
        });

        $this->notify($requisicion, $user, array_keys($cambios), $motivo);

        $msg = 'Datos de la requisición corregidos.';
        if ($itemsMovidos > 0) {
            $msg .= " También se actualizó la sucursal de {$itemsMovidos} item(s).";
        }

        return redirect()->route('requisiciones.show', $requisicion->id)->with('success', $msg);
    }

    /**
     * Catálogos completos (activos e inactivos) para corregir: nombre visible
     * en `nombre` y un dato de apoyo en `detalle` (ambos se pueden buscar).
     *
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        $inactivo = fn ($activo) => $activo === false || $activo === 0 || $activo === '0';

        $corporativos = Corporativo::query()->orderBy('nombre')->get(['id', 'nombre', 'activo'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'detalle' => $inactivo($c->activo) ? 'Inactivo' : null,
            ]);

        $corpNames = $corporativos->pluck('nombre', 'id');

        $sucursales = Sucursal::query()->orderBy('nombre')->get(['id', 'nombre', 'codigo', 'corporativo_id', 'activo'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'nombre' => $s->nombre,
                'corporativo_id' => $s->corporativo_id,
                'detalle' => collect([$s->codigo, $corpNames[$s->corporativo_id] ?? null, $inactivo($s->activo) ? 'Inactiva' : null])
                    ->filter()->implode(' · ') ?: null,
            ]);

        $sucNames = $sucursales->pluck('nombre', 'id');

        $empleados = Empleado::query()->orderBy('nombre')->orderBy('apellido_paterno')
            ->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno', 'puesto', 'sucursal_id', 'activo'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'nombre' => trim($e->nombre.' '.$e->apellido_paterno.' '.($e->apellido_materno ?? '')),
                'sucursal_id' => $e->sucursal_id,
                'detalle' => collect([$sucNames[$e->sucursal_id] ?? null, $e->puesto, $inactivo($e->activo) ? 'Inactivo' : null])
                    ->filter()->implode(' · ') ?: null,
            ]);

        $conceptos = Concepto::query()->orderBy('nombre')->get(['id', 'nombre', 'activo'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'detalle' => $inactivo($c->activo) ? 'Inactivo' : null,
            ]);

        $proveedores = Proveedor::query()->orderBy('razon_social')->get(['id', 'razon_social', 'rfc', 'status'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->razon_social,
                'detalle' => collect([$p->rfc, strtoupper((string) $p->status) !== 'ACTIVO' ? 'Inactivo' : null])
                    ->filter()->implode(' · ') ?: null,
            ]);

        return compact('corporativos', 'sucursales', 'empleados', 'conceptos', 'proveedores');
    }

    /** Valor legible para la bitácora: «Nombre (#id)». */
    private function describe(string $field, mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }
        if ($field === 'observaciones') {
            return (string) $id;
        }

        $name = match ($field) {
            'comprador_corp_id' => Corporativo::query()->whereKey($id)->value('nombre'),
            'sucursal_id' => Sucursal::query()->whereKey($id)->value('nombre'),
            'concepto_id' => Concepto::query()->whereKey($id)->value('nombre'),
            'proveedor_id' => Proveedor::query()->whereKey($id)->value('razon_social'),
            'solicitante_id' => ($e = Empleado::query()->find($id, ['nombre', 'apellido_paterno', 'apellido_materno']))
                ? trim($e->nombre.' '.$e->apellido_paterno.' '.($e->apellido_materno ?? ''))
                : null,
            default => null,
        };

        return $name ? "{$name} (#{$id})" : "#{$id}";
    }

    private function short(?string $value): string
    {
        if ($value === null) {
            return 'vacío';
        }

        return mb_strlen($value) > 120 ? mb_substr($value, 0, 120).'…' : $value;
    }

    /**
     * Campana a los roles suscritos a Requisiciones (incluida la persona que
     * corrige, si su rol está suscrito) y aviso directo a quien la creó.
     *
     * @param  list<string>  $fields
     */
    private function notify(Requisicion $requisicion, User $actor, array $fields, string $motivo): void
    {
        $campos = implode(', ', array_map(fn ($f) => mb_strtolower(self::FIELDS[$f]), $fields));

        $this->notifications->notify(
            topic: NotificationTopic::Requisiciones,
            event: 'requisicion.corregida',
            title: "Datos corregidos en {$requisicion->folio}",
            message: "{$actor->name} corrigió {$campos}. Motivo: {$motivo}",
            severity: 'info',
            url: route('requisiciones.show', $requisicion->id, false),
            direct: array_filter([$requisicion->creadaPor()->first()]),
            actor: $actor,
            canSee: fn (User $u) => $u->can('view', $requisicion),
        );
    }
}
