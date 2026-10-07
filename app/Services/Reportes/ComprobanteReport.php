<?php

namespace App\Services\Reportes;

use App\Models\Comprobante;
use App\Models\Requisicion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consulta de comprobantes con filtros, compartida por la pantalla y los
 * reportes PDF/Excel. Respeta el alcance del usuario: sin "ver todas las
 * requisiciones" solo ve comprobantes de sus requisiciones.
 */
class ComprobanteReport
{
    public const ESTATUS = ['PENDIENTE' => 'Pendiente', 'APROBADO' => 'Aprobado', 'RECHAZADO' => 'Rechazado'];

    public const TIPOS = ['FACTURA' => 'Factura', 'TICKET' => 'Ticket', 'NOTA' => 'Nota', 'OTRO' => 'Otro'];

    /** @return array<string, mixed> */
    public function filters(array $in): array
    {
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        $int = fn ($v) => is_numeric($v) && (int) $v > 0 ? (int) $v : null;

        return [
            'q' => trim((string) ($in['q'] ?? '')) ?: null,
            'desde' => $date($in['desde'] ?? null),
            'hasta' => $date($in['hasta'] ?? null),
            'estatus' => array_key_exists($in['estatus'] ?? '', self::ESTATUS) ? $in['estatus'] : null,
            'tipo_doc' => array_key_exists($in['tipo_doc'] ?? '', self::TIPOS) ? $in['tipo_doc'] : null,
            'solicitante_id' => $int($in['solicitante_id'] ?? null),
            'user_carga_id' => $int($in['user_carga_id'] ?? null),
            'user_revision_id' => $int($in['user_revision_id'] ?? null),
            'corporativo_id' => $int($in['corporativo_id'] ?? null),
            'requisicion_id' => $int($in['requisicion_id'] ?? null),
            'per_page' => in_array((int) ($in['per_page'] ?? 0), [12, 24, 48, 96], true) ? (int) $in['per_page'] : 24,
        ];
    }

    public function query(User $user, array $f): Builder
    {
        // Fecha del comprobante: fecha de emisión o, si no se capturó, la de carga.
        $fecha = 'COALESCE(comprobantes.fecha_emision, DATE(comprobantes.created_at))';

        return Comprobante::query()
            ->whereIn('requisicion_id', Requisicion::query()->visibleTo($user)->select('id'))
            ->when($f['requisicion_id'], fn ($q, $id) => $q->where('requisicion_id', $id))
            ->when($f['estatus'], fn ($q, $v) => $q->where('estatus', $v))
            ->when($f['tipo_doc'], fn ($q, $v) => $q->where('tipo_doc', $v))
            ->when($f['user_carga_id'], fn ($q, $v) => $q->where('user_carga_id', $v))
            ->when($f['user_revision_id'], fn ($q, $v) => $q->where('user_revision_id', $v))
            ->when($f['desde'], fn ($q, $d) => $q->whereRaw("{$fecha} >= ?", [$d]))
            ->when($f['hasta'], fn ($q, $d) => $q->whereRaw("{$fecha} <= ?", [$d]))
            ->when($f['solicitante_id'] || $f['corporativo_id'], fn ($q) => $q->whereHas('requisicion', fn ($r) => $r
                ->when($f['solicitante_id'], fn ($w, $id) => $w->where('solicitante_id', $id))
                ->when($f['corporativo_id'], fn ($w, $id) => $w->where('comprador_corp_id', $id))))
            ->when($f['q'], fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('archivo_original', 'like', "%{$term}%")
                ->orWhere('comentario_revision', 'like', "%{$term}%")
                ->orWhereHas('requisicion', fn ($r) => $r->where('folio', 'like', "%{$term}%")
                    ->orWhereHas('proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$term}%")))))
            ->with([
                'requisicion:id,folio,status,monto_total,solicitante_id,proveedor_id,concepto_id,comprador_corp_id',
                'requisicion.solicitante:id,nombre,apellido_paterno',
                'requisicion.proveedor:id,razon_social',
                'requisicion.concepto:id,nombre',
                'requisicion.comprador:id,nombre',
                'userCarga:id,name',
                'userRevision:id,name',
            ])
            ->orderByRaw("{$fecha} desc")
            ->orderByDesc('comprobantes.id');
    }

    /** @return array<string, int|float> */
    public function kpis(Builder $query): array
    {
        $row = (clone $query)->reorder()->setEagerLoads([])->toBase()
            ->selectRaw("COUNT(*) as c, COALESCE(SUM(monto), 0) as m,
                SUM(CASE WHEN estatus = 'PENDIENTE' THEN 1 ELSE 0 END) as pend,
                SUM(CASE WHEN estatus = 'APROBADO' THEN 1 ELSE 0 END) as apr,
                SUM(CASE WHEN estatus = 'RECHAZADO' THEN 1 ELSE 0 END) as rech")
            ->first();

        return [
            'total' => (int) ($row->c ?? 0),
            'monto' => round((float) ($row->m ?? 0), 2),
            'pendientes' => (int) ($row->pend ?? 0),
            'aprobados' => (int) ($row->apr ?? 0),
            'rechazados' => (int) ($row->rech ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public function present(Comprobante $c): array
    {
        $r = $c->requisicion;
        $ext = strtolower(pathinfo((string) ($c->archivo_original ?: $c->archivo_path), PATHINFO_EXTENSION));

        return [
            'id' => $c->id,
            'tipo_doc' => $c->tipo_doc,
            'tipo_label' => self::TIPOS[$c->tipo_doc] ?? $c->tipo_doc,
            'fecha_emision' => optional($c->fecha_emision)->toDateString(),
            'monto' => (float) $c->monto,
            'estatus' => $c->estatus,
            'estatus_label' => self::ESTATUS[$c->estatus] ?? $c->estatus,
            'comentario_revision' => $c->comentario_revision,
            'revisado_at' => optional($c->revisado_at)->toISOString(),
            'archivo_original' => $c->archivo_original ?: basename((string) $c->archivo_path),
            'kind' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? 'image' : ($ext === 'pdf' ? 'pdf' : ($c->archivo_path ? 'file' : 'none')),
            'ext' => $ext,
            'preview_url' => $c->archivo_path ? route('comprobantes.archivo', $c) : null,
            'download_url' => $c->archivo_path ? route('comprobantes.archivo', [$c, 'descargar' => 1]) : null,
            'user_carga' => $c->userCarga?->name,
            'user_revision' => $c->userRevision?->name,
            'created_at' => optional($c->created_at)->toISOString(),
            'requisicion' => $r ? [
                'id' => $r->id,
                'folio' => $r->folio,
                'status' => $r->status,
                'monto_total' => (float) $r->monto_total,
                'solicitante' => $r->solicitante ? trim($r->solicitante->nombre.' '.$r->solicitante->apellido_paterno) : null,
                'proveedor' => $r->proveedor?->razon_social,
                'concepto' => $r->concepto?->nombre,
                'corporativo' => $r->comprador?->nombre,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function options(User $user): array
    {
        $reqIds = Requisicion::query()->visibleTo($user)->select('id');

        return [
            'estatus' => collect(self::ESTATUS)->map(fn ($nombre, $id) => compact('id', 'nombre'))->values(),
            'tipos' => collect(self::TIPOS)->map(fn ($nombre, $id) => compact('id', 'nombre'))->values(),
            'solicitantes' => DB::table('empleados')->whereIn('id', Requisicion::query()->visibleTo($user)->select('solicitante_id'))
                ->orderBy('nombre')->get(['id', DB::raw(self::fullName().' as nombre')]),
            'cargaron' => User::query()->whereIn('id', Comprobante::query()->whereIn('requisicion_id', $reqIds)->select('user_carga_id'))->orderBy('name')->get(['id', 'name as nombre']),
            'revisaron' => User::query()->whereIn('id', Comprobante::query()->whereIn('requisicion_id', $reqIds)->whereNotNull('user_revision_id')->select('user_revision_id'))->orderBy('name')->get(['id', 'name as nombre']),
            'corporativos' => DB::table('corporativos')->where('activo', 1)->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    /** Etiquetas legibles de los filtros activos (encabezado de reportes). */
    public function filterLabels(array $f): array
    {
        $name = fn (string $table, ?int $id, string $col = 'name') => $id ? DB::table($table)->where('id', $id)->value($col) : null;

        return array_filter([
            'Búsqueda' => $f['q'],
            'Desde' => $f['desde'],
            'Hasta' => $f['hasta'],
            'Estatus' => $f['estatus'] ? self::ESTATUS[$f['estatus']] : null,
            'Tipo' => $f['tipo_doc'] ? self::TIPOS[$f['tipo_doc']] : null,
            'Solicitante' => $f['solicitante_id'] ? DB::table('empleados')->where('id', $f['solicitante_id'])->selectRaw(self::fullName().' as n')->value('n') : null,
            'Cargó' => $name('users', $f['user_carga_id']),
            'Revisó' => $name('users', $f['user_revision_id']),
            'Corporativo' => $name('corporativos', $f['corporativo_id'], 'nombre'),
        ]);
    }

    /** Nombre completo del colaborador (compatible con MySQL y SQLite). */
    private static function fullName(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "TRIM(nombre || ' ' || apellido_paterno)"
            : "TRIM(CONCAT(nombre, ' ', apellido_paterno))";
    }
}
