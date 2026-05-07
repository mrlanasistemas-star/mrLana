<?php

namespace App\Http\Controllers\Exports;

use App\Exports\Requisiciones\RequisicionesExport;
use App\Models\Concepto;
use App\Models\Corporativo;
use App\Models\Empleado;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Sucursal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RequisicionExportController {

    public function excel(Request $request) {
        $rows    = $this->buildRows($request);
        $filters = $this->presentFilters($request);

        $meta = [
            'title'        => 'Reporte de Requisiciones',
            'subtitle'     => 'Exportación con filtros actuales',
            'generated_at' => now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
            'footer_left'  => 'ERP MR-Lana',
        ];

        return Excel::download(
            new RequisicionesExport($rows, $filters, $meta),
            'requisiciones.xlsx'
        );
    }

    public function pdf(Request $request) {
        $rows    = $this->buildRows($request);
        $filters = $this->presentFilters($request);

        $meta = [
            'title'        => 'Reporte de Requisiciones',
            'subtitle'     => 'Exportación con filtros actuales',
            'generated_at' => now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
            'footer_left'  => 'ERP MR-Lana',
        ];

        $pdf = Pdf::loadView('exports.requisiciones.index', [
            'rows'    => $rows,
            'filters' => $filters,
            'meta'    => $meta,
        ])->setPaper('letter', 'landscape');

        return $pdf->download('requisiciones.pdf');
    }

    /**
     * Construye filas para exportación.
     *
     * Reglas:
     * - Sale una fila por cada detalle/item.
     * - Si la requisición tiene diferencia neta entre total items y total final,
     *   se agrega UNA sola fila adicional con descripción "AJUSTE".
     * - No se agregan ajustes uno por uno.
     */
    private function buildRows(Request $request): array {
        $q             = trim((string) $request->query('q', ''));
        $tab           = strtoupper((string) $request->query('tab', 'ACTIVAS'));
        $status        = (string) $request->query('status', '');
        $corpId        = $request->query('comprador_corp_id');
        $sucursalId    = $request->query('sucursal_id');
        $solicitanteId = $request->query('solicitante_id');
        $conceptoId    = $request->query('concepto_id');
        $proveedorId   = $request->query('proveedor_id');
        $tipo          = (string) $request->query('tipo', '');
        $from          = $this->safeYmd($request->query('fecha_from'));
        $to            = $this->safeYmd($request->query('fecha_to'));
        $dir           = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortRaw       = (string) $request->query('sort', 'created_at');
        $sort          = $this->normalizeSort($sortRaw);

        $user = $request->user();
        $rol  = strtoupper((string)($user->rol ?? 'COLABORADOR'));

        $query = Requisicion::query()
            ->with([
                'sucursal:id,nombre,codigo,corporativo_id',
                'sucursal.corporativo:id,nombre',
                'solicitante:id,nombre,apellido_paterno,apellido_materno',
                'proveedor:id,razon_social,rfc',
                'concepto:id,nombre',
                'comprador:id,nombre',
                'detalles',
            ]);

        if ($rol === 'COLABORADOR') {
            if ($user && $user->empleado_id) {
                $query->where('solicitante_id', (int) $user->empleado_id);
            } else {
                $query->whereRaw('1=0');
            }
        }

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
                    if ($rol !== 'COLABORADOR') {
                        $query->whereNotIn('status', ['BORRADOR', 'ELIMINADA']);
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
                    ->orWhereHas('proveedor', fn($p) => $p->where('razon_social', 'like', "%{$q}%"))
                    ->orWhereHas('concepto', fn($c) => $c->where('nombre', 'like', "%{$q}%"))
                    ->orWhereHas('comprador', fn($c) => $c->where('nombre', 'like', "%{$q}%"))
                    ->orWhereHas('sucursal', fn($s) => $s->where('nombre', 'like', "%{$q}%"));
            });
        }

        if (!empty($corpId))        $query->where('comprador_corp_id', (int) $corpId);
        if (!empty($sucursalId))    $query->where('sucursal_id', (int) $sucursalId);
        if ($rol !== 'COLABORADOR' && !empty($solicitanteId)) {
            $query->where('solicitante_id', (int) $solicitanteId);
        }
        if (!empty($conceptoId))    $query->where('concepto_id', (int) $conceptoId);
        if (!empty($proveedorId))   $query->where('proveedor_id', (int) $proveedorId);
        if ($tipo !== '')           $query->where('tipo', $tipo);
        if ($from)                  $query->whereDate('created_at', '>=', $from);
        if ($to)                    $query->whereDate('created_at', '<=', $to);

        $allowed = ['folio', 'created_at', 'monto_total', 'status', 'tipo', 'id'];
        if (!in_array($sort, $allowed, true)) {
            $sort = 'created_at';
        }

        $perPage = (int) $request->query('perPage', 0);
        $page    = (int) $request->query('page', 0);

        $itemsQuery = $query
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc');

        if ($perPage > 0 && $page > 0) {
            $itemsQuery
                ->skip(($page - 1) * $perPage)
                ->take($perPage);
        }

        $items = $itemsQuery->get();

        $rows = [];

        foreach ($items as $req) {
            $detalles = collect($req->detalles ?? []);

            $totalItems = (float) $detalles->sum(function ($d) {
                return (float) ($d->total ?? 0);
            });

            $subtotalReq = (float) ($req->monto_subtotal ?? 0);
            $totalReq    = (float) ($req->monto_total ?? 0);
            $ivaReq      = round($totalReq - $subtotalReq, 2);

            // Diferencia neta real entre total de items y total final de la requisición
            $ajusteNeto = round($totalReq - $totalItems, 2);

            $common = [
                'folio'           => $req->folio,
                'fecha_captura'   => optional($req->created_at)->format('Y-m-d H:i'),
                'fecha_solicitud' => $req->fecha_solicitud ? optional($req->fecha_solicitud)->format('Y-m-d') : '',
                'fecha_pago'      => $req->fecha_pago ? optional($req->fecha_pago)->format('Y-m-d') : '',
                'tipo'            => $req->tipo,
                'estatus'         => $req->status,

                'comprador'       => $req->comprador?->nombre,
                'corporativo'     => $req->sucursal?->corporativo?->nombre ?: $req->comprador?->nombre,
                'sucursal'        => $req->sucursal?->nombre,
                'sucursal_codigo' => $req->sucursal?->codigo,

                'solicitante'     => $req->solicitante
                    ? trim($req->solicitante->nombre . ' ' . $req->solicitante->apellido_paterno . ' ' . ($req->solicitante->apellido_materno ?? ''))
                    : '',

                'proveedor'       => $req->proveedor?->razon_social,
                'proveedor_rfc'   => $req->proveedor?->rfc,

                'concepto'        => $req->concepto?->nombre,
                'observaciones'   => $req->observaciones,

                'subtotal'        => $subtotalReq,
                'iva'             => $ivaReq,
                'total'           => $totalItems,
                'ajustes_netos'   => $ajusteNeto,
                'total_final'     => $totalReq,
            ];

            $printedHeader = false;

            foreach ($detalles as $detalle) {
                $rows[] = [
                    'folio'             => !$printedHeader ? $common['folio'] : '',
                    'fecha_captura'     => !$printedHeader ? $common['fecha_captura'] : '',
                    'fecha_solicitud'   => !$printedHeader ? $common['fecha_solicitud'] : '',
                    'fecha_pago'        => !$printedHeader ? $common['fecha_pago'] : '',
                    'tipo'              => !$printedHeader ? $common['tipo'] : '',
                    'estatus'           => !$printedHeader ? $common['estatus'] : '',
                    'comprador'         => !$printedHeader ? $common['comprador'] : '',
                    'corporativo'       => !$printedHeader ? $common['corporativo'] : '',
                    'sucursal'          => !$printedHeader ? $common['sucursal'] : '',
                    'sucursal_codigo'   => !$printedHeader ? $common['sucursal_codigo'] : '',
                    'solicitante'       => !$printedHeader ? $common['solicitante'] : '',
                    'proveedor'         => !$printedHeader ? $common['proveedor'] : '',
                    'proveedor_rfc'     => !$printedHeader ? $common['proveedor_rfc'] : '',
                    'concepto'          => !$printedHeader ? $common['concepto'] : '',
                    'observaciones'     => !$printedHeader ? $common['observaciones'] : '',

                    'cantidad'          => (float) ($detalle->cantidad ?? 0),
                    'descripcion_item'  => (string) ($detalle->descripcion ?? ''),
                    'precio_unitario'   => (float) ($detalle->precio_unitario ?? 0),
                    'genera_iva'        => !empty($detalle->genera_iva) ? 'Sí' : 'No',
                    'subtotal_item'     => (float) ($detalle->subtotal ?? 0),
                    'iva_item'          => (float) ($detalle->iva ?? 0),
                    'total_item'        => (float) ($detalle->total ?? 0),

                    'subtotal'          => !$printedHeader ? $common['subtotal'] : '',
                    'iva'               => !$printedHeader ? $common['iva'] : '',
                    'total'             => !$printedHeader ? $common['total'] : '',
                    'ajustes_netos'     => !$printedHeader ? $common['ajustes_netos'] : '',
                    'total_final'       => !$printedHeader ? $common['total_final'] : '',
                    'row_kind'          => 'ITEM',
                ];

                $printedHeader = true;
            }

            // Si no hubo detalles, igual metemos una fila base vacía
            if (!$printedHeader) {
                $rows[] = [
                    'folio'             => $common['folio'],
                    'fecha_captura'     => $common['fecha_captura'],
                    'fecha_solicitud'   => $common['fecha_solicitud'],
                    'fecha_pago'        => $common['fecha_pago'],
                    'tipo'              => $common['tipo'],
                    'estatus'           => $common['estatus'],
                    'comprador'         => $common['comprador'],
                    'corporativo'       => $common['corporativo'],
                    'sucursal'          => $common['sucursal'],
                    'sucursal_codigo'   => $common['sucursal_codigo'],
                    'solicitante'       => $common['solicitante'],
                    'proveedor'         => $common['proveedor'],
                    'proveedor_rfc'     => $common['proveedor_rfc'],
                    'concepto'          => $common['concepto'],
                    'observaciones'     => $common['observaciones'],

                    'cantidad'          => '',
                    'descripcion_item'  => '',
                    'precio_unitario'   => '',
                    'genera_iva'        => '',
                    'subtotal_item'     => '',
                    'iva_item'          => '',
                    'total_item'        => '',

                    'subtotal'          => $common['subtotal'],
                    'iva'               => $common['iva'],
                    'total'             => $common['total'],
                    'ajustes_netos'     => $common['ajustes_netos'],
                    'total_final'       => $common['total_final'],
                    'row_kind'          => 'BASE',
                ];

                $printedHeader = true;
            }

            // Agrega SOLO una fila general de ajuste, si la diferencia neta existe
            if (abs($ajusteNeto) > 0.00001) {
                $rows[] = [
                    'folio'             => '',
                    'fecha_captura'     => '',
                    'fecha_solicitud'   => '',
                    'fecha_pago'        => '',
                    'tipo'              => '',
                    'estatus'           => '',
                    'comprador'         => '',
                    'corporativo'       => '',
                    'sucursal'          => '',
                    'sucursal_codigo'   => '',
                    'solicitante'       => '',
                    'proveedor'         => '',
                    'proveedor_rfc'     => '',
                    'concepto'          => '',
                    'observaciones'     => '',

                    'cantidad'          => 1,
                    'descripcion_item'  => 'AJUSTE',
                    'precio_unitario'   => $ajusteNeto,
                    'genera_iva'        => 'No',
                    'subtotal_item'     => $ajusteNeto,
                    'iva_item'          => 0,
                    'total_item'        => $ajusteNeto,

                    'subtotal'          => '',
                    'iva'               => '',
                    'total'             => '',
                    'ajustes_netos'     => '',
                    'total_final'       => '',
                    'row_kind'          => 'AJUSTE',
                ];
            }
        }

        return $rows;
    }

    private function presentFilters(Request $request): array {
        $sortRaw = (string) $request->query('sort', 'created_at');
        $sort    = $this->normalizeSort($sortRaw);

        $sortLabel = match ($sort) {
            'created_at'  => 'Fecha de captura',
            'folio'       => 'Folio',
            'monto_total' => 'Total',
            'status'      => 'Estatus',
            'tipo'        => 'Tipo',
            default       => 'Fecha de captura',
        };

        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc'
            ? 'Ascendente'
            : 'Descendente';

        $corpId         = $request->query('comprador_corp_id');
        $sucursalId     = $request->query('sucursal_id');
        $solicitanteId  = $request->query('solicitante_id');
        $conceptoId     = $request->query('concepto_id');
        $proveedorId    = $request->query('proveedor_id');

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
            ? trim($sol->nombre . ' ' . $sol->apellido_paterno . ' ' . ($sol->apellido_materno ?? ''))
            : '';

        $con = $conceptoId
            ? (Concepto::select('id', 'nombre')->find((int) $conceptoId)?->nombre ?? "#{$conceptoId}")
            : '';

        $prov = $proveedorId
            ? (Proveedor::select('id', 'razon_social')->find((int) $proveedorId)?->razon_social ?? "#{$proveedorId}")
            : '';

        return array_filter([
            'Búsqueda'      => trim((string) $request->query('q', '')),
            'Tab'           => strtoupper((string) $request->query('tab', 'ACTIVAS')),
            'Estatus'       => (string) $request->query('status', ''),
            'Corporativo'   => $corp,
            'Sucursal'      => $suc,
            'Solicitante'   => $solName,
            'Concepto'      => $con,
            'Proveedor'     => $prov,
            'Tipo'          => (string) $request->query('tipo', ''),
            'Captura desde' => (string) ($request->query('fecha_from', '')),
            'Captura hasta' => (string) ($request->query('fecha_to', '')),
            'Orden'         => $sortLabel,
            'Dirección'     => $dir,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function safeYmd($v): ?string {
        if (!is_string($v) || $v === '') return null;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private function normalizeSort(string $sort): string {
        $map = [
            'fecha_captura' => 'created_at',
            'createdAt'     => 'created_at',
            'created_at'    => 'created_at',
            'folio'         => 'folio',
            'monto_total'   => 'monto_total',
            'status'        => 'status',
            'tipo'          => 'tipo',
            'id'            => 'id',
        ];
        $sort = trim($sort);
        return $map[$sort] ?? 'created_at';
    }

}
