<?php

namespace App\Http\Controllers\Exports;

use App\Exports\Requisiciones\RequisicionesExport;
use App\Http\Controllers\RequisicionController;
use App\Models\Concepto;
use App\Models\Corporativo;
use App\Models\Empleado;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Sucursal;
use App\Services\Pdf\PdfService;
use App\Support\BusinessDate;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RequisicionExportController
{
    public function excel(Request $request)
    {
        $rows = $this->buildRows($request);
        $filters = $this->presentFilters($request);

        $meta = [
            'title' => 'Reporte de Requisiciones',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => BusinessDate::now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
            'footer_left' => 'ERP MR-Lana',
        ];

        return Excel::download(
            new RequisicionesExport($rows, $filters, $meta),
            'requisiciones.xlsx'
        );
    }

    public function pdf(Request $request)
    {
        $rows = $this->buildRows($request);
        $filters = $this->presentFilters($request);

        $meta = [
            'title' => 'Reporte de Requisiciones',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => BusinessDate::now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
            'footer_left' => 'ERP MR-Lana',
        ];

        return app(PdfService::class)->download('exports.requisiciones.index', [
            'rows' => $rows,
            'filters' => $filters,
            'meta' => $meta,
        ], 'requisiciones.pdf', ['paper' => 'letter', 'landscape' => true]);
    }

    /**
     * Requisiciones del reporte (mismos filtros, orden y alcance que el
     * listado), cada una con sus items, ajustes y totales.
     *
     * @return list<array<string, mixed>>
     */
    private function buildRows(Request $request): array
    {
        $q = trim((string) $request->query('q', ''));
        $tab = strtoupper((string) $request->query('tab', 'ACTIVAS'));
        $status = (string) $request->query('status', '');
        $corpId = $request->query('comprador_corp_id');
        $sucursalId = $request->query('sucursal_id');
        $solicitanteId = $request->query('solicitante_id');
        $conceptoId = $request->query('concepto_id');
        $proveedorId = $request->query('proveedor_id');
        $registroFrom = $this->safeYmd($request->query('fecha_registro_from') ?? $request->query('fecha_from'));
        $registroTo = $this->safeYmd($request->query('fecha_registro_to') ?? $request->query('fecha_to'));
        $pagoFrom = $this->safeYmd($request->query('fecha_pago_from'));
        $pagoTo = $this->safeYmd($request->query('fecha_pago_to'));
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortRaw = (string) $request->query('sort', 'created_at');
        $sort = $this->normalizeSort($sortRaw);

        $user = $request->user();
        $verTodos = $user->can('requisiciones.ver_todos');

        $query = Requisicion::query()
            ->visibleTo($user)
            ->with([
                'sucursal:id,nombre,codigo,corporativo_id',
                'sucursal.corporativo:id,nombre',
                'solicitante:id,nombre,apellido_paterno,apellido_materno',
                'proveedor:id,razon_social,rfc',
                'concepto:id,nombre',
                'comprador:id,nombre',
                'detalles' => fn ($q) => $q->orderBy('id'),
                'ajustes:id,requisicion_id,tipo,sentido,monto,estatus,motivo,fecha_registro,fecha_aplicacion',
            ])
            ->withSum('pagos', 'monto')
            ->withSum(['comprobantes as comprobado_aprobado' => fn ($q) => $q->where('estatus', 'APROBADO')], 'monto');

        if ($status === 'ELIMINADA' || $tab === 'ELIMINADAS') {
            $query->where('status', 'ELIMINADA');
        } else {
            $query->where('status', '!=', 'ELIMINADA');
        }

        if ($status === '') {
            switch ($tab) {
                case 'BORRADOR':
                    $query->where('status', 'BORRADOR');
                    break;

                case 'CAPTURADAS':
                    $query->whereNotIn('status', ['BORRADOR', 'ELIMINADA']);
                    break;

                case 'ELIMINADAS':
                    $query->where('status', 'ELIMINADA');
                    break;

                case 'ACTIVAS':
                default:
                    // Igual que el listado: sin borradores ajenos para quien ve todas.
                    if ($verTodos) {
                        $query->where(fn ($w) => $w->where('status', '!=', 'BORRADOR')->orWhere('creada_por_user_id', $user->id));
                    }
                    break;
            }
        } else {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('folio', 'like', "%{$q}%")
                    ->orWhere('observaciones', 'like', "%{$q}%")
                    ->orWhereHas('proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$q}%"))
                    ->orWhereHas('concepto', fn ($c) => $c->where('nombre', 'like', "%{$q}%"))
                    ->orWhereHas('comprador', fn ($c) => $c->where('nombre', 'like', "%{$q}%"))
                    ->orWhereHas('sucursal', fn ($s) => $s->where('nombre', 'like', "%{$q}%"));
            });
        }

        if (! empty($corpId)) {
            $query->where('comprador_corp_id', (int) $corpId);
        }
        if (! empty($sucursalId)) {
            $query->where('sucursal_id', (int) $sucursalId);
        }
        if ($verTodos && ! empty($solicitanteId)) {
            $query->where('solicitante_id', (int) $solicitanteId);
        }
        if (! empty($conceptoId)) {
            $query->where('concepto_id', (int) $conceptoId);
        }
        if (! empty($proveedorId)) {
            $query->where('proveedor_id', (int) $proveedorId);
        }
        if ($registroFrom) {
            $query->whereDate('created_at', '>=', $registroFrom);
        }
        if ($registroTo) {
            $query->whereDate('created_at', '<=', $registroTo);
        }
        if ($pagoFrom) {
            $query->whereDate('fecha_pago', '>=', $pagoFrom);
        }
        if ($pagoTo) {
            $query->whereDate('fecha_pago', '<=', $pagoTo);
        }

        $allowed = ['folio', 'created_at', 'monto_total', 'status', 'id'];
        if (! in_array($sort, $allowed, true)) {
            $sort = 'created_at';
        }

        // Misma normalización de "por página" que el listado (predeterminado 20).
        $page = (int) $request->query('page', 0);
        [$perPage, $showAll] = RequisicionController::resolvePerPage($request->query('perPage'));

        $itemsQuery = $query
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc');

        if (! $showAll && $perPage > 0 && $page > 0) {
            $itemsQuery
                ->skip(($page - 1) * $perPage)
                ->take($perPage);
        }

        $requisiciones = $itemsQuery->get();

        return $requisiciones->map(fn (Requisicion $req) => $this->presentRequisicion($req))->all();
    }

    private const AJUSTE_TIPOS = [
        'DEVOLUCION' => 'Devolución',
        'FALTANTE' => 'Faltante',
        'INCREMENTO_AUTORIZADO' => 'Incremento autorizado',
    ];

    private const AJUSTE_ESTATUS = [
        'PENDIENTE' => 'Pendiente de autorizar',
        'APROBADO' => 'Autorizado, sin aplicar',
        'APLICADO' => 'Aplicado',
    ];

    /**
     * Una requisición con sus items (lo que se pidió), los ajustes que
     * modificaron su monto (devoluciones, faltantes, incrementos) y el total
     * efectuado, además de lo pagado y lo comprobado.
     */
    private function presentRequisicion(Requisicion $req): array
    {
        $items = $req->detalles->values()->map(fn ($d, $i) => [
            'n' => $i + 1,
            'item' => trim((string) $d->descripcion),
            'cantidad' => (float) $d->cantidad,
            'precio_unitario' => (float) $d->precio_unitario,
            'genera_iva' => (bool) $d->genera_iva,
            'subtotal' => (float) $d->subtotal,
            'iva' => (float) $d->iva,
            'total' => (float) $d->total,
        ])->all();

        // Rechazados y cancelados no cambian el monto: no se listan.
        $ajustes = $req->ajustes
            ->filter(fn ($a) => isset(self::AJUSTE_ESTATUS[$a->estatus]))
            ->sortBy('id')
            ->values()
            ->map(function ($a) {
                $signo = $a->sentido === 'A_FAVOR_EMPRESA' ? -1 : 1;

                return [
                    'tipo' => self::AJUSTE_TIPOS[$a->tipo] ?? (string) $a->tipo,
                    'motivo' => (string) $a->motivo,
                    'estatus' => self::AJUSTE_ESTATUS[$a->estatus],
                    'aplicado' => $a->estatus === 'APLICADO',
                    'fecha' => optional($a->fecha_aplicacion ?? $a->fecha_registro)->format('Y-m-d'),
                    'monto' => round($signo * (float) $a->monto, 2),
                ];
            })->all();

        $totalItems = round(array_sum(array_column($items, 'total')), 2);
        $ajustesAplicados = round(array_sum(array_map(fn ($a) => $a['aplicado'] ? $a['monto'] : 0, $ajustes)), 2);
        $totalEfectuado = round((float) $req->monto_total, 2);
        $solicitante = $req->solicitante
            ? trim($req->solicitante->nombre.' '.$req->solicitante->apellido_paterno.' '.($req->solicitante->apellido_materno ?? ''))
            : '';

        return [
            'folio' => $req->folio,
            'estatus' => $req->status,
            'fecha_captura' => optional($req->created_at)->format('Y-m-d H:i'),
            'fecha_solicitud' => optional($req->fecha_solicitud)->format('Y-m-d'),
            'fecha_pago_esperada' => optional($req->fecha_pago_esperada)->format('Y-m-d'),
            'fecha_autorizacion' => optional($req->fecha_autorizacion)->format('Y-m-d H:i'),
            'fecha_pago' => optional($req->fecha_pago)->format('Y-m-d'),
            'corporativo' => $req->sucursal?->corporativo?->nombre ?: $req->comprador?->nombre,
            'sucursal' => $req->sucursal?->nombre,
            'sucursal_codigo' => $req->sucursal?->codigo,
            'solicitante' => $solicitante,
            'proveedor' => $req->proveedor?->razon_social,
            'proveedor_rfc' => $req->proveedor?->rfc,
            'concepto' => $req->concepto?->nombre,
            'observaciones' => $req->observaciones,
            'items' => $items,
            'ajustes' => $ajustes,
            'subtotal' => round((float) $req->monto_subtotal, 2),
            'total_items' => $totalItems,
            'ajustes_aplicados' => $ajustesAplicados,
            // Diferencias históricas que no corresponden a un ajuste registrado.
            'otras_diferencias' => round($totalEfectuado - $totalItems - $ajustesAplicados, 2),
            'total_efectuado' => $totalEfectuado,
            'pagado' => round((float) ($req->pagos_sum_monto ?? 0), 2),
            'comprobado' => round((float) ($req->comprobado_aprobado ?? 0), 2),
        ];
    }

    private function presentFilters(Request $request): array
    {
        $sortRaw = (string) $request->query('sort', 'created_at');
        $sort = $this->normalizeSort($sortRaw);

        $sortLabel = match ($sort) {
            'created_at' => 'Fecha de captura',
            'folio' => 'Folio',
            'monto_total' => 'Total',
            'status' => 'Estatus',
            'tipo' => 'Tipo',
            default => 'Fecha de captura',
        };

        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc'
            ? 'Ascendente'
            : 'Descendente';

        $corpId = $request->query('comprador_corp_id');
        $sucursalId = $request->query('sucursal_id');
        $solicitanteId = $request->query('solicitante_id');
        $conceptoId = $request->query('concepto_id');
        $proveedorId = $request->query('proveedor_id');

        $corp = $corpId
            ? (Corporativo::select('id', 'nombre')->find((int) $corpId)?->nombre ?? "#{$corpId}")
            : '';

        $suc = $sucursalId
            ? (Sucursal::select('id', 'nombre')->find((int) $sucursalId)?->nombre ?? "#{$sucursalId}")
            : '';

        $sol = $solicitanteId
            ? Empleado::select('id', 'nombre', 'apellido_paterno', 'apellido_materno')->find((int) $solicitanteId)
            : null;

        $solName = $sol
            ? trim($sol->nombre.' '.$sol->apellido_paterno.' '.($sol->apellido_materno ?? ''))
            : '';

        $con = $conceptoId
            ? (Concepto::select('id', 'nombre')->find((int) $conceptoId)?->nombre ?? "#{$conceptoId}")
            : '';

        $prov = $proveedorId
            ? (Proveedor::select('id', 'razon_social')->find((int) $proveedorId)?->razon_social ?? "#{$proveedorId}")
            : '';

        return array_filter([
            'Búsqueda' => trim((string) $request->query('q', '')),
            'Tab' => strtoupper((string) $request->query('tab', 'ACTIVAS')),
            'Estatus' => (string) $request->query('status', ''),
            'Corporativo' => $corp,
            'Sucursal' => $suc,
            'Solicitante' => $solName,
            'Concepto' => $con,
            'Proveedor' => $prov,
            'Tipo' => (string) $request->query('tipo', ''),
            'Registro desde' => (string) ($request->query('fecha_registro_from') ?? $request->query('fecha_from', '')),
            'Registro hasta' => (string) ($request->query('fecha_registro_to') ?? $request->query('fecha_to', '')),
            'Pago desde' => (string) ($request->query('fecha_pago_from', '')),
            'Pago hasta' => (string) ($request->query('fecha_pago_to', '')),
            'Orden' => $sortLabel,
            'Dirección' => $dir,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function safeYmd($v): ?string
    {
        if (! is_string($v) || $v === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private function normalizeSort(string $sort): string
    {
        $map = [
            'fecha_captura' => 'created_at',
            'createdAt' => 'created_at',
            'created_at' => 'created_at',
            'folio' => 'folio',
            'monto_total' => 'monto_total',
            'status' => 'status',
            'tipo' => 'tipo',
            'id' => 'id',
        ];
        $sort = trim($sort);

        return $map[$sort] ?? 'created_at';
    }
}
