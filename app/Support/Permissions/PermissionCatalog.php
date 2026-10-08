<?php

namespace App\Support\Permissions;

/**
 * Catálogo central de permisos del ERP.
 *
 * Los identificadores internos (p. ej. "requisiciones.ver_sucursal") son
 * estables y se guardan en la tabla `permissions` de spatie/laravel-permission.
 * La interfaz nunca muestra el identificador: usa label() / forUi().
 *
 * Cada módulo declara:
 * - `scope`: permisos de alcance de lectura (propio → sucursal → corporativo →
 *   global). Son mutuamente excluyentes en la interfaz y AccessScope aplica
 *   siempre el más amplio que la persona pueda resolver.
 * - `permissions`: todos los permisos visibles, con grupo, descripción breve,
 *   si son sensibles y de qué otros permisos dependen.
 *
 * Los permisos de legacy() ya no autorizan nada: se conservan ocultos durante
 * la transición (ver PermissionTransition) y nunca se muestran.
 *
 * Para agregar un permiso: añádelo aquí y ejecuta `php artisan erp:sync-permissions`
 * (idempotente; Administrador lo recibe automáticamente).
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

    /** Grupos dentro de cada módulo, en orden de presentación. */
    public const GROUPS = [
        'alcance' => 'Alcance de lectura',
        'operacion' => 'Operación',
        'captura' => 'Captura especial',
        'administracion' => 'Administración',
        'exportacion' => 'Exportación',
    ];

    /** Permiso mínimo para recibir notificaciones (nunca el de "ver todas"). */
    public const NOTIFICATIONS_MIN = 'notificaciones.ver';

    /**
     * Qué necesita el nivel "propio" de cada módulo para resolverse. Sin ese
     * dato (p. ej. una cuenta sin colaborador) el nivel no da acceso.
     */
    public const OWN_REQUIRES = [
        'corporativos' => 'corporativo',
        'sucursales' => 'sucursal',
        'areas' => 'area',
        'colaboradores' => 'empleado',
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /**
     * Módulos con su alcance de lectura y sus permisos.
     *
     * @return array<string, array{label: string, description: string, scope: array<string, string>, permissions: array<string, array{label: string, group: string, description: string, sensitive?: bool, requires?: list<string>, scoped?: bool}>}>
     */
    public static function modules(): array
    {
        return self::$cache ??= self::definition();
    }

    /** @return array<string, array<string, mixed>> */
    private static function definition(): array
    {
        $scope4 = fn (string $m, array $labels, array $desc) => [
            "{$m}.ver_propios" => self::p($labels[0], 'alcance', $desc[0]),
            "{$m}.ver_sucursal" => self::p($labels[1], 'alcance', $desc[1] ?? 'Registros de la sucursal de tu colaborador.'),
            "{$m}.ver_corporativo" => self::p($labels[2], 'alcance', $desc[2] ?? 'Registros de todas las sucursales de tu corporativo.'),
            "{$m}.ver_todos" => self::p($labels[3], 'alcance', $desc[3] ?? 'Registros de todos los corporativos y sucursales.', sensitive: true),
        ];
        $levels4 = fn (string $m) => [
            'own' => "{$m}.ver_propios",
            'sucursal' => "{$m}.ver_sucursal",
            'corporativo' => "{$m}.ver_corporativo",
            'global' => "{$m}.ver_todos",
        ];

        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'description' => 'Indicadores y gráficas de gasto. Cada vista usa solo la información de su alcance.',
                'scope' => [
                    'own' => 'dashboard.personal',
                    'sucursal' => 'dashboard.sucursal',
                    'corporativo' => 'dashboard.corporativo',
                    'global' => 'dashboard.general',
                ],
                'permissions' => [
                    'dashboard.personal' => self::p('Ver mi dashboard de gastos', 'alcance', 'Solo tus requisiciones y gastos.'),
                    'dashboard.sucursal' => self::p('Ver dashboard de mi sucursal', 'alcance', 'Indicadores de la sucursal de tu colaborador.'),
                    'dashboard.corporativo' => self::p('Ver dashboard de mi corporativo', 'alcance', 'Indicadores de todas las sucursales de tu corporativo.'),
                    'dashboard.general' => self::p('Ver dashboard general', 'alcance', 'Indicadores de todo el sistema.', sensitive: true),
                    'reportes.dashboard' => self::p('Exportar dashboard autorizado', 'exportacion', 'PDF o Excel exactamente de la vista que puedes consultar.'),
                ],
            ],
            'notificaciones' => [
                'label' => 'Notificaciones',
                'description' => 'Campana de avisos. Qué temas recibe el rol se elige en «Notificaciones del rol».',
                'scope' => ['own' => 'notificaciones.ver', 'global' => 'notificaciones.ver_todas'],
                'permissions' => [
                    'notificaciones.ver' => self::p('Ver mis notificaciones', 'alcance', 'Tu campana: ver, filtrar y marcar como leídas tus avisos.'),
                    'notificaciones.ver_todas' => self::p('Ver todas las notificaciones', 'alcance', 'Consulta de solo lectura de los avisos de todas las personas.', sensitive: true),
                ],
            ],
            'requisiciones' => [
                'label' => 'Requisiciones',
                'description' => 'Solicitudes de gasto desde el borrador hasta la comprobación.',
                'scope' => $levels4('requisiciones'),
                'permissions' => $scope4('requisiciones',
                    ['Ver mis requisiciones', 'Ver requisiciones de mi sucursal', 'Ver requisiciones de mi corporativo', 'Ver todas las requisiciones'],
                    ['Las que creaste o en las que eres el solicitante.'],
                ) + [
                    'requisiciones.registrar' => self::p('Registrar requisiciones', 'operacion', 'Abrir el formulario de nueva requisición.'),
                    'requisiciones.guardar_borrador' => self::p('Guardar borradores', 'operacion', 'Guardar sin enviar para terminar después.', requires: ['requisiciones.registrar']),
                    'requisiciones.enviar' => self::p('Enviar mis requisiciones', 'operacion', 'Enviar a autorización las requisiciones que capturas.', requires: ['requisiciones.registrar']),
                    'requisiciones.editar' => self::p('Editar mis requisiciones en borrador', 'operacion', 'Corregir tus borradores antes de enviarlos.'),
                    'requisiciones.eliminar_borrador' => self::p('Eliminar mis borradores', 'operacion', 'Dar de baja tus propios borradores.'),
                    'requisiciones.solicitar_eliminacion' => self::p('Solicitar eliminación de requisiciones', 'operacion', 'Pedir que se elimine una requisición capturada.'),
                    'requisiciones.elegir_sucursal_corporativo' => self::p('Elegir sucursal de mi corporativo', 'captura', 'Capturar en cualquier sucursal activa de tu corporativo.', requires: ['requisiciones.registrar'], scoped: false),
                    'requisiciones.elegir_sucursal_global' => self::p('Elegir sucursal de cualquier corporativo', 'captura', 'Capturar en sucursales de otros corporativos.', sensitive: true, requires: ['requisiciones.registrar'], scoped: false),
                    'requisiciones.elegir_corporativo' => self::p('Elegir otro corporativo comprador', 'captura', 'Cambiar el corporativo que compra. Requiere elegir sucursal de cualquier corporativo.', sensitive: true, requires: ['requisiciones.registrar', 'requisiciones.elegir_sucursal_global'], scoped: false),
                    'requisiciones.elegir_solicitante' => self::p('Elegir solicitante', 'captura', 'Registrar a nombre de otro colaborador dentro de las sucursales que puedes elegir.', requires: ['requisiciones.registrar'], scoped: false),
                    'requisiciones.editar_cualquiera' => self::p('Editar cualquier borrador autorizado', 'administracion', 'Editar y enviar borradores de otras personas dentro de tu alcance.'),
                    'requisiciones.eliminar' => self::p('Eliminar requisiciones autorizadas', 'administracion', 'Baja lógica de requisiciones dentro de tu alcance.', sensitive: true),
                    'requisiciones.autorizar_eliminacion' => self::p('Autorizar o rechazar solicitudes de eliminación', 'administracion', 'Resolver las solicitudes de eliminación.', sensitive: true),
                    'requisiciones.exportar' => self::p('Exportar requisiciones autorizadas', 'exportacion', 'Listado en PDF o Excel con tus filtros y tu alcance.'),
                    'requisiciones.imprimir' => self::p('Descargar o imprimir requisiciones autorizadas', 'exportacion', 'PDF individual de cada requisición que puedes ver.'),
                ],
            ],
            'plantillas' => [
                'label' => 'Plantillas',
                'description' => 'Requisiciones frecuentes guardadas para reutilizar.',
                'scope' => ['own' => 'plantillas.ver_propios', 'global' => 'plantillas.ver_todos'],
                'permissions' => [
                    'plantillas.ver_propios' => self::p('Ver mis plantillas', 'alcance', 'Solo las plantillas que creaste.'),
                    'plantillas.ver_todos' => self::p('Ver todas las plantillas', 'alcance', 'Plantillas de todas las personas.', sensitive: true),
                    'plantillas.registrar' => self::p('Registrar plantillas', 'operacion', 'Crear plantillas propias. Respetan tus permisos de captura.'),
                    'plantillas.editar' => self::p('Editar mis plantillas', 'operacion', 'Modificar las plantillas que creaste.'),
                    'plantillas.eliminar' => self::p('Eliminar mis plantillas', 'operacion', 'Dar de baja o reactivar tus plantillas.'),
                    'plantillas.editar_cualquiera' => self::p('Editar cualquier plantilla autorizada', 'administracion', 'Plantillas de otras personas que puedes ver.'),
                    'plantillas.eliminar_cualquiera' => self::p('Eliminar cualquier plantilla autorizada', 'administracion', 'Baja de plantillas de otras personas que puedes ver.', sensitive: true),
                ],
            ],
            'pagos' => [
                'label' => 'Pagos',
                'description' => 'Autorización y registro de pagos. Heredan el alcance de la requisición.',
                'scope' => $levels4('pagos'),
                'permissions' => $scope4('pagos',
                    ['Ver pagos de mis requisiciones', 'Ver pagos de mi sucursal', 'Ver pagos de mi corporativo', 'Ver todos los pagos'],
                    ['Pagos de las requisiciones que creaste o solicitaste.'],
                ) + [
                    'pagos.registrar' => self::p('Registrar pagos', 'operacion', 'Capturar pagos totales o parciales con su comprobante.'),
                    'pagos.editar' => self::p('Editar datos de pago cuando el estado lo permita', 'operacion', 'Fecha general de pago cuando el total ya está cubierto.'),
                    'pagos.autorizar' => self::p('Autorizar pagos', 'administracion', 'Programar el pago de una requisición capturada.', sensitive: true),
                    'pagos.rechazar' => self::p('Rechazar pagos', 'administracion', 'Rechazar el pago de una requisición capturada, con motivo.', sensitive: true),
                    'pagos.descargar' => self::p('Descargar comprobantes de pago', 'exportacion', 'Ver y descargar el archivo de cada pago.'),
                    'pagos.exportar' => self::p('Exportar pagos autorizados', 'exportacion', 'Listado en PDF o Excel con tus filtros y tu alcance.'),
                ],
            ],
            'comprobaciones' => [
                'label' => 'Comprobaciones',
                'description' => 'Facturas, tickets y notas que comprueban el gasto.',
                'scope' => $levels4('comprobaciones'),
                'permissions' => $scope4('comprobaciones',
                    ['Ver comprobaciones de mis requisiciones', 'Ver comprobaciones de mi sucursal', 'Ver comprobaciones de mi corporativo', 'Ver todas las comprobaciones'],
                    ['Comprobantes de las requisiciones que creaste o solicitaste.'],
                ) + [
                    'comprobaciones.subir' => self::p('Subir comprobantes a mis requisiciones', 'operacion', 'Cargar archivos en tus propias requisiciones.'),
                    'comprobaciones.subir_cualquiera' => self::p('Subir comprobantes a requisiciones autorizadas', 'operacion', 'Cargar archivos en requisiciones de otras personas dentro de tu alcance.'),
                    'comprobaciones.eliminar_propios' => self::p('Eliminar mis comprobantes', 'operacion', 'Quitar comprobantes que tú cargaste y aún no se aprueban.'),
                    'comprobaciones.revisar' => self::p('Revisar comprobantes', 'administracion', 'Ver los controles de revisión de cada comprobante.'),
                    'comprobaciones.aceptar' => self::p('Aceptar comprobantes', 'administracion', 'Aprobar comprobantes.', requires: ['comprobaciones.revisar']),
                    'comprobaciones.rechazar' => self::p('Rechazar comprobantes', 'administracion', 'Rechazar comprobantes con motivo.', requires: ['comprobaciones.revisar']),
                    'comprobaciones.eliminar' => self::p('Eliminar cualquier comprobante autorizado', 'administracion', 'Quitar comprobantes de requisiciones dentro de tu alcance.', sensitive: true),
                    'comprobaciones.administrar_folios' => self::p('Editar folios de factura', 'administracion', 'Corregir folio y monto de facturas registradas.'),
                    'comprobaciones.exportar' => self::p('Exportar comprobaciones autorizadas', 'exportacion', 'Listado en PDF o Excel con tus filtros y tu alcance.'),
                ],
            ],
            'ajustes' => [
                'label' => 'Ajustes de monto',
                'description' => 'Devoluciones, faltantes e incrementos sobre el total de una requisición.',
                'scope' => $levels4('ajustes'),
                'permissions' => $scope4('ajustes',
                    ['Ver ajustes de mis requisiciones', 'Ver ajustes de mi sucursal', 'Ver ajustes de mi corporativo', 'Ver todos los ajustes'],
                    ['Ajustes de las requisiciones que creaste o solicitaste.'],
                ) + [
                    'ajustes.solicitar' => self::p('Solicitar ajustes en mis requisiciones', 'operacion', 'Pedir un ajuste de monto en tus requisiciones.'),
                    'ajustes.solicitar_cualquiera' => self::p('Solicitar ajustes en requisiciones autorizadas', 'operacion', 'Pedir ajustes en requisiciones de otras personas dentro de tu alcance.'),
                    'ajustes.autorizar' => self::p('Autorizar ajustes', 'administracion', 'Aprobar ajustes pendientes.', sensitive: true),
                    'ajustes.rechazar' => self::p('Rechazar ajustes', 'administracion', 'Rechazar ajustes pendientes con motivo.'),
                    'ajustes.aplicar' => self::p('Aplicar ajustes autorizados', 'administracion', 'Cambiar el total de la requisición con un ajuste aprobado.', sensitive: true),
                ],
            ],
            'proveedores' => [
                'label' => 'Proveedores',
                'description' => 'Beneficiarios de los pagos.',
                'scope' => ['own' => 'proveedores.ver', 'global' => 'proveedores.ver_todos'],
                'permissions' => [
                    'proveedores.ver' => self::p('Ver mis proveedores', 'alcance', 'Proveedores que registraste.'),
                    'proveedores.ver_todos' => self::p('Ver todos los proveedores', 'alcance', 'Proveedores de todas las personas.', sensitive: true),
                    'proveedores.registrar' => self::p('Registrar proveedores', 'operacion', 'Dar de alta proveedores propios.'),
                    'proveedores.usar_todos' => self::p('Utilizar cualquier proveedor activo en requisiciones', 'operacion', 'Elegir proveedores activos de otras personas al capturar, sin poder administrarlos.', scoped: false),
                    'proveedores.editar' => self::p('Editar proveedores autorizados', 'administracion', 'Modificar proveedores dentro de tu alcance.'),
                    'proveedores.desactivar' => self::p('Desactivar proveedores autorizados', 'administracion', 'Dar de baja proveedores dentro de tu alcance.'),
                    'proveedores.reactivar' => self::p('Reactivar proveedores autorizados', 'administracion', 'Volver a activar proveedores dentro de tu alcance.'),
                    'proveedores.exportar' => self::p('Exportar proveedores autorizados', 'exportacion', 'Listado en PDF o Excel con tu alcance.'),
                ],
            ],
            'conceptos' => [
                'label' => 'Conceptos',
                'description' => 'Clasificación del gasto (catálogo general).',
                'scope' => ['global' => 'conceptos.ver'],
                'permissions' => self::crud('conceptos', 'Ver conceptos', 'Consultar el catálogo de conceptos.', 'conceptos'),
            ],
            'corporativos' => [
                'label' => 'Corporativos',
                'description' => 'Empresas del grupo que compran.',
                'scope' => ['own' => 'corporativos.ver_propio', 'global' => 'corporativos.ver'],
                'permissions' => [
                    'corporativos.ver_propio' => self::p('Ver mi corporativo', 'alcance', 'El corporativo de la sucursal de tu colaborador.'),
                    'corporativos.ver' => self::p('Ver todos los corporativos', 'alcance', 'Todos los corporativos del sistema.', sensitive: true),
                ] + self::actions('corporativos', 'corporativos'),
            ],
            'sucursales' => [
                'label' => 'Sucursales',
                'description' => 'Ubicaciones de cada corporativo.',
                'scope' => ['own' => 'sucursales.ver_propia', 'corporativo' => 'sucursales.ver_corporativo', 'global' => 'sucursales.ver'],
                'permissions' => [
                    'sucursales.ver_propia' => self::p('Ver mi sucursal', 'alcance', 'La sucursal de tu colaborador.'),
                    'sucursales.ver_corporativo' => self::p('Ver sucursales de mi corporativo', 'alcance', 'Todas las sucursales de tu corporativo.'),
                    'sucursales.ver' => self::p('Ver todas las sucursales', 'alcance', 'Sucursales de todos los corporativos.', sensitive: true),
                ] + self::actions('sucursales', 'sucursales'),
            ],
            'areas' => [
                'label' => 'Áreas',
                'description' => 'Departamentos de cada corporativo.',
                'scope' => ['own' => 'areas.ver_propia', 'corporativo' => 'areas.ver_corporativo', 'global' => 'areas.ver'],
                'permissions' => [
                    'areas.ver_propia' => self::p('Ver mi área', 'alcance', 'El área de tu colaborador.'),
                    'areas.ver_corporativo' => self::p('Ver áreas de mi corporativo', 'alcance', 'Todas las áreas de tu corporativo.'),
                    'areas.ver' => self::p('Ver todas las áreas', 'alcance', 'Áreas de todos los corporativos.', sensitive: true),
                ] + self::actions('áreas', 'areas'),
            ],
            'colaboradores' => [
                'label' => 'Colaboradores',
                'description' => 'Personas de la organización (con o sin cuenta de acceso).',
                'scope' => ['own' => 'colaboradores.ver_propio', 'sucursal' => 'colaboradores.ver_sucursal', 'corporativo' => 'colaboradores.ver_corporativo', 'global' => 'colaboradores.ver'],
                'permissions' => [
                    'colaboradores.ver_propio' => self::p('Ver mi información de colaborador', 'alcance', 'Solo tu propio registro.'),
                    'colaboradores.ver_sucursal' => self::p('Ver colaboradores de mi sucursal', 'alcance', 'Personas de la sucursal de tu colaborador.'),
                    'colaboradores.ver_corporativo' => self::p('Ver colaboradores de mi corporativo', 'alcance', 'Personas de todas las sucursales de tu corporativo.'),
                    'colaboradores.ver' => self::p('Ver todos los colaboradores', 'alcance', 'Personas de todos los corporativos.', sensitive: true),
                    'colaboradores.registrar' => self::p('Registrar colaboradores', 'operacion', 'Alta de personas en las sucursales de tu alcance.'),
                    'colaboradores.editar' => self::p('Editar colaboradores autorizados', 'administracion', 'Modificar personas dentro de tu alcance.'),
                    'colaboradores.desactivar' => self::p('Desactivar colaboradores autorizados', 'administracion', 'Baja de personas; también desactiva su cuenta.', sensitive: true),
                    'colaboradores.reactivar' => self::p('Reactivar colaboradores autorizados', 'administracion', 'Volver a activar personas dentro de tu alcance.'),
                    'colaboradores.exportar' => self::p('Exportar colaboradores autorizados', 'exportacion', 'Listado en PDF o Excel con tu alcance.'),
                ],
            ],
            'usuarios' => [
                'label' => 'Usuarios',
                'description' => 'Cuentas de acceso al sistema.',
                'scope' => ['sucursal' => 'usuarios.ver_sucursal', 'corporativo' => 'usuarios.ver_corporativo', 'global' => 'usuarios.ver'],
                'permissions' => [
                    'usuarios.ver_sucursal' => self::p('Ver usuarios de mi sucursal', 'alcance', 'Cuentas vinculadas a colaboradores de tu sucursal.'),
                    'usuarios.ver_corporativo' => self::p('Ver usuarios de mi corporativo', 'alcance', 'Cuentas vinculadas a colaboradores de tu corporativo.'),
                    'usuarios.ver' => self::p('Ver todos los usuarios', 'alcance', 'Todas las cuentas, incluidas las que no tienen colaborador.', sensitive: true),
                    'usuarios.registrar' => self::p('Registrar usuarios', 'operacion', 'Crear cuentas para colaboradores de tu alcance.', sensitive: true),
                    'usuarios.ver_vinculo' => self::p('Consultar relación usuario–colaborador', 'operacion', 'Ver qué colaborador tiene cada cuenta y viceversa.'),
                    'usuarios.editar' => self::p('Editar usuarios autorizados', 'administracion', 'Nombre, correo y colaborador de cuentas de tu alcance.', sensitive: true),
                    'usuarios.cambiar_rol' => self::p('Cambiar el rol de usuarios autorizados', 'administracion', 'Solo puedes asignar roles con permisos que tú también tienes.', sensitive: true, requires: ['usuarios.editar']),
                    'usuarios.desactivar' => self::p('Desactivar usuarios autorizados', 'administracion', 'La persona deja de poder entrar de inmediato.', sensitive: true),
                    'usuarios.reactivar' => self::p('Reactivar usuarios autorizados', 'administracion', 'Devolver el acceso a una cuenta desactivada.'),
                    'usuarios.restablecer_contrasena' => self::p('Restablecer contraseñas', 'administracion', 'Enviar una contraseña temporal por correo.', sensitive: true),
                ],
            ],
            'roles' => [
                'label' => 'Roles y permisos',
                'description' => 'Qué puede hacer cada grupo de personas.',
                'scope' => ['global' => 'roles.ver'],
                'permissions' => [
                    'roles.ver' => self::p('Ver roles', 'alcance', 'Consultar roles, permisos y notificaciones.'),
                    'roles.registrar' => self::p('Registrar roles', 'administracion', 'Crear roles personalizados.', sensitive: true),
                    'roles.editar' => self::p('Editar roles y permisos', 'administracion', 'Cambiar permisos y notificaciones de los roles.', sensitive: true),
                    'roles.eliminar' => self::p('Eliminar roles', 'administracion', 'Eliminar roles sin usuarios asignados.', sensitive: true),
                ],
            ],
            'logs' => [
                'label' => 'Bitácora',
                'description' => 'Quién hizo qué y cuándo. Es de solo lectura e inmutable.',
                'scope' => ['own' => 'logs.ver_propios', 'sucursal' => 'logs.ver_sucursal', 'corporativo' => 'logs.ver_corporativo', 'global' => 'logs.ver'],
                'permissions' => [
                    'logs.ver_propios' => self::p('Ver mi actividad', 'alcance', 'Solo los movimientos que hiciste tú.'),
                    'logs.ver_sucursal' => self::p('Ver actividad de mi sucursal', 'alcance', 'Movimientos de personas de tu sucursal.'),
                    'logs.ver_corporativo' => self::p('Ver actividad de mi corporativo', 'alcance', 'Movimientos de personas de tu corporativo.'),
                    'logs.ver' => self::p('Ver toda la bitácora', 'alcance', 'Todos los movimientos del sistema.', sensitive: true),
                ],
            ],
            'configuracion' => [
                'label' => 'Configuración',
                'description' => 'Colores, logo y aplicación móvil.',
                'scope' => ['global' => 'configuracion.ver'],
                'permissions' => [
                    'configuracion.ver' => self::p('Ver configuración', 'alcance', 'Consultar la configuración visual.'),
                    'configuracion.administrar' => self::p('Administrar configuración', 'administracion', 'Cambiar colores, logo y ajustes generales.', sensitive: true),
                ],
            ],
        ];
    }

    /**
     * Permisos anteriores que ya no autorizan nada. Se conservan (ocultos) para
     * no perder historial durante la transición; la interfaz nunca los muestra.
     *
     * @return array<string, string>
     */
    public static function legacy(): array
    {
        return [
            'dashboard.ver' => 'Ver dashboard (anterior)',
            'pagos.ver' => 'Ver pagos (anterior)',
            'comprobaciones.ver' => 'Ver comprobaciones (anterior)',
            'ajustes.ver' => 'Ver ajustes (anterior)',
            'ajustes.revisar' => 'Autorizar o rechazar ajustes (anterior)',
        ];
    }

    /** @return list<string> Permisos vigentes (visibles). */
    public static function all(): array
    {
        $names = [];
        foreach (self::modules() as $module) {
            array_push($names, ...array_keys($module['permissions']));
        }

        return $names;
    }

    /** @return array<string, string> nivel => permiso de alcance del módulo. */
    public static function scopeLevels(string $module): array
    {
        return self::modules()[$module]['scope'] ?? [];
    }

    /** @return list<string> Módulos con alcance de lectura. */
    public static function scopedModules(): array
    {
        return array_keys(array_filter(self::modules(), fn (array $m) => $m['scope'] !== []));
    }

    /** Middleware "permission:a|b|c": basta cualquier nivel de alcance del módulo. */
    public static function anyScope(string $module): string
    {
        return implode('|', self::scopeLevels($module));
    }

    /** Módulo al que pertenece un permiso. */
    public static function moduleOf(string $permission): ?string
    {
        foreach (self::modules() as $key => $module) {
            if (isset($module['permissions'][$permission])) {
                return $key;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public static function meta(string $permission): ?array
    {
        $module = self::moduleOf($permission);

        return $module ? self::modules()[$module]['permissions'][$permission] : null;
    }

    public static function label(string $permission): string
    {
        return self::meta($permission)['label'] ?? self::legacy()[$permission] ?? 'Permiso';
    }

    public static function isSensitive(string $permission): bool
    {
        return (bool) (self::meta($permission)['sensitive'] ?? false);
    }

    /** ¿Es un permiso de alcance de lectura? */
    public static function isScope(string $permission): bool
    {
        $module = self::moduleOf($permission);

        return $module !== null && in_array($permission, self::scopeLevels($module), true);
    }

    /**
     * Normaliza la selección de un rol antes de guardarla:
     * - descarta permisos desconocidos o legados;
     * - agrega dependencias explícitas (`requires`);
     * - si una acción necesita leer registros y el módulo no tiene alcance,
     *   asigna el alcance MÍNIMO del módulo (nunca el global si hay otro);
     * - recibir notificaciones exige al menos "Ver mis notificaciones";
     * - conserva solo el alcance más alto de cada módulo.
     *
     * @param  iterable<string>  $permissions
     * @return list<string>
     */
    public static function normalize(iterable $permissions, bool $receivesNotifications = false): array
    {
        $valid = array_flip(self::all());
        $set = [];
        foreach ($permissions as $p) {
            if (is_string($p) && isset($valid[$p])) {
                $set[$p] = true;
            }
        }

        if ($receivesNotifications) {
            $set[self::NOTIFICATIONS_MIN] = true;
        }

        // Dependencias (pueden encadenarse: se itera hasta estabilizar).
        do {
            $before = count($set);
            foreach (array_keys($set) as $p) {
                foreach (self::meta($p)['requires'] ?? [] as $dep) {
                    $set[$dep] = true;
                }
            }
        } while (count($set) !== $before);

        foreach (self::modules() as $module) {
            $levels = array_values($module['scope']);
            if ($levels === []) {
                continue;
            }

            $hasScope = array_filter($levels, fn ($l) => isset($set[$l])) !== [];
            $needsScope = false;
            foreach ($module['permissions'] as $name => $meta) {
                if (isset($set[$name]) && $meta['group'] !== 'alcance' && ($meta['scoped'] ?? true)) {
                    $needsScope = true;
                    break;
                }
            }

            if (! $hasScope && $needsScope) {
                $set[$levels[0]] = true;
            }

            // Solo el nivel más alto seleccionado.
            $highest = null;
            foreach ($levels as $level) {
                if (isset($set[$level])) {
                    $highest = $level;
                }
            }
            foreach ($levels as $level) {
                if ($level !== $highest) {
                    unset($set[$level]);
                }
            }
        }

        // Orden estable según el catálogo.
        return array_values(array_filter(self::all(), fn ($p) => isset($set[$p])));
    }

    /**
     * Estructura para la interfaz (sin claves técnicas a la vista; `name` solo
     * se usa como identificador del control).
     *
     * @return list<array<string, mixed>>
     */
    public static function forUi(): array
    {
        $out = [];
        foreach (self::modules() as $key => $module) {
            $scope = [];
            foreach ($module['scope'] as $level => $name) {
                $scope[] = [
                    'level' => $level,
                    'name' => $name,
                    'label' => $module['permissions'][$name]['label'],
                    'description' => $module['permissions'][$name]['description'],
                ];
            }

            $groups = [];
            foreach (self::GROUPS as $groupKey => $groupLabel) {
                if ($groupKey === 'alcance') {
                    continue;
                }
                $perms = [];
                foreach ($module['permissions'] as $name => $meta) {
                    if ($meta['group'] !== $groupKey) {
                        continue;
                    }
                    $perms[] = [
                        'name' => $name,
                        'label' => $meta['label'],
                        'description' => $meta['description'],
                        'sensitive' => (bool) ($meta['sensitive'] ?? false),
                        'requires' => array_values($meta['requires'] ?? []),
                        'scoped' => (bool) ($meta['scoped'] ?? true),
                    ];
                }
                if ($perms !== []) {
                    $groups[] = ['key' => $groupKey, 'label' => $groupLabel, 'permissions' => $perms];
                }
            }

            $out[] = [
                'key' => $key,
                'label' => $module['label'],
                'description' => $module['description'],
                'scope' => $scope,
                'groups' => $groups,
            ];
        }

        return $out;
    }

    /**
     * Módulos con sus permisos (clave + etiqueta), para listados de solo lectura.
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
                    fn (string $name, array $meta) => ['name' => $name, 'label' => $meta['label']],
                    array_keys($module['permissions']),
                    array_values($module['permissions']),
                ),
            ];
        }

        return $out;
    }

    /**
     * Permisos iniciales por rol para instalaciones nuevas. Equivalen al
     * resultado de PermissionTransition sobre los roles anteriores, de modo
     * que una base nueva y una migrada se comportan igual.
     *
     * @return array<string, list<string>>
     */
    public static function defaultRolePermissions(): array
    {
        $catalogoBase = fn (string $m, string $ver = 'ver') => [
            "{$m}.{$ver}", "{$m}.registrar", "{$m}.editar", "{$m}.desactivar", "{$m}.reactivar", "{$m}.exportar",
        ];

        $contabilidad = array_merge(
            ['dashboard.general', 'reportes.dashboard', 'notificaciones.ver'],
            $catalogoBase('corporativos'),
            $catalogoBase('sucursales'),
            $catalogoBase('areas'),
            $catalogoBase('conceptos'),
            $catalogoBase('proveedores', 'ver_todos'),
            ['proveedores.usar_todos'],
            [
                'requisiciones.ver_todos', 'requisiciones.registrar', 'requisiciones.guardar_borrador', 'requisiciones.enviar',
                'requisiciones.editar', 'requisiciones.editar_cualquiera', 'requisiciones.eliminar_borrador',
                'requisiciones.eliminar', 'requisiciones.exportar', 'requisiciones.imprimir', 'requisiciones.autorizar_eliminacion',
                'requisiciones.elegir_sucursal_corporativo', 'requisiciones.elegir_sucursal_global',
                'requisiciones.elegir_corporativo', 'requisiciones.elegir_solicitante',
                'pagos.ver_todos', 'pagos.autorizar', 'pagos.rechazar', 'pagos.registrar', 'pagos.editar', 'pagos.descargar', 'pagos.exportar',
                'comprobaciones.ver_todos', 'comprobaciones.subir', 'comprobaciones.subir_cualquiera', 'comprobaciones.revisar',
                'comprobaciones.aceptar', 'comprobaciones.rechazar', 'comprobaciones.eliminar', 'comprobaciones.exportar',
                'ajustes.ver_todos', 'ajustes.solicitar', 'ajustes.solicitar_cualquiera', 'ajustes.autorizar', 'ajustes.rechazar', 'ajustes.aplicar',
                'plantillas.ver_todos', 'plantillas.registrar', 'plantillas.editar', 'plantillas.eliminar',
                'plantillas.editar_cualquiera', 'plantillas.eliminar_cualquiera',
            ],
        );

        $colaborador = [
            'dashboard.personal', 'reportes.dashboard', 'notificaciones.ver',
            'proveedores.ver', 'proveedores.registrar', 'proveedores.editar', 'proveedores.desactivar',
            'requisiciones.ver_propios', 'requisiciones.registrar', 'requisiciones.guardar_borrador', 'requisiciones.enviar',
            'requisiciones.editar', 'requisiciones.eliminar_borrador', 'requisiciones.exportar', 'requisiciones.imprimir',
            'requisiciones.solicitar_eliminacion',
            'pagos.ver_propios', 'pagos.descargar', 'pagos.exportar',
            'comprobaciones.ver_propios', 'comprobaciones.subir', 'comprobaciones.exportar',
            'ajustes.ver_propios', 'ajustes.solicitar',
            'plantillas.ver_propios', 'plantillas.registrar', 'plantillas.editar', 'plantillas.eliminar',
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

    /** @return array<string, mixed> */
    private static function p(string $label, string $group, string $description, bool $sensitive = false, array $requires = [], bool $scoped = true): array
    {
        return array_filter([
            'label' => $label,
            'group' => $group,
            'description' => $description,
            'sensitive' => $sensitive ?: null,
            'requires' => $requires ?: null,
            'scoped' => $scoped ? null : false,
        ], fn ($v) => $v !== null);
    }

    /** Acciones estándar de un catálogo organizacional (sin el permiso de ver). */
    private static function actions(string $plural, string $key): array
    {
        return [
            "{$key}.registrar" => self::p("Registrar {$plural}", 'operacion', "Dar de alta {$plural} dentro de tu alcance."),
            "{$key}.editar" => self::p("Editar {$plural}", 'administracion', "Modificar {$plural} que puedes ver."),
            "{$key}.desactivar" => self::p("Desactivar {$plural}", 'administracion', "Baja lógica de {$plural} que puedes ver.", sensitive: true),
            "{$key}.reactivar" => self::p("Reactivar {$plural}", 'administracion', "Volver a activar {$plural} que puedes ver."),
            "{$key}.exportar" => self::p("Exportar {$plural}", 'exportacion', 'PDF o Excel con tus filtros y tu alcance.'),
        ];
    }

    private static function crud(string $plural, string $verLabel, string $verDesc, string $key): array
    {
        return ["{$key}.ver" => self::p($verLabel, 'alcance', $verDesc)] + self::actions($plural, $key);
    }
}
