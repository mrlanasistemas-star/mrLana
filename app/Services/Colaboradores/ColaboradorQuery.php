<?php

namespace App\Services\Colaboradores;

use App\Models\Empleado;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de Colaboradores, compartidos por la pantalla y las
 * exportaciones para que siempre muestren exactamente los mismos registros.
 */
final class ColaboradorQuery
{
    public const ACCESOS = ['all', 'con', 'sin'];

    public const ESTADOS = ['all', '1', '0'];

    /** @return array{q: string, corporativo_id: ?int, sucursal_id: ?int, area_id: ?int, activo: string, acceso: string} */
    public static function filters(Request $request): array
    {
        $int = fn (string $key) => ($v = $request->input($key)) === null || $v === '' ? null : (int) $v;

        $activo = (string) $request->input('activo', 'all');
        $acceso = (string) $request->input('acceso', 'all');

        return [
            'q' => trim((string) $request->input('q', '')),
            'corporativo_id' => $int('corporativo_id'),
            'sucursal_id' => $int('sucursal_id'),
            'area_id' => $int('area_id'),
            'activo' => in_array($activo, self::ESTADOS, true) ? $activo : 'all',
            'acceso' => in_array($acceso, self::ACCESOS, true) ? $acceso : 'all',
        ];
    }

    /**
     * Siempre limitado al alcance del usuario (mi registro, mi sucursal, mi
     * corporativo o todos): búsquedas, filtros, contadores y exportaciones
     * parten de esta misma consulta.
     *
     * @param  array{q: string, corporativo_id: ?int, sucursal_id: ?int, area_id: ?int, activo: string, acceso: string}  $f
     */
    public static function build(array $f, User $user, bool $applyAcceso = true): Builder
    {
        $q = $f['q'];

        return Empleado::query()
            ->visibleTo($user)
            ->when($q !== '', function (Builder $qq) use ($q) {
                $qq->where(function (Builder $w) use ($q) {
                    $w->where('nombre', 'like', "%{$q}%")
                        ->orWhere('apellido_paterno', 'like', "%{$q}%")
                        ->orWhere('apellido_materno', 'like', "%{$q}%")
                        ->orWhere('telefono', 'like', "%{$q}%")
                        ->orWhere('puesto', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))
                        ->orWhereHas('sucursal', fn ($s) => $s->where('nombre', 'like', "%{$q}%"))
                        ->orWhereHas('area', fn ($a) => $a->where('nombre', 'like', "%{$q}%"));
                });
            })
            ->when($f['corporativo_id'], fn ($qq, $id) => $qq->whereHas('sucursal', fn ($s) => $s->where('corporativo_id', $id)))
            ->when($f['sucursal_id'], fn ($qq, $id) => $qq->where('sucursal_id', $id))
            ->when($f['area_id'], fn ($qq, $id) => $qq->where('area_id', $id))
            ->when($f['activo'] !== 'all', fn ($qq) => $qq->where('activo', $f['activo'] === '1'))
            ->when($applyAcceso && $f['acceso'] === 'con', fn ($qq) => $qq->has('user'))
            ->when($applyAcceso && $f['acceso'] === 'sin', fn ($qq) => $qq->doesntHave('user'));
    }

    /**
     * Conteos con los filtros actuales (sin el filtro de acceso, para que los
     * tres números siempre sumen).
     *
     * @return array{total: int, con_usuario: int, sin_usuario: int}
     */
    public static function counts(array $f, User $user): array
    {
        $total = self::build($f, $user, false)->count();
        $con = self::build($f, $user, false)->has('user')->count();

        return ['total' => $total, 'con_usuario' => $con, 'sin_usuario' => $total - $con];
    }
}
