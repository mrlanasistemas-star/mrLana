<?php

namespace App\Enums;

/**
 * Temas de notificación a los que se puede suscribir un rol.
 * Un rol con "Recibir todas" recibe cualquier tema, presente o futuro.
 */
enum NotificationTopic: string
{
    case Requisiciones = 'requisiciones';
    case Pagos = 'pagos';
    case Comprobaciones = 'comprobaciones';
    case Ajustes = 'ajustes';
    case Seguridad = 'seguridad';
    case Sistema = 'sistema';

    public function label(): string
    {
        return match ($this) {
            self::Requisiciones => 'Requisiciones',
            self::Pagos => 'Pagos',
            self::Comprobaciones => 'Comprobaciones',
            self::Ajustes => 'Ajustes',
            self::Seguridad => 'Usuarios y seguridad',
            self::Sistema => 'Sistema',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $t) => ['value' => $t->value, 'label' => $t->label()],
            self::cases(),
        );
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
