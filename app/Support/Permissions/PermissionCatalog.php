<?php

namespace App\Support\Permissions;

/**
 * Catálogo central de permisos del ERP.
 *
 * Los identificadores internos (p. ej. "usuarios.registrar") son estables y se
 * guardan en la tabla `permissions` de spatie/laravel-permission. La interfaz
 * nunca debe mostrar el identificador: usa label() / grouped().
 *
 * Para agregar un permiso: añádelo aquí y ejecuta `php artisan erp:sync-permissions`
 * (o vuelve a correr las migraciones/seeders); la sincronización es idempotente.
 */
final class PermissionCatalog
{
    public const ROLE_ADMIN = 'Administrador';

    public const ROLE_CONTABILIDAD = 'Contabilidad';

    public const ROLE_COLABORADOR = 'Colaborador';

    /** Valor legado de users.rol → rol inicial. */
    public const LEGACY_ROLE_MAP = [
        'ADMIN' => self::ROLE_ADMIN,
        'CONTADOR' => self::ROLE_CONTABILIDAD,
        'COLABORADOR' => self::ROLE_COLABORADOR,
    ];

    /**
     * Permisos que definen "administración total": quien los tiene puede
     * administrar roles y cuentas. El sistema nunca debe quedarse sin al menos
     * un usuario activo que los tenga.
     */
    public const ADMIN_PERMISSIONS = ['roles.editar', 'usuarios.editar'];

    /**
     * Módulos → [clave de permiso => etiqueta humana].
     *
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    public static function modules(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'permissions' => [
                'dashboard.ver' => 'Ver dashboard',
            ]],
            'corporativos' => ['label' => 'Corporativos', 'permissions' => self::crud('corporativos')],
            'sucursales' => ['label' => 'Sucursales', 'permissions' => self::crud('sucursales')],
            'areas' => ['label' => 'Áreas', 'permissions' => self::crud('áreas', 'areas')],
            'colaboradores' => ['label' => 'Colaboradores', 'permissions' => self::crud('colaboradores')],
            'usuarios' => ['label' => 'Usuarios', 'permissions' => [
                'usuarios.ver' => 'Ver usuarios',
                'usuarios.registrar' => 'Registrar usuarios',
                'usuarios.editar' => 'Editar usuarios',
                'usuarios.desactivar' => 'Desactivar usuarios',
                'usuarios.reactivar' => 'Reactivar usuarios',
                'usuarios.restablecer_contrasena' => 'Restablecer contraseñas',
            ]],
            'roles' => ['label' => 'Roles y permisos', 'permissions' => [
                'roles.ver' => 'Ver roles',
                'roles.registrar' => 'Registrar roles',
                'roles.editar' => 'Editar roles y permisos',
                'roles.eliminar' => 'Eliminar roles',
            ]],
            'conceptos' => ['label' => 'Conceptos', 'permissions' => self::crud('conceptos')],
            'proveedores' => ['label' => 'Proveedores', 'permissions' => self::crud('proveedores') + [
                'proveedores.ver_todos' => 'Ver proveedores de todos los usuarios',
            ]],
            'requisiciones' => ['label' => 'Requisiciones', 'permissions' => [
                'requisiciones.ver_todos' => 'Ver todas las requisiciones',
                'requisiciones.ver_propios' => 'Ver requisiciones propias',
                'requisiciones.registrar' => 'Registrar requisiciones',
                'requisiciones.editar' => 'Editar requisiciones en borrador',
                'requisiciones.eliminar' => 'Eliminar requisiciones',
                'requisiciones.exportar' => 'Exportar requisiciones',
                'requisiciones.solicitar_eliminacion' => 'Solicitar eliminación de requisiciones',
                'requisiciones.autorizar_eliminacion' => 'Autorizar eliminación de requisiciones',
            ]],
            'pagos' => ['label' => 'Pagos', 'permissions' => [
                'pagos.ver' => 'Ver pagos',
                'pagos.autorizar' => 'Autorizar pagos',
                'pagos.registrar' => 'Registrar pagos',
            ]],
            'comprobaciones' => ['label' => 'Comprobaciones', 'permissions' => [
                'comprobaciones.ver' => 'Ver comprobaciones',
                'comprobaciones.subir' => 'Subir comprobantes',
                'comprobaciones.revisar' => 'Revisar comprobantes',
                'comprobaciones.eliminar' => 'Eliminar comprobantes',
                'comprobaciones.administrar_folios' => 'Editar folios de factura',
            ]],
            'ajustes' => ['label' => 'Ajustes de monto', 'permissions' => [
                'ajustes.ver' => 'Ver ajustes',
                'ajustes.solicitar' => 'Solicitar ajustes',
                'ajustes.revisar' => 'Autorizar o rechazar ajustes',
                'ajustes.aplicar' => 'Aplicar ajustes',
            ]],
            'plantillas' => ['label' => 'Plantillas', 'permissions' => [
                'plantillas.ver_todos' => 'Ver plantillas de todos los usuarios',
                'plantillas.ver_propios' => 'Ver plantillas propias',
                'plantillas.registrar' => 'Registrar plantillas',
                'plantillas.editar' => 'Editar plantillas',
                'plantillas.eliminar' => 'Eliminar plantillas',
            ]],
            'reportes' => ['label' => 'Reportes y exportaciones', 'permissions' => [
                'reportes.dashboard' => 'Exportar reportes del dashboard',
            ]],
            'logs' => ['label' => 'Bitácora del sistema', 'permissions' => [
                'logs.ver' => 'Ver bitácora del sistema',
            ]],
            'configuracion' => ['label' => 'Configuración', 'permissions' => [
                'configuracion.ver' => 'Ver configuración',
                'configuracion.administrar' => 'Administrar configuración',
            ]],
            'notificaciones' => ['label' => 'Notificaciones', 'permissions' => [
                'notificaciones.ver' => 'Ver notificaciones',
            ]],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        $names = [];
        foreach (self::modules() as $module) {
            array_push($names, ...array_keys($module['permissions']));
        }

        return $names;
    }

    public static function label(string $permission): string
    {
        foreach (self::modules() as $module) {
            if (isset($module['permissions'][$permission])) {
                return $module['permissions'][$permission];
            }
        }

        return $permission;
    }

    /**
     * Estructura para la interfaz: módulos con sus permisos (clave + etiqueta).
     *
     * @return list<array{key: string, label: string, permissions: list<array{name: string, label: string}>}>
     */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::modules() as $key => $module) {
            $out[] = [
                'key' => $key,
                'label' => $module['label'],
                'permissions' => array_map(
                    fn (string $name, string $label) => ['name' => $name, 'label' => $label],
                    array_keys($module['permissions']),
                    array_values($module['permissions']),
                ),
            ];
        }

        return $out;
    }

    /**
     * Permisos iniciales por rol (equivalentes al comportamiento previo basado
     * en users.rol). Administrador recibe todo el catálogo.
     *
     * @return array<string, list<string>>
     */
    public static function defaultRolePermissions(): array
    {
        $catalogoBase = fn (string $m) => [
            "{$m}.ver", "{$m}.registrar", "{$m}.editar", "{$m}.desactivar", "{$m}.reactivar", "{$m}.exportar",
        ];

        $contabilidad = array_merge(
            ['dashboard.ver'],
            $catalogoBase('corporativos'),
            $catalogoBase('sucursales'),
            $catalogoBase('areas'),
            $catalogoBase('conceptos'),
            $catalogoBase('proveedores'),
            ['proveedores.ver_todos'],
            [
                'requisiciones.ver_todos', 'requisiciones.registrar', 'requisiciones.editar',
                'requisiciones.eliminar', 'requisiciones.exportar', 'requisiciones.autorizar_eliminacion',
                'pagos.ver', 'pagos.autorizar', 'pagos.registrar',
                'comprobaciones.ver', 'comprobaciones.subir', 'comprobaciones.revisar', 'comprobaciones.eliminar',
                'ajustes.ver', 'ajustes.solicitar', 'ajustes.revisar', 'ajustes.aplicar',
                'plantillas.ver_todos', 'plantillas.registrar', 'plantillas.editar', 'plantillas.eliminar',
                'reportes.dashboard',
                'notificaciones.ver',
            ],
        );

        $colaborador = [
            'dashboard.ver',
            'proveedores.ver', 'proveedores.registrar', 'proveedores.editar', 'proveedores.desactivar',
            'requisiciones.ver_propios', 'requisiciones.registrar', 'requisiciones.editar',
            'requisiciones.exportar', 'requisiciones.solicitar_eliminacion',
            'pagos.ver',
            'comprobaciones.ver', 'comprobaciones.subir',
            'ajustes.ver', 'ajustes.solicitar',
            'plantillas.ver_propios', 'plantillas.registrar', 'plantillas.editar', 'plantillas.eliminar',
            'reportes.dashboard',
            'notificaciones.ver',
        ];

        return [
            self::ROLE_ADMIN => self::all(),
            self::ROLE_CONTABILIDAD => $contabilidad,
            self::ROLE_COLABORADOR => $colaborador,
        ];
    }

    /** @return array<string, array{descripcion: string, receive_all: bool, topics: list<string>}> */
    public static function defaultRoleMeta(): array
    {
        return [
            self::ROLE_ADMIN => [
                'descripcion' => 'Acceso total al sistema, usuarios, roles y configuración.',
                'receive_all' => true,
                'topics' => [],
            ],
            self::ROLE_CONTABILIDAD => [
                'descripcion' => 'Autoriza pagos, revisa comprobaciones y ajustes, y administra catálogos.',
                'receive_all' => false,
                'topics' => ['requisiciones', 'pagos', 'comprobaciones', 'ajustes'],
            ],
            self::ROLE_COLABORADOR => [
                'descripcion' => 'Registra y da seguimiento a sus propias requisiciones.',
                'receive_all' => false,
                'topics' => [],
            ],
        ];
    }

    /** @return array<string, string> */
    private static function crud(string $plural, ?string $key = null): array
    {
        $key ??= $plural;

        return [
            "{$key}.ver" => "Ver {$plural}",
            "{$key}.registrar" => "Registrar {$plural}",
            "{$key}.editar" => "Editar {$plural}",
            "{$key}.desactivar" => "Desactivar {$plural}",
            "{$key}.reactivar" => "Reactivar {$plural}",
            "{$key}.exportar" => "Exportar {$plural}",
        ];
    }
}
