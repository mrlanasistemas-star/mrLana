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

/**
 * Controlador de exportación para requisiciones.
 *
 * Esta clase conserva la lógica de filtrado y generación de filas que
 * permite agrupar los detalles de cada requisición, mostrar los campos
 * solicitados (incluyendo corporativo, IVA, fechas y totales) y
 * descargar el reporte en PDF o Excel.
 */
class RequisicionExportController
{
    /**
     * Exporta las requisiciones a un archivo de Excel.
     */
    public function excel(Request $request)
    {
        $rows    = $this->buildRows($request);
        $filters = $this->presentFilters($request);
        // Construimos meta para pasar información contextual al exportador
        $meta = [
            'title'        => 'Reporte de Requisiciones',
            'subtitle'     => 'Exportación con filtros actuales',
            'generated_at' => now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
            'footer_left'  => 'ERP MR-Lana',
        ];
        return Excel::download(new RequisicionesExport($rows, $filters, $meta), 'requisiciones.xlsx');
    }

    /**
     * Exporta las requisiciones a un archivo PDF.
     */
    public function pdf(Request $request)
    {
        $rows    = $this->buildRows($request);
        $filters = $this->presentFilters($request);
        // Meta para la vista PDF (título, subtítulo, fechas)
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
     * Construye las filas del reporte tomando en cuenta filtros y
     * agrupando los detalles por requisición.  En la primera línea de
     * cada requisición se rellenan los campos comunes; en las
     * siguientes solo se muestran los campos específicos del detalle.
     *
     * @param Request $request
     * @return array<int, array<string,mixed>>
     */
    private function buildRows(Request $request): array
    {
        // Extracción de filtros de la consulta
        $q            = trim((string) $request->query('q', ''));
        $tab          = strtoupper((string) $request->query('tab', 'ACTIVAS'));
        $status       = (string) $request->query('status', '');
        $corpId       = $request->query('comprador_corp_id');
        $sucursalId   = $request->query('sucursal_id');
        $solicitanteId= $request->query('solicitante_id');
        $conceptoId   = $request->query('concepto_id');
        $proveedorId  = $request->query('proveedor_id');
        $tipo         = (string) $request->query('tipo', '');
        $from         = $this->safeYmd($request->query('fecha_from'));
        $to           = $this->safeYmd($request->query('fecha_to'));
        $dir          = strtolower((string)$request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortRaw      = (string) $request->query('sort', 'created_at');
        $sort         = $this->normalizeSort($sortRaw);

        // Consulta con relaciones necesarias, incluido corporativo a través de sucursal
        $query = Requisicion::query()
            ->with([
                'sucursal:id,nombre,codigo,corporativo_id',
                'sucursal.corporativo:id,nombre',
                'solicitante:id,nombre,apellido_paterno,apellido_materno',
                'proveedor:id,razon_social,rfc',
                'concepto:id,nombre',
                'comprador:id,nombre',
                // Nota: no hay relación createdBy definida en el modelo, por lo que se omite
                'detalles',
            ]);

        // Filtrado por estatus y tab
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
                    break;
            }
        } else {
            $query->where('status', $status);
        }

        // Búsqueda global por folio, observaciones y relaciones
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

        // Filtros opcionales
        if (!empty($corpId))        $query->where('comprador_corp_id', (int)$corpId);
        if (!empty($sucursalId))    $query->where('sucursal_id', (int)$sucursalId);
        if (!empty($solicitanteId)) $query->where('solicitante_id', (int)$solicitanteId);
        if (!empty($conceptoId))    $query->where('concepto_id', (int)$conceptoId);
        if (!empty($proveedorId))   $query->where('proveedor_id', (int)$proveedorId);
        if ($tipo !== '')           $query->where('tipo', $tipo);
        if ($from)                  $query->whereDate('created_at', '>=', $from);
        if ($to)                    $query->whereDate('created_at', '<=', $to);

        // Sort seguro
        $allowed = ['folio','created_at','monto_total','status','tipo','id'];
        if (!in_array($sort, $allowed, true)) $sort = 'created_at';

        $items = $query->orderBy($sort, $dir)->orderBy('id','desc')->get();
        $rows  = [];
        foreach ($items as $req) {
            $isFirstItem = true;
            foreach ($req->detalles as $detalle) {
                // Calcula IVA de requisición una sola vez
                $ivaReq = (float)($req->monto_total ?? 0) - (float)($req->monto_subtotal ?? 0);
                $rows[] = [
                    // Campos comunes a la requisición
                    'folio'         => $isFirstItem ? $req->folio : '',
                    'fecha_captura' => $isFirstItem ? optional($req->created_at)->format('Y-m-d H:i') : '',
                    'tipo'          => $isFirstItem ? $req->tipo : '',
                    'estatus'       => $isFirstItem ? $req->status : '',
                    'comprador'     => $isFirstItem ? $req->comprador?->nombre : '',
                    'corporativo'   => $isFirstItem ? $req->sucursal?->corporativo?->nombre : '',
                    'sucursal'      => $isFirstItem ? $req->sucursal?->nombre : '',
                    'solicitante'   => $isFirstItem ? ($req->solicitante
                        ? trim($req->solicitante->nombre.' '.$req->solicitante->apellido_paterno.' '.($req->solicitante->apellido_materno ?? ''))
                        : '') : '',
                    'proveedor'     => $isFirstItem ? $req->proveedor?->razon_social : '',
                    'concepto'      => $isFirstItem ? $req->concepto?->nombre : '',
                    'fecha_pago'    => $isFirstItem ? ($req->fecha_pago ? optional($req->fecha_pago)->format('Y-m-d') : '') : '',

                    // Datos del detalle (siempre visibles)
                    'cantidad'        => (float) $detalle->cantidad,
                    'descripcion_item'=> $detalle->descripcion,
                    'precio_unitario' => (float) $detalle->precio_unitario,
                    'genera_iva'      => $detalle->genera_iva ? 'Sí' : 'No',
                    'subtotal_item'   => (float) $detalle->subtotal,
                    'iva_item'        => (float) $detalle->iva,
                    'total_item'      => (float) $detalle->total,

                    // Totales de la requisición
                    'subtotal'        => $isFirstItem ? (float)($req->monto_subtotal ?? 0) : '',
                    'iva'            => $isFirstItem ? $ivaReq : '',
                    'total'           => $isFirstItem ? (float)($req->monto_total    ?? 0) : '',

                    // Campo creador: eliminado porque el modelo no tiene relación creada
                    // 'created_by'   => $isFirstItem ? optional($req->createdBy)->nombre : '',
                ];
                $isFirstItem = false;
            }
        }

        return $rows;
    }

    /**
     * Genera un array con etiquetas legibles de los filtros aplicados.
     *
     * @param Request $request
     * @return array<string,string>
     */
    private function presentFilters(Request $request): array
    {
        $sortRaw = (string)$request->query('sort', 'created_at');
        $sort    = $this->normalizeSort($sortRaw);
        $sortLabel = match ($sort) {
            'created_at'  => 'Fecha de captura',
            'folio'       => 'Folio',
            'monto_total' => 'Total',
            'status'      => 'Estatus',
            'tipo'        => 'Tipo',
            default       => 'Fecha de captura',
        };
        $dir       = strtolower((string)$request->query('dir', 'desc')) === 'asc' ? 'Ascendente' : 'Descendente';
        $corpId    = $request->query('comprador_corp_id');
        $sucursalId= $request->query('sucursal_id');
        $solicitanteId = $request->query('solicitante_id');
        $conceptoId = $request->query('concepto_id');
        $proveedorId = $request->query('proveedor_id');

        $corp = $corpId ? (Corporativo::select('id','nombre')->find((int)$corpId)?->nombre ?? "#{$corpId}") : '';
        $suc  = $sucursalId ? (Sucursal::select('id','nombre')->find((int)$sucursalId)?->nombre ?? "#{$sucursalId}") : '';
        $sol  = $solicitanteId
            ? Empleado::select('id','nombre','apellido_paterno','apellido_materno')->find((int)$solicitanteId)
            : null;
        $solName = $sol ? trim($sol->nombre . ' ' . $sol->apellido_paterno . ' ' . ($sol->apellido_materno ?? '')) : '';
        $con  = $conceptoId ? (Concepto::select('id','nombre')->find((int)$conceptoId)?->nombre ?? "#{$conceptoId}") : '';
        $prov = $proveedorId ? (Proveedor::select('id','razon_social')->find((int)$proveedorId)?->razon_social ?? "#{$proveedorId}") : '';

        return array_filter([
            'Búsqueda'    => trim((string)$request->query('q', '')),
            'Tab'         => strtoupper((string)$request->query('tab', 'ACTIVAS')),
            'Estatus'     => (string)$request->query('status', ''),
            'Corporativo' => $corp,
            'Sucursal'    => $suc,
            'Solicitante' => $solName,
            'Concepto'    => $con,
            'Proveedor'   => $prov,
            'Tipo'        => (string)$request->query('tipo', ''),
            'Captura desde' => (string)($request->query('fecha_from', '')),
            'Captura hasta' => (string)($request->query('fecha_to', '')),
            'Orden'       => $sortLabel,
            'Dirección'   => $dir,
        ], fn($v) => $v !== null && $v !== '');
    }

    /**
     * Valida que la fecha tenga formato Y-m-d y la devuelve, o null en caso contrario.
     */
    private function safeYmd($v): ?string
    {
        if (!is_string($v) || $v === '') return null;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    /**
     * Normaliza los campos de ordenamiento a valores conocidos del modelo.
     */
    private function normalizeSort(string $sort): string
    {
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
