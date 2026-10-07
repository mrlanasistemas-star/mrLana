<?php

namespace App\Services\Reportes;

use App\Models\Pago;
use App\Models\Requisicion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consulta de pagos con filtros, compartida por la pantalla y los reportes
 * PDF/Excel. Respeta el alcance del usuario (requisiciones visibles).
 */
class PagoReport
{
    public const TIPOS = ['TRANSFERENCIA' => 'Transferencia', 'EFECTIVO' => 'Efectivo', 'TARJETA' => 'Tarjeta', 'CHEQUE' => 'Cheque', 'OTRO' => 'Otro'];

    /** @return array<string, mixed> */
    public function filters(array $in): array
    {
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        $int = fn ($v) => is_numeric($v) && (int) $v > 0 ? (int) $v : null;

        return [
            'q' => trim((string) ($in['q'] ?? '')) ?: null,
            'desde' => $date($in['desde'] ?? null),
            'hasta' => $date($in['hasta'] ?? null),
            'tipo_pago' => array_key_exists($in['tipo_pago'] ?? '', self::TIPOS) ? $in['tipo_pago'] : null,
            'solicitante_id' => $int($in['solicitante_id'] ?? null),
            'user_carga_id' => $int($in['user_carga_id'] ?? null),
            'autorizo_id' => $int($in['autorizo_id'] ?? null),
            'corporativo_id' => $int($in['corporativo_id'] ?? null),
            'requisicion_id' => $int($in['requisicion_id'] ?? null),
            'per_page' => in_array((int) ($in['per_page'] ?? 0), [12, 24, 48, 96], true) ? (int) $in['per_page'] : 24,
        ];
    }

    public function query(User $user, array $f): Builder
    {
        return Pago::query()
            ->whereIn('requisicion_id', Requisicion::query()->visibleTo($user)->select('id'))
            ->when($f['requisicion_id'], fn ($q, $id) => $q->where('requisicion_id', $id))
            ->when($f['tipo_pago'], fn ($q, $v) => $q->where('tipo_pago', $v))
            ->when($f['user_carga_id'], fn ($q, $v) => $q->where('user_carga_id', $v))
            ->when($f['desde'], fn ($q, $d) => $q->whereDate('fecha_pago', '>=', $d))
            ->when($f['hasta'], fn ($q, $d) => $q->whereDate('fecha_pago', '<=', $d))
            ->when($f['solicitante_id'] || $f['corporativo_id'] || $f['autorizo_id'], fn ($q) => $q->whereHas('requisicion', fn ($r) => $r
                ->when($f['solicitante_id'], fn ($w, $id) => $w->where('solicitante_id', $id))
                ->when($f['corporativo_id'], fn ($w, $id) => $w->where('comprador_corp_id', $id))
                ->when($f['autorizo_id'], fn ($w, $id) => $w->where('pago_autorizado_por_id', $id))))
            ->when($f['q'], fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('beneficiario_nombre', 'like', "%{$term}%")
                ->orWhere('referencia', 'like', "%{$term}%")
                ->orWhere('archivo_original', 'like', "%{$term}%")
                ->orWhereHas('requisicion', fn ($r) => $r->where('folio', 'like', "%{$term}%"))))
            ->with([
                'requisicion:id,folio,status,monto_total,solicitante_id,concepto_id,comprador_corp_id,fecha_autorizacion,pago_autorizado_por_id',
                'requisicion.solicitante:id,nombre,apellido_paterno',
                'requisicion.concepto:id,nombre',
                'requisicion.comprador:id,nombre',
                'requisicion.pagoAutorizadoPor:id,name',
                'userCarga:id,name',
            ])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('pagos.id');
    }

    /** @return array<string, int|float> */
    public function kpis(Builder $query): array
    {
        $base = (clone $query)->reorder()->setEagerLoads([])->toBase();
        $row = (clone $base)->selectRaw('COUNT(*) as c, COALESCE(SUM(monto), 0) as m, COUNT(DISTINCT requisicion_id) as r')->first();
        $porTipo = (clone $base)->selectRaw('tipo_pago, COALESCE(SUM(monto), 0) as m')->groupBy('tipo_pago')->pluck('m', 'tipo_pago');

        return [
            'total' => (int) ($row->c ?? 0),
            'monto' => round((float) ($row->m ?? 0), 2),
            'requisiciones' => (int) ($row->r ?? 0),
            'transferencias' => round((float) ($porTipo['TRANSFERENCIA'] ?? 0), 2),
            'otros' => round((float) $porTipo->except('TRANSFERENCIA')->sum(), 2),
        ];
    }

    /** @return array<string, mixed> */
    public function present(Pago $p): array
    {
        $r = $p->requisicion;
        $ext = strtolower(pathinfo((string) ($p->archivo_original ?: $p->archivo_path), PATHINFO_EXTENSION));
        $mask = fn (?string $v) => $v ? '•••• '.substr(preg_replace('/\D/', '', $v), -4) : null;

        return [
            'id' => $p->id,
            'beneficiario' => $p->beneficiario_nombre,
            'banco' => $p->banco,
            'cuenta' => $mask($p->clabe ?: $p->cuenta),
            'tipo_pago' => $p->tipo_pago,
            'tipo_label' => self::TIPOS[$p->tipo_pago] ?? $p->tipo_pago,
            'monto' => (float) $p->monto,
            'fecha_pago' => optional($p->fecha_pago)->toDateString(),
            'referencia' => $p->referencia,
            'archivo_original' => $p->archivo_original ?: ($p->archivo_path ? basename($p->archivo_path) : null),
            'kind' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? 'image' : ($ext === 'pdf' ? 'pdf' : ($p->archivo_path ? 'file' : 'none')),
            'ext' => $ext,
            'preview_url' => $p->archivo_path ? route('pagos.archivo', $p) : null,
            'download_url' => $p->archivo_path ? route('pagos.archivo', [$p, 'descargar' => 1]) : null,
            'user_carga' => $p->userCarga?->name,
            'created_at' => optional($p->created_at)->toISOString(),
            'requisicion' => $r ? [
                'id' => $r->id,
                'folio' => $r->folio,
                'status' => $r->status,
                'monto_total' => (float) $r->monto_total,
                'solicitante' => $r->solicitante ? trim($r->solicitante->nombre.' '.$r->solicitante->apellido_paterno) : null,
                'concepto' => $r->concepto?->nombre,
                'corporativo' => $r->comprador?->nombre,
                'autorizo' => $r->pagoAutorizadoPor?->name,
                'fecha_autorizacion' => optional($r->fecha_autorizacion)->toISOString(),
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function options(User $user): array
    {
        $reqs = Requisicion::query()->visibleTo($user);

        return [
            'tipos' => collect(self::TIPOS)->map(fn ($nombre, $id) => compact('id', 'nombre'))->values(),
            'solicitantes' => DB::table('empleados')->whereIn('id', (clone $reqs)->select('solicitante_id'))
                ->orderBy('nombre')->get(['id', DB::raw(self::fullName().' as nombre')]),
            'registraron' => User::query()->whereIn('id', Pago::query()->whereIn('requisicion_id', (clone $reqs)->select('id'))->select('user_carga_id'))->orderBy('name')->get(['id', 'name as nombre']),
            'autorizaron' => User::query()->whereIn('id', (clone $reqs)->whereNotNull('pago_autorizado_por_id')->select('pago_autorizado_por_id'))->orderBy('name')->get(['id', 'name as nombre']),
            'corporativos' => DB::table('corporativos')->where('activo', 1)->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    public function filterLabels(array $f): array
    {
        $name = fn (string $table, ?int $id, string $col = 'name') => $id ? DB::table($table)->where('id', $id)->value($col) : null;

        return array_filter([
            'Búsqueda' => $f['q'],
            'Desde' => $f['desde'],
            'Hasta' => $f['hasta'],
            'Tipo de pago' => $f['tipo_pago'] ? self::TIPOS[$f['tipo_pago']] : null,
            'Solicitante' => $f['solicitante_id'] ? DB::table('empleados')->where('id', $f['solicitante_id'])->selectRaw(self::fullName().' as n')->value('n') : null,
            'Registró' => $name('users', $f['user_carga_id']),
            'Autorizó' => $name('users', $f['autorizo_id']),
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
