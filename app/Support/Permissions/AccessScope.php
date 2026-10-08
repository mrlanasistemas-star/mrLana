<?php

namespace App\Support\Permissions;

use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Resolución centralizada del alcance de lectura.
 *
 * Jerarquía: Global > Corporativo > Sucursal > Propio > Sin acceso.
 * - Se aplica siempre el nivel más amplio que la persona tenga y pueda
 *   resolver.
 * - "Mi sucursal" y "mi corporativo" se obtienen de
 *   usuario → colaborador → sucursal → corporativo. Sin colaborador (o sin
 *   sucursal) esos niveles NO dan acceso: nunca se convierten en globales.
 * - Un nivel superior incluye lo propio.
 *
 * Todas las consultas y verificaciones de registros concretos pasan por aquí
 * (scopes de modelos, policies, exportaciones), para que la vista, los
 * contadores y los reportes muestren exactamente lo mismo.
 */
final class AccessScope
{
    /** Alcance efectivo del usuario en un módulo. */
    public static function for(User $user, string $module): Scope
    {
        $best = Scope::None;

        foreach (PermissionCatalog::scopeLevels($module) as $levelKey => $permission) {
            $level = Scope::fromKey($levelKey);
            if ($level->value <= $best->value || ! $user->can($permission)) {
                continue;
            }
            if (self::resolvable($user, $module, $level)) {
                $best = $level;
            }
        }

        return $best;
    }

    /**
     * Niveles que el usuario puede consultar en un módulo, del más alto al más
     * bajo (para vistas con selector, como el dashboard). Incluye los niveles
     * inferiores al efectivo que el módulo define y que se pueden resolver.
     *
     * @return list<Scope>
     */
    public static function available(User $user, string $module): array
    {
        $max = self::for($user, $module);
        $out = [];

        foreach (array_reverse(array_keys(PermissionCatalog::scopeLevels($module))) as $levelKey) {
            $level = Scope::fromKey($levelKey);
            if ($level->value <= $max->value && self::resolvable($user, $module, $level)) {
                $out[] = $level;
            }
        }

        return $out;
    }

    /** ¿El nivel tiene los datos organizacionales que necesita? */
    public static function resolvable(User $user, string $module, Scope $level): bool
    {
        return match ($level) {
            Scope::Global => true,
            Scope::Corporativo => self::corporativoId($user) !== null,
            Scope::Sucursal => self::sucursalId($user) !== null,
            Scope::Own => match (PermissionCatalog::OWN_REQUIRES[$module] ?? null) {
                'corporativo' => self::corporativoId($user) !== null,
                'sucursal' => self::sucursalId($user) !== null,
                'area' => self::areaId($user) !== null,
                'empleado' => self::empleado($user) !== null,
                default => true,
            },
            Scope::None => false,
        };
    }

    /* =========================================================
     | Datos organizacionales del usuario
     ========================================================= */

    public static function empleado(User $user): ?Empleado
    {
        if (! $user->empleado_id) {
            return null;
        }

        $user->loadMissing('empleado.sucursal');

        return $user->empleado;
    }

    public static function sucursalId(User $user): ?int
    {
        $id = self::empleado($user)?->sucursal_id;

        return $id ? (int) $id : null;
    }

    public static function corporativoId(User $user): ?int
    {
        $id = self::empleado($user)?->sucursal?->corporativo_id;

        return $id ? (int) $id : null;
    }

    public static function areaId(User $user): ?int
    {
        $id = self::empleado($user)?->area_id;

        return $id ? (int) $id : null;
    }

    /** Subconsulta con los ids de sucursal de un corporativo. */
    public static function sucursalesOf(int $corporativoId): \Illuminate\Database\Eloquent\Builder
    {
        return Sucursal::query()->select('id')->where('corporativo_id', $corporativoId);
    }

    /* =========================================================
     | Aplicación a consultas y registros concretos
     ========================================================= */

    /**
     * Limita una consulta al alcance del usuario en el módulo.
     *
     * $map describe cómo filtrar cada nivel:
     * - 'own'         => Closure($q): condiciones de "propio".
     * - 'sucursal'    => columna con el id de sucursal, o Closure($q, int $sucursalId).
     * - 'corporativo' => columna con el id de corporativo, o Closure($q, int $corpId).
     *                    Si se omite y 'sucursal' es columna, se usa
     *                    "sucursal IN (sucursales del corporativo)".
     *
     * Un nivel sin entrada en $map no devuelve registros (nunca amplía).
     *
     * @template TBuilder of BuilderContract
     *
     * @param  TBuilder  $query
     * @param  array<string, Closure|string>  $map
     * @return TBuilder
     */
    public static function apply(BuilderContract $query, User $user, string $module, array $map, ?Scope $scope = null): BuilderContract
    {
        $scope ??= self::for($user, $module);

        if ($scope === Scope::Global) {
            return $query;
        }

        // Sin acceso no devuelve nada (nunca cae en el criterio de "propio").
        if ($scope === Scope::None) {
            return $query->whereRaw('1 = 0');
        }

        $own = $map['own'] ?? null;
        $org = match ($scope) {
            Scope::Sucursal => self::orgFilter($map, 'sucursal', self::sucursalId($user)),
            Scope::Corporativo => self::orgFilter($map, 'corporativo', self::corporativoId($user)),
            default => null,
        };

        if ($own === null && $org === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($w) use ($own, $org) {
            if ($own !== null) {
                $w->where(fn ($x) => $own($x));
            }
            if ($org !== null) {
                $w->orWhere(fn ($x) => $org($x));
            }
        });
    }

    /**
     * ¿Un registro concreto está dentro del alcance? Recibe los datos del
     * registro (para no repetir consultas en listados).
     *
     * @param  int|Closure():(?int)|null  $corporativoId  id o función que lo obtiene solo si hace falta
     */
    public static function contains(User $user, string $module, bool $own, ?int $sucursalId = null, int|Closure|null $corporativoId = null, ?Scope $scope = null): bool
    {
        $scope ??= self::for($user, $module);

        return match ($scope) {
            Scope::Global => true,
            Scope::None => false,
            Scope::Own => $own,
            Scope::Sucursal => $own || ($sucursalId !== null && $sucursalId === self::sucursalId($user)),
            Scope::Corporativo => $own || self::sameCorporativo($user, $corporativoId),
        };
    }

    private static function sameCorporativo(User $user, int|Closure|null $corporativoId): bool
    {
        $mine = self::corporativoId($user);
        if ($mine === null) {
            return false;
        }

        $value = $corporativoId instanceof Closure ? $corporativoId() : $corporativoId;

        return $value !== null && (int) $value === $mine;
    }

    /** @param  array<string, Closure|string>  $map */
    private static function orgFilter(array $map, string $level, ?int $id): ?Closure
    {
        if ($id === null) {
            return null;
        }

        $entry = $map[$level] ?? null;

        if ($entry === null && $level === 'corporativo' && is_string($map['sucursal'] ?? null)) {
            $column = $map['sucursal'];

            return fn ($q) => $q->whereIn($column, self::sucursalesOf($id));
        }

        return match (true) {
            $entry instanceof Closure => fn ($q) => $entry($q, $id),
            is_string($entry) => fn ($q) => $q->where($entry, $id),
            default => null,
        };
    }
}
