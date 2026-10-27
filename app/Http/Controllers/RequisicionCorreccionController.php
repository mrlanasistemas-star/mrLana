<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Http\Requests\Requisicion\RequisicionRules;
use App\Models\Ajuste;
use App\Models\Concepto;
use App\Models\Corporativo;
use App\Models\Detalle;
use App\Models\Empleado;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\BusinessDate;
use App\Support\Requisiciones\DetallesCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Corrección administrativa de una requisición ya registrada, con permiso
 * «requisiciones.corregir». Es la misma captura del alta pero sobre una
 * requisición existente.
 *
 * - Cabecera (siempre): corporativo, sucursal, solicitante, concepto,
 *   proveedor, fecha de solicitud, fecha esperada de pago y observaciones.
 *   Los catálogos se ofrecen completos (activos e inactivos): es una
 *   corrección, no una captura nueva, y la fecha de solicitud puede ser pasada.
 * - Items y montos: solo mientras no haya dinero ni evidencias de por medio
 *   (ver bloqueoItems()). Los montos se recalculan en el servidor con la misma
 *   regla del alta (DetallesCalculator); nunca se toman del cliente.
 * - Folio y estatus no cambian nunca.
 *
 * Cada corrección queda en la bitácora (acción CORRECCION) con valores
 * anteriores y nuevos, y se notifica a los suscritos a Requisiciones y a
 * quien creó la requisición.
 */
class RequisicionCorreccionController extends Controller
{
    /** Campos de cabecera corregibles → etiqueta legible. */
    public const FIELDS = [
        'comprador_corp_id' => 'Corporativo comprador',
        'sucursal_id' => 'Sucursal',
        'solicitante_id' => 'Solicitante',
        'concepto_id' => 'Concepto',
        'proveedor_id' => 'Proveedor',
        'fecha_solicitud' => 'Fecha de solicitud',
        'fecha_pago_esperada' => 'Fecha esperada de pago',
        'observaciones' => 'Observaciones',
    ];

    /** Etiquetas de lo que cambia al corregir items (bitácora y aviso). */
    public const ITEM_FIELDS = [
        'items' => 'Items',
        'monto_subtotal' => 'Subtotal',
        'monto_total' => 'Monto total',
    ];

    /** Estatus anteriores al pago en los que se pueden corregir items y montos. */
    public const ITEMS_STATUSES = ['BORRADOR', 'CAPTURADA', 'PAGO_AUTORIZADO', 'PAGO_RECHAZADO'];

    private const DATE_FIELDS = ['fecha_solicitud', 'fecha_pago_esperada'];

    public function __construct(private NotificationService $notifications) {}

    public function edit(Request $request, Requisicion $requisicion): Response
    {
        $this->authorize('correct', $requisicion);

        $requisicion->load(['detalles' => fn ($q) => $q->orderBy('id')]);
        $itemsTotal = round((float) $requisicion->detalles->sum('total'), 2);

        return Inertia::render('Requisiciones/Corregir', [
            'requisicion' => [
                'id' => $requisicion->id,
                'folio' => $requisicion->folio,
                'status' => $requisicion->status,
                'monto_subtotal' => (float) $requisicion->monto_subtotal,
                'monto_total' => (float) $requisicion->monto_total,
                // Ajustes ya aplicados (monto_total − suma de items), igual que en el detalle.
                'ajustes_aplicados' => round((float) $requisicion->monto_total - $itemsTotal, 2),
                'comprador_corp_id' => $requisicion->comprador_corp_id,
                'sucursal_id' => $requisicion->sucursal_id,
                'solicitante_id' => $requisicion->solicitante_id,
                'concepto_id' => $requisicion->concepto_id,
                'proveedor_id' => $requisicion->proveedor_id,
                'fecha_solicitud' => $this->ymd($requisicion->fecha_solicitud),
                'fecha_pago_esperada' => $this->ymd($requisicion->fecha_pago_esperada),
                'observaciones' => $requisicion->observaciones,
                'detalles' => $requisicion->detalles->map(fn (Detalle $d) => [
                    'id' => $d->id,
                    'sucursal_id' => $d->sucursal_id,
                    'cantidad' => (float) $d->cantidad,
                    'descripcion' => (string) $d->descripcion,
                    'precio_unitario' => (float) $d->precio_unitario,
                    'genera_iva' => (bool) ($d->genera_iva ?? true),
                    'subtotal' => (float) $d->subtotal,
                    'iva' => (float) $d->iva,
                    'total' => (float) $d->total,
                ])->values(),
                'items_total' => $requisicion->detalles->count(),
                'items_misma_sucursal' => $requisicion->detalles->where('sucursal_id', $requisicion->sucursal_id)->count(),
                'items_editables' => ($bloqueo = $this->bloqueoItems($requisicion)) === null,
                'items_bloqueo' => $bloqueo,
            ],
            'catalogos' => $this->catalogos(),
            'today' => BusinessDate::todayString(),
            'can' => [
                'ver_ajustes' => $request->user()->can('viewAjustes', $requisicion),
            ],
        ]);
    }

    public function update(Request $request, Requisicion $requisicion): RedirectResponse
    {
        $this->authorize('correct', $requisicion);

        $itemRules = array_filter(RequisicionRules::header(), fn ($key) => str_starts_with($key, 'detalles'), ARRAY_FILTER_USE_KEY);
        $itemRules['detalles'] = ['sometimes', 'array', 'min:1'];

        $data = $request->validate([
            'comprador_corp_id' => ['required', 'integer', Rule::exists('corporativos', 'id')],
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursals', 'id')],
            'solicitante_id' => ['required', 'integer', Rule::exists('empleados', 'id')],
            'concepto_id' => ['required', 'integer', Rule::exists('conceptos', 'id')],
            // Si la requisición ya tenía proveedor no se puede dejar sin él.
            'proveedor_id' => [$requisicion->proveedor_id ? 'required' : 'nullable', 'integer', Rule::exists('proveedors', 'id')],
            // Es una corrección: la fecha de solicitud puede ser pasada.
            'fecha_solicitud' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'fecha_pago_esperada' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
            'actualizar_items_sucursal' => ['sometimes', 'boolean'],
        ] + $itemRules, [
            'comprador_corp_id.required' => 'Selecciona el corporativo comprador.',
            'sucursal_id.required' => 'Selecciona la sucursal.',
            'solicitante_id.required' => 'Selecciona el solicitante.',
            'concepto_id.required' => 'Selecciona el concepto.',
            'proveedor_id.required' => 'Selecciona el proveedor.',
            '*.exists' => 'El registro seleccionado no existe.',
            'motivo.required' => 'Escribe el motivo de la corrección.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no debe exceder 500 caracteres.',
            'detalles.*.sucursal_id.exists' => 'La sucursal del item no existe.',
        ] + RequisicionRules::messages());

        $sucursalCorp = Sucursal::query()->whereKey($data['sucursal_id'])->value('corporativo_id');
        if ((int) $sucursalCorp !== (int) $data['comprador_corp_id']) {
            throw ValidationException::withMessages(['sucursal_id' => 'La sucursal no pertenece al corporativo seleccionado.']);
        }

        $antes = $this->headerValues($requisicion);
        $fechaSolicitud = $data['fecha_solicitud'] ?? $antes['fecha_solicitud'];
        $fechaEsperada = array_key_exists('fecha_pago_esperada', $data) ? $data['fecha_pago_esperada'] : $antes['fecha_pago_esperada'];
        if ($fechaEsperada && $fechaSolicitud && $fechaEsperada < $fechaSolicitud) {
            throw ValidationException::withMessages(['fecha_pago_esperada' => 'La fecha esperada de pago no puede ser anterior a la fecha de solicitud.']);
        }

        $motivo = trim($data['motivo']);
        $nuevos = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $value = match ($field) {
                'fecha_solicitud' => $fechaSolicitud,
                'fecha_pago_esperada' => $fechaEsperada,
                default => $data[$field] ?? null,
            };
            $nuevos[$field] = match (true) {
                $field === 'observaciones' => ($value = trim((string) $value)) === '' ? null : $value,
                in_array($field, self::DATE_FIELDS, true) => ($value === null || $value === '') ? null : (string) $value,
                default => $value === null ? null : (int) $value,
            };
        }

        $cambios = array_filter(
            $nuevos,
            fn ($value, $field) => (string) ($antes[$field] ?? '') !== (string) ($value ?? ''),
            ARRAY_FILTER_USE_BOTH,
        );

        // Items: solo se toman en cuenta si se enviaron y son distintos a los actuales.
        $nuevosItems = null;
        if (array_key_exists('detalles', $data)) {
            [$clean, $subtotal, $total] = DetallesCalculator::sanitize($data['detalles']);
            $actuales = $requisicion->detalles()->orderBy('id')->get();
            if ($this->itemsKey($clean) !== $this->itemsKey($actuales->all())) {
                $nuevosItems = [$clean, $subtotal, $total];
            }
        }

        $oldSucursal = $requisicion->sucursal_id;
        $moverItems = $nuevosItems === null
            && ! array_key_exists('detalles', $data)
            && (bool) ($data['actualizar_items_sucursal'] ?? false)
            && isset($cambios['sucursal_id']);

        if ($cambios === [] && $nuevosItems === null) {
            return back()->with('warning', 'No hubo cambios que guardar.');
        }

        $user = $request->user();

        [$itemsMovidos, $camposCambiados] = DB::transaction(function () use ($requisicion, $cambios, $antes, $motivo, $moverItems, $oldSucursal, $user, $nuevosItems) {
            // Se bloquea la fila para que un pago registrado al mismo tiempo no
            // quede con montos distintos a los autorizados.
            $locked = Requisicion::query()->whereKey($requisicion->id)->lockForUpdate()->firstOrFail();

            $log = [];
            $lineas = [];
            $campos = array_keys($cambios);

            if ($nuevosItems !== null) {
                if (($bloqueo = $this->bloqueoItems($locked)) !== null) {
                    throw ValidationException::withMessages(['detalles' => $bloqueo]);
                }

                [$clean, $subtotal, $itemsTotal] = $nuevosItems;
                $actuales = $locked->detalles()->orderBy('id')->get();
                // Los ajustes ya aplicados se conservan sobre el nuevo total de items.
                $ajustesAplicados = round((float) $locked->monto_total - (float) $actuales->sum('total'), 2);
                $total = max(0, round($itemsTotal + $ajustesAplicados, 2));

                $montosAntes = ['monto_subtotal' => (float) $locked->monto_subtotal, 'monto_total' => (float) $locked->monto_total];

                // Sin el registro automático por item: la bitácora se escribe una sola vez abajo.
                Detalle::withoutEvents(function () use ($locked, $clean) {
                    $locked->detalles()->delete();
                    $locked->detalles()->createMany($clean);
                });

                $locked->forceFill(['monto_subtotal' => $subtotal, 'monto_total' => $total]);

                $log['items'] = [$this->describeItems($actuales->all()), $this->describeItems($clean)];
                $lineas[] = '- Items: '.$this->itemsResumen($actuales->all(), $clean);
                $campos[] = 'items';
                foreach (['monto_subtotal' => $subtotal, 'monto_total' => $total] as $field => $new) {
                    if (round($montosAntes[$field], 2) !== round((float) $new, 2)) {
                        $log[$field] = [$montosAntes[$field], $new];
                        $lineas[] = '- '.self::ITEM_FIELDS[$field].': '.$this->money($montosAntes[$field]).' → '.$this->money($new);
                        $campos[] = $field;
                    }
                }
                if ($ajustesAplicados != 0.0) {
                    $lineas[] = '- Se conservaron ajustes aplicados por '.$this->money($ajustesAplicados).'.';
                }
            }

            $fill = $cambios;
            foreach (self::DATE_FIELDS as $field) {
                if (array_key_exists($field, $fill)) {
                    $fill[$field] = $fill[$field] === null ? null : Carbon::createFromFormat('!Y-m-d', $fill[$field]);
                }
            }

            // Guardado sin el registro automático: la bitácora se escribe abajo,
            // una sola vez, con nombres legibles y el motivo.
            $locked->fill($fill)->saveQuietly();

            $movidos = 0;
            if ($moverItems) {
                foreach ($locked->detalles()->where('sucursal_id', $oldSucursal)->get() as $detalle) {
                    $detalle->sucursal_id = $cambios['sucursal_id'];
                    $detalle->saveQuietly();
                    $movidos++;
                }
            }

            $cabecera = [];
            foreach ($cambios as $field => $new) {
                $old = $antes[$field] ?? null;
                $cabecera[$field] = [$this->describe($field, $old), $this->describe($field, $new)];
            }
            $lineasCabecera = array_map(
                fn ($field) => '- '.self::FIELDS[$field].': '.$this->short($cabecera[$field][0]).' → '.$this->short($cabecera[$field][1]),
                array_keys($cabecera),
            );
            $log = $cabecera + $log;
            $lineas = array_merge($lineasCabecera, $lineas);

            if ($movidos > 0) {
                $log['items_sucursal'] = [$this->describe('sucursal_id', $oldSucursal), $this->describe('sucursal_id', $cambios['sucursal_id'])." ({$movidos} item(s))"];
                $lineas[] = "- Sucursal de {$movidos} item(s) actualizada.";
            }

            $locked->auditLog(
                'CORRECCION',
                "{$user->name} corrigió datos de {$locked->folio}. Motivo: {$motivo}\nCambios:\n".implode("\n", $lineas),
                $log,
            );

            return [$movidos, $campos];
        });

        $this->notify($requisicion, $user, $camposCambiados, $motivo);

        $msg = 'Datos de la requisición corregidos.';
        if ($nuevosItems !== null) {
            $msg = 'Requisición corregida. Los items y montos se recalcularon.';
        }
        if ($itemsMovidos > 0) {
            $msg .= " También se actualizó la sucursal de {$itemsMovidos} item(s).";
        }

        return redirect()->route('requisiciones.show', $requisicion->id)->with('success', $msg);
    }

    /**
     * Motivo por el que no se pueden corregir items ni montos, o null si se
     * puede. Solo antes del pago: sin pagos registrados (también parciales),
     * sin comprobantes y sin ajustes pendientes o aprobados (que se calcularon
     * sobre el monto actual).
     */
    private function bloqueoItems(Requisicion $requisicion): ?string
    {
        if ($requisicion->pagos()->exists()) {
            return 'Esta requisición ya tiene pagos; los montos e items no se pueden cambiar. Usa Ajustes.';
        }
        if ($requisicion->comprobantes()->exists()) {
            return 'Esta requisición ya tiene comprobantes; los montos e items no se pueden cambiar. Usa Ajustes.';
        }
        if (! in_array($requisicion->status, self::ITEMS_STATUSES, true)) {
            return 'Esta requisición ya está pagada o en comprobación; los montos e items no se pueden cambiar. Usa Ajustes.';
        }
        if ($requisicion->ajustes()->whereIn('estatus', [Ajuste::ESTATUS_PENDIENTE, Ajuste::ESTATUS_APROBADO])->exists()) {
            return 'Esta requisición tiene ajustes pendientes o aprobados sin aplicar. Resuélvelos o cancélalos en Ajustes antes de cambiar items o montos.';
        }

        return null;
    }

    /** @return array<string, mixed> Valores actuales de cabecera, normalizados como los nuevos. */
    private function headerValues(Requisicion $requisicion): array
    {
        $values = $requisicion->only(array_keys(self::FIELDS));
        foreach (self::DATE_FIELDS as $field) {
            $values[$field] = $this->ymd($requisicion->{$field});
        }

        return $values;
    }

    private function ymd(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10);
    }

    /**
     * Firma comparable de una lista de items (modelos o arreglos limpios).
     *
     * @param  array<int, Detalle|array<string, mixed>>  $items
     */
    private function itemsKey(array $items): string
    {
        return json_encode(array_map(fn ($d) => [
            ! empty($d['sucursal_id']) ? (int) $d['sucursal_id'] : null,
            number_format((float) $d['cantidad'], 2, '.', ''),
            trim((string) $d['descripcion']),
            number_format((float) $d['precio_unitario'], 2, '.', ''),
            (bool) ($d['genera_iva'] ?? true),
        ], array_values($items)));
    }

    /** @param  array<int, Detalle|array<string, mixed>>  $items */
    private function describeItems(array $items): string
    {
        $total = 0.0;
        $lineas = array_map(function ($d) use (&$total) {
            $total += (float) $d['total'];

            return rtrim(rtrim(number_format((float) $d['cantidad'], 2, '.', ''), '0'), '.')
                .' × '.trim((string) $d['descripcion'])
                .' @ '.$this->money($d['precio_unitario'])
                .(($d['genera_iva'] ?? true) ? ' + IVA' : '');
        }, array_values($items));

        return count($lineas).' item(s), total '.$this->money($total).': '.implode('; ', $lineas);
    }

    /**
     * «2 → 3 item(s); total de items $1,160.00 → $2,320.00».
     *
     * @param  array<int, Detalle|array<string, mixed>>  $antes
     * @param  array<int, array<string, mixed>>  $despues
     */
    private function itemsResumen(array $antes, array $despues): string
    {
        $sum = fn (array $items) => array_sum(array_map(fn ($d) => (float) $d['total'], $items));

        return count($antes).' → '.count($despues).' item(s); total de items '
            .$this->money($sum($antes)).' → '.$this->money($sum($despues));
    }

    private function money(mixed $value): string
    {
        $v = (float) $value;

        return ($v < 0 ? '-$' : '$').number_format(abs($v), 2);
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

        $proveedores = Proveedor::query()->orderBy('razon_social')->get(['id', 'razon_social', 'rfc', 'clabe', 'banco', 'status'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->razon_social,
                'razon_social' => $p->razon_social,
                'rfc' => $p->rfc,
                'clabe' => $p->clabe,
                'banco' => $p->banco,
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
        if ($field === 'observaciones' || in_array($field, self::DATE_FIELDS, true)) {
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
        $labels = self::FIELDS + self::ITEM_FIELDS;
        $campos = implode(', ', array_map(fn ($f) => mb_strtolower($labels[$f] ?? $f), $fields));

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
