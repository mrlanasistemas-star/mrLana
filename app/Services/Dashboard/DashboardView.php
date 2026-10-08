<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Support\Permissions\AccessScope;
use App\Support\Permissions\Scope;

/**
 * Vistas del dashboard, derivadas SOLO de los permisos del módulo Dashboard
 * (no de requisiciones ni de usuarios):
 * - personal:    requisiciones y gastos propios.
 * - sucursal:    la sucursal del colaborador vinculado.
 * - corporativo: todas las sucursales del corporativo del colaborador.
 * - general:     todo el sistema.
 */
enum DashboardView: string
{
    case Personal = 'personal';
    case Sucursal = 'sucursal';
    case Corporativo = 'corporativo';
    case General = 'general';

    public static function fromScope(Scope $scope): ?self
    {
        return match ($scope) {
            Scope::Own => self::Personal,
            Scope::Sucursal => self::Sucursal,
            Scope::Corporativo => self::Corporativo,
            Scope::Global => self::General,
            Scope::None => null,
        };
    }

    public function scope(): Scope
    {
        return match ($this) {
            self::Personal => Scope::Own,
            self::Sucursal => Scope::Sucursal,
            self::Corporativo => Scope::Corporativo,
            self::General => Scope::Global,
        };
    }

    /**
     * Vistas que el usuario puede consultar, de la más amplia a la más acotada.
     *
     * @return list<self>
     */
    public static function availableFor(User $user): array
    {
        return array_values(array_filter(array_map(
            fn (Scope $s) => self::fromScope($s),
            AccessScope::available($user, 'dashboard'),
        )));
    }

    public function allowedFor(User $user): bool
    {
        return in_array($this, self::availableFor($user), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Mi dashboard',
            self::Sucursal => 'Mi sucursal',
            self::Corporativo => 'Mi corporativo',
            self::General => 'General',
        };
    }

    public function headline(): string
    {
        return match ($this) {
            self::Personal => 'Mis gastos',
            self::Sucursal => 'Dashboard de mi sucursal',
            self::Corporativo => 'Dashboard de mi corporativo',
            self::General => 'Dashboard general',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Personal => 'Solo tus requisiciones y gastos.',
            self::Sucursal => 'Requisiciones de la sucursal de tu colaborador.',
            self::Corporativo => 'Requisiciones de todas las sucursales de tu corporativo.',
            self::General => 'Información de todos los corporativos y sucursales.',
        };
    }
}
