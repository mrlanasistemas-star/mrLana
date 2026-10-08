<?php

namespace App\Support\Permissions;

/**
 * Niveles de alcance de lectura, de menor a mayor. Un nivel superior incluye
 * a los inferiores (quien ve su corporativo también ve su sucursal y lo propio).
 */
enum Scope: int
{
    case None = 0;
    case Own = 1;
    case Sucursal = 2;
    case Corporativo = 3;
    case Global = 4;

    /** Clave usada en el catálogo y en la interfaz. */
    public function key(): string
    {
        return match ($this) {
            self::None => 'none',
            self::Own => 'own',
            self::Sucursal => 'sucursal',
            self::Corporativo => 'corporativo',
            self::Global => 'global',
        };
    }

    public static function fromKey(string $key): self
    {
        return match ($key) {
            'own' => self::Own,
            'sucursal' => self::Sucursal,
            'corporativo' => self::Corporativo,
            'global' => self::Global,
            default => self::None,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'Sin acceso',
            self::Own => 'Propio',
            self::Sucursal => 'Sucursal',
            self::Corporativo => 'Corporativo',
            self::Global => 'Global',
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->value >= $other->value;
    }

    public function allows(): bool
    {
        return $this !== self::None;
    }
}
