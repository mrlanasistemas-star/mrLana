<?php

namespace App\Services\Dashboard;

use App\Models\User;

/**
 * Perfil de dashboard derivado de permisos (funciona con roles personalizados):
 * - ejecutivo:  ve todas las requisiciones y administra usuarios.
 * - financiero: ve todas las requisiciones.
 * - personal:   solo sus propias requisiciones.
 */
enum DashboardProfile: string
{
    case Ejecutivo = 'ejecutivo';
    case Financiero = 'financiero';
    case Personal = 'personal';

    public static function forUser(User $user): self
    {
        if ($user->can('requisiciones.ver_todos') && $user->can('usuarios.ver')) {
            return self::Ejecutivo;
        }

        if ($user->can('requisiciones.ver_todos')) {
            return self::Financiero;
        }

        return self::Personal;
    }

    /** ¿El usuario puede ver este perfil? */
    public function allowedFor(User $user): bool
    {
        return match ($this) {
            self::Ejecutivo => self::forUser($user) === self::Ejecutivo,
            self::Financiero => $user->can('requisiciones.ver_todos'),
            self::Personal => true,
        };
    }

    /** Acepta los segmentos de URL anteriores (ADMIN/CONTADOR/COLABORADOR). */
    public static function fromSegment(string $segment): ?self
    {
        return match (strtolower(trim($segment))) {
            'ejecutivo', 'admin' => self::Ejecutivo,
            'financiero', 'contador' => self::Financiero,
            'personal', 'colaborador' => self::Personal,
            default => null,
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Ejecutivo => 'dashboard.admin',
            self::Financiero => 'dashboard.contador',
            self::Personal => 'dashboard.colaborador',
        };
    }

    public function exportSegment(): string
    {
        return match ($this) {
            self::Ejecutivo => 'admin',
            self::Financiero => 'contador',
            self::Personal => 'colaborador',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ejecutivo => 'Panel ejecutivo',
            self::Financiero => 'Panel financiero',
            self::Personal => 'Mi operación',
        };
    }
}
