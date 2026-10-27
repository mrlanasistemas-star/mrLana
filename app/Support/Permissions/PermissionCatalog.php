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

    /** Explicación breve de cada grupo, visible en «Roles y permisos». */
    public const GROUP_HINTS = [
        'alcance' => 'Qué registros ve. Cada nivel incluye los anteriores.',
        'operacion' => 'Trabajo del día a día: dar de alta registros, enviar, subir archivos o hacer solicitudes.',
        'captura' => 'Qué datos puede cambiar al capturar. Sin estos permisos, solicitante, sucursal y corporativo se llenan solos con los de su colaborador.',
        'administracion' => 'Acciones de control: autorizar, rechazar, editar, corregir o dar de baja registros, incluidos los de otras personas.',
        'exportacion' => 'Descargas en PDF o Excel. Nunca incluyen registros fuera de su alcance.',
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

    /**
     * Textos: se leen en «Roles y permisos» al asignar permisos, así que
     * describen en tercera persona («la persona») qué pantallas y botones
     * habilita cada permiso, sobre qué registros y de qué otros depende.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function definition(): array
    {
        /*
         * Alcance de cuatro niveles. $own = descripción completa del nivel
         * propio; $what = qué registros se ven («las requisiciones»);
         * $ownShort = cómo se nombra lo propio al ampliar («las propias»).
         */
        $scope4 = fn (string $m, array $labels, string $own, string $what, string $ownShort) => [
            "{$m}.ver_propios" => self::p($labels[0], 'alcance', $own),
            "{$m}.ver_sucursal" => self::p($labels[1], 'alcance', "Ve {$what} de la sucursal de su colaborador, además de {$ownShort}."),
            "{$m}.ver_corporativo" => self::p($labels[2], 'alcance', "Ve {$what} de todas las sucursales del corporativo de su colaborador, además de {$ownShort}."),
            "{$m}.ver_todos" => self::p($labels[3], 'alcance', "Ve {$what} de todos los corporativos y sucursales, sin restricción.", sensitive: true),
        ];
        $levels4 = fn (string $m) => [
            'own' => "{$m}.ver_propios",
            'sucursal' => "{$m}.ver_sucursal",
            'corporativo' => "{$m}.ver_corporativo",
            'global' => "{$m}.ver_todos",
        ];
        $heredado = 'El alcance se calcula sobre la requisición (quién la creó o solicitó y a qué sucursal se cargó), pero se asigna aparte del de Requisiciones.';

        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'description' => 'Pantalla de inicio con montos, conteos por estatus y gráficas de gasto. Cada vista suma solo las requisiciones de su nivel; un nivel mayor también deja cambiar a las vistas menores. Sin acceso, la pantalla de inicio pasa a ser Requisiciones.',
                'scope' => [
                    'own' => 'dashboard.personal',
                    'sucursal' => 'dashboard.sucursal',
                    'corporativo' => 'dashboard.corporativo',
                    'global' => 'dashboard.general',
                ],
                'permissions' => [
                    'dashboard.personal' => self::p('Ver mi dashboard de gastos', 'alcance', 'Ve «Mi dashboard»: indicadores solo de las requisiciones que la persona creó o en las que es solicitante.'),
                    'dashboard.sucursal' => self::p('Ver dashboard de mi sucursal', 'alcance', 'Ve los indicadores de la sucursal de su colaborador y puede cambiar a su vista personal.'),
                    'dashboard.corporativo' => self::p('Ver dashboard de mi corporativo', 'alcance', 'Ve los indicadores de todas las sucursales del corporativo de su colaborador y puede cambiar a las vistas de su sucursal y personal.'),
                    'dashboard.general' => self::p('Ver dashboard general', 'alcance', 'Ve los indicadores de todo el sistema (todos los corporativos y sucursales) y puede cambiar a cualquier otra vista.', sensitive: true),
                    'reportes.dashboard' => self::p('Exportar dashboard autorizado', 'exportacion', 'Muestra los botones para descargar en PDF o Excel la vista del dashboard que está consultando, con sus filtros. Nunca exporta vistas que no puede ver.'),
                ],
            ],
            'notificaciones' => [
                'label' => 'Notificaciones',
                'description' => 'Campana de avisos y pantalla «Notificaciones». Qué temas recibe el rol se elige aparte, en «Notificaciones del rol».',
                'scope' => ['own' => 'notificaciones.ver', 'global' => 'notificaciones.ver_todas'],
                'permissions' => [
                    'notificaciones.ver' => self::p('Ver mis notificaciones', 'alcance', 'Muestra la campana y la pantalla de Notificaciones con los avisos de la propia persona: verlos, filtrarlos y marcarlos como leídos. Es obligatorio si el rol recibe avisos.'),
                    'notificaciones.ver_todas' => self::p('Ver todas las notificaciones', 'alcance', 'En la pantalla de Notificaciones también ve los avisos de todas las personas, con su destinatario (solo lectura; la campana sigue mostrando solo los suyos). Si el rol además recibe todas las notificaciones, le llegan avisos aunque el registro esté fuera de su alcance.', sensitive: true),
                ],
            ],
            'requisiciones' => [
                'label' => 'Requisiciones',
                'description' => 'Solicitudes de gasto: captura, envío a autorización, eliminación y corrección. Los pagos, comprobaciones y ajustes de cada requisición se asignan en sus propios módulos.',
                'scope' => $levels4('requisiciones'),
                'permissions' => $scope4('requisiciones',
                    ['Ver mis requisiciones', 'Ver requisiciones de mi sucursal', 'Ver requisiciones de mi corporativo', 'Ver todas las requisiciones'],
                    'Ve en Requisiciones solo las que la persona creó o en las que aparece como solicitante.',
                    'las requisiciones',
                    'las propias',
                ) + [
                    'requisiciones.registrar' => self::p('Registrar requisiciones', 'operacion', 'Muestra el botón «Nueva requisición» y abre el formulario de captura (también desde una plantilla). Para guardar lo capturado necesita además «Guardar borradores» o «Enviar mis requisiciones».'),
                    'requisiciones.guardar_borrador' => self::p('Guardar borradores', 'operacion', 'Muestra el botón «Guardar» en el formulario: la requisición queda como Borrador, sin avisar a nadie, para terminarla después.', requires: ['requisiciones.registrar']),
                    'requisiciones.enviar' => self::p('Enviar mis requisiciones', 'operacion', 'Muestra el botón «Enviar» en el formulario y en sus borradores: la requisición pasa a Capturada y se avisa a quien autoriza.', requires: ['requisiciones.registrar']),
                    'requisiciones.editar' => self::p('Editar mis requisiciones en borrador', 'operacion', 'Autoriza modificar los borradores propios antes de enviarlos. Por ahora el sistema no tiene pantalla para editar borradores, así que este permiso no muestra ningún botón.'),
                    'requisiciones.eliminar_borrador' => self::p('Eliminar mis borradores', 'operacion', 'Muestra «Eliminar» en los borradores propios para descartarlos. No aplica a requisiciones ya enviadas.'),
                    'requisiciones.solicitar_eliminacion' => self::p('Solicitar eliminación de requisiciones', 'operacion', 'Muestra «Solicitar eliminación» en las requisiciones Capturadas que puede ver, para pedir con un motivo que se eliminen. Decide quien tenga «Autorizar o rechazar solicitudes de eliminación».'),
                    'requisiciones.elegir_sucursal_corporativo' => self::p('Elegir sucursal de mi corporativo', 'captura', 'Al capturar requisiciones o plantillas, deja elegir cualquier sucursal activa del corporativo de su colaborador (el corporativo comprador queda fijo). Sin este permiso, la sucursal es siempre la de su colaborador.', requires: ['requisiciones.registrar'], scoped: false),
                    'requisiciones.elegir_sucursal_global' => self::p('Elegir sucursal de cualquier corporativo', 'captura', 'Complemento de «Elegir otro corporativo comprador»: juntos permiten capturar en cualquier sucursal activa de cualquier corporativo. Por sí solo no cambia nada en el formulario.', sensitive: true, requires: ['requisiciones.registrar'], scoped: false),
                    'requisiciones.elegir_corporativo' => self::p('Elegir otro corporativo comprador', 'captura', 'Al capturar, deja cambiar el corporativo comprador y elegir cualquier sucursal activa de cualquier corporativo. Al marcarlo se agrega solo «Elegir sucursal de cualquier corporativo».', sensitive: true, requires: ['requisiciones.registrar', 'requisiciones.elegir_sucursal_global'], scoped: false),
                    'requisiciones.elegir_solicitante' => self::p('Elegir solicitante', 'captura', 'Al capturar requisiciones o plantillas, deja registrarlas a nombre de otro colaborador activo de las sucursales que puede elegir. Sin este permiso, el solicitante es siempre su propio colaborador.', requires: ['requisiciones.registrar'], scoped: false),
                    'requisiciones.editar_cualquiera' => self::p('Editar cualquier borrador autorizado', 'administracion', 'Autoriza modificar borradores de otras personas que puede ver y, si también tiene «Enviar mis requisiciones», muestra «Enviar» en esos borradores para mandarlos a autorización.'),
                    'requisiciones.eliminar' => self::p('Eliminar requisiciones autorizadas', 'administracion', 'Muestra «Eliminar» (también en lote) en cualquier requisición que puede ver, en cualquier estatus y sin pasar por solicitud. No se borra: pasa a la pestaña «Eliminadas» y queda en la bitácora.', sensitive: true),
                    'requisiciones.autorizar_eliminacion' => self::p('Autorizar o rechazar solicitudes de eliminación', 'administracion', 'En el detalle de las requisiciones que puede ver, muestra las solicitudes de eliminación pendientes para aprobarlas (la requisición pasa a Eliminada) o rechazarlas con motivo.', sensitive: true),
                    'requisiciones.corregir' => self::p('Corregir datos de requisiciones', 'administracion', 'Muestra «Corregir datos» en las requisiciones que puede ver (en cualquier estatus salvo Eliminada) para cambiar concepto, solicitante, sucursal, corporativo, proveedor u observaciones, eligiendo de todo el catálogo. Pide un motivo, queda en la bitácora y no cambia montos ni items.', sensitive: true),
                    'requisiciones.exportar' => self::p('Exportar requisiciones autorizadas', 'exportacion', 'Muestra los botones «PDF» y «Excel» en el listado de Requisiciones para descargar lo filtrado, con sus items, ajustes y totales. Solo incluye lo que su alcance le deja ver.'),
                    'requisiciones.imprimir' => self::p('Descargar o imprimir requisiciones autorizadas', 'exportacion', 'Muestra el botón «PDF» en cada requisición que puede ver, para descargar o imprimir su formato individual.'),
                ],
            ],
            'plantillas' => [
                'label' => 'Plantillas',
                'description' => 'Requisiciones frecuentes guardadas para precargar el formulario. Usar una plantilla para crear una requisición requiere también «Registrar requisiciones».',
                'scope' => ['own' => 'plantillas.ver_propios', 'global' => 'plantillas.ver_todos'],
                'permissions' => [
                    'plantillas.ver_propios' => self::p('Ver mis plantillas', 'alcance', 'Ve en Plantillas solo las que la persona creó.'),
                    'plantillas.ver_todos' => self::p('Ver todas las plantillas', 'alcance', 'Ve las plantillas de todas las personas y puede usarlas para precargar una requisición.', sensitive: true),
                    'plantillas.registrar' => self::p('Registrar plantillas', 'operacion', 'Muestra «Nueva plantilla» para crear plantillas propias. Al capturarlas aplican sus mismos permisos de captura (solicitante, sucursal, corporativo y proveedores que puede elegir).'),
                    'plantillas.editar' => self::p('Editar mis plantillas', 'operacion', 'Muestra «Editar» en las plantillas que la persona creó.'),
                    'plantillas.eliminar' => self::p('Eliminar mis plantillas', 'operacion', 'Permite dar de baja y volver a activar las plantillas que la persona creó.'),
                    'plantillas.editar_cualquiera' => self::p('Editar cualquier plantilla autorizada', 'administracion', 'Muestra «Editar» también en plantillas de otras personas. Para verlas necesita el alcance «Ver todas las plantillas».'),
                    'plantillas.eliminar_cualquiera' => self::p('Eliminar cualquier plantilla autorizada', 'administracion', 'Permite dar de baja y reactivar plantillas de otras personas. Para verlas necesita el alcance «Ver todas las plantillas».', sensitive: true),
                ],
            ],
            'pagos' => [
                'label' => 'Pagos',
                'description' => 'Autorización, registro y consulta de pagos: pantalla «Pagos» de cada requisición y módulo Pagos del menú. '.$heredado,
                'scope' => $levels4('pagos'),
                'permissions' => $scope4('pagos',
                    ['Ver pagos de mis requisiciones', 'Ver pagos de mi sucursal', 'Ver pagos de mi corporativo', 'Ver todos los pagos'],
                    'Ve el módulo Pagos y la pantalla de pagos solo de las requisiciones que la persona creó o en las que es solicitante.',
                    'los pagos de las requisiciones',
                    'los de sus propias requisiciones',
                ) + [
                    'pagos.registrar' => self::p('Registrar pagos', 'operacion', 'En la pantalla de pagos de la requisición muestra el formulario para registrar un pago total o parcial con su archivo. Cuando se cubre el total, la requisición pasa a Pagada.'),
                    'pagos.editar' => self::p('Editar datos de pago cuando el estado lo permita', 'operacion', 'Permite capturar la fecha general de pago de la requisición, solo cuando sus pagos ya cubren el total.'),
                    'pagos.autorizar' => self::p('Autorizar pagos', 'administracion', 'Muestra «Autorizar pago» en las requisiciones Capturadas que puede ver: pasan a Pago autorizado.', sensitive: true),
                    'pagos.rechazar' => self::p('Rechazar pagos', 'administracion', 'Muestra «Rechazar pago» en las requisiciones Capturadas que puede ver, indicando el motivo.', sensitive: true),
                    'pagos.descargar' => self::p('Descargar comprobantes de pago', 'exportacion', 'Permite abrir y descargar el archivo de cada pago (en la requisición y en el módulo Pagos). Sin él ve los pagos, pero no sus archivos.'),
                    'pagos.exportar' => self::p('Exportar pagos autorizados', 'exportacion', 'Muestra los botones «PDF» y «Excel» en el módulo Pagos para descargar el listado filtrado. Solo incluye lo que su alcance le deja ver.'),
                ],
            ],
            'comprobaciones' => [
                'label' => 'Comprobaciones',
                'description' => 'Facturas, tickets y notas que comprueban el gasto: pantalla «Comprobaciones» de cada requisición, módulo Comprobantes del menú y folios de factura. '.$heredado,
                'scope' => $levels4('comprobaciones'),
                'permissions' => $scope4('comprobaciones',
                    ['Ver comprobaciones de mis requisiciones', 'Ver comprobaciones de mi sucursal', 'Ver comprobaciones de mi corporativo', 'Ver todas las comprobaciones'],
                    'Ve el módulo Comprobantes y la pantalla de comprobaciones solo de las requisiciones que la persona creó o en las que es solicitante.',
                    'los comprobantes de las requisiciones',
                    'los de sus propias requisiciones',
                ) + [
                    'comprobaciones.subir' => self::p('Subir comprobantes a mis requisiciones', 'operacion', 'En las requisiciones propias (creadas o solicitadas por la persona) permite cargar comprobantes y avisar a quien revisa que ya están completos.'),
                    'comprobaciones.subir_cualquiera' => self::p('Subir comprobantes a requisiciones autorizadas', 'operacion', 'Lo mismo que «Subir comprobantes a mis requisiciones», pero también en requisiciones de otras personas que puede ver.'),
                    'comprobaciones.eliminar_propios' => self::p('Eliminar mis comprobantes', 'operacion', 'Permite quitar comprobantes que la propia persona cargó, mientras no estén aprobados.'),
                    'comprobaciones.revisar' => self::p('Revisar comprobantes', 'administracion', 'Base para revisar: por sí solo no muestra nada. Se agrega solo al marcar «Aceptar comprobantes» o «Rechazar comprobantes», que son los que muestran los botones.'),
                    'comprobaciones.aceptar' => self::p('Aceptar comprobantes', 'administracion', 'Muestra el botón para aprobar comprobantes pendientes en las requisiciones que puede ver.', requires: ['comprobaciones.revisar']),
                    'comprobaciones.rechazar' => self::p('Rechazar comprobantes', 'administracion', 'Muestra el botón para rechazar comprobantes pendientes, indicando el motivo, en las requisiciones que puede ver.', requires: ['comprobaciones.revisar']),
                    'comprobaciones.eliminar' => self::p('Eliminar cualquier comprobante autorizado', 'administracion', 'Permite quitar cualquier comprobante de las requisiciones que puede ver, aunque lo haya cargado otra persona o ya esté aprobado.', sensitive: true),
                    'comprobaciones.administrar_folios' => self::p('Editar folios de factura', 'administracion', 'En el panel de folios de la pantalla de comprobaciones, permite corregir el monto total de folios de factura ya registrados que estén dentro de su alcance. El número de folio es fijo: nunca se puede cambiar una vez registrado.'),
                    'comprobaciones.exportar' => self::p('Exportar comprobaciones autorizadas', 'exportacion', 'Muestra los botones «PDF» y «Excel» en el módulo Comprobantes para descargar el listado filtrado. Solo incluye lo que su alcance le deja ver.'),
                ],
            ],
            'ajustes' => [
                'label' => 'Ajustes de monto',
                'description' => 'Devoluciones, faltantes e incrementos sobre el total de una requisición ya enviada. El flujo es: solicitar, luego autorizar o rechazar, y al final aplicar. '.$heredado,
                'scope' => $levels4('ajustes'),
                'permissions' => $scope4('ajustes',
                    ['Ver ajustes de mis requisiciones', 'Ver ajustes de mi sucursal', 'Ver ajustes de mi corporativo', 'Ver todos los ajustes'],
                    'Ve el botón «Ver ajustes» y el historial de ajustes solo de las requisiciones que la persona creó o en las que es solicitante.',
                    'los ajustes de las requisiciones',
                    'los de sus propias requisiciones',
                ) + [
                    'ajustes.solicitar' => self::p('Solicitar ajustes en mis requisiciones', 'operacion', 'Muestra «Ajustes» con el formulario para pedir un ajuste de monto en requisiciones propias ya enviadas (no en borradores ni eliminadas). También puede cancelar sus solicitudes pendientes.'),
                    'ajustes.solicitar_cualquiera' => self::p('Solicitar ajustes en requisiciones autorizadas', 'operacion', 'Lo mismo que «Solicitar ajustes en mis requisiciones», pero también en requisiciones de otras personas que puede ver.'),
                    'ajustes.autorizar' => self::p('Autorizar ajustes', 'administracion', 'Muestra «Aprobar» en los ajustes pendientes de las requisiciones que puede ver. Aprobar no cambia el total: eso lo hace «Aplicar ajustes autorizados». También muestra «Cancelar aprobación» en ajustes aprobados que aún no se aplican, por si se aprobó por error (pide motivo).', sensitive: true),
                    'ajustes.rechazar' => self::p('Rechazar ajustes', 'administracion', 'Muestra el botón para rechazar, con motivo, los ajustes pendientes de las requisiciones que puede ver.'),
                    'ajustes.aplicar' => self::p('Aplicar ajustes autorizados', 'administracion', 'Muestra «Aplicar» en los ajustes ya aprobados: cambia el monto total de la requisición. También puede «Cancelar aprobación» antes de aplicar (pide motivo); ya aplicado no se cancela.', sensitive: true),
                ],
            ],
            'proveedores' => [
                'label' => 'Proveedores',
                'description' => 'Beneficiarios de los pagos. Cada proveedor pertenece a quien lo registró. Para elegir proveedores al capturar no hace falta ver este módulo: basta con que sean propios o con «Utilizar cualquier proveedor activo».',
                'scope' => ['own' => 'proveedores.ver', 'global' => 'proveedores.ver_todos'],
                'permissions' => [
                    'proveedores.ver' => self::p('Ver mis proveedores', 'alcance', 'Ve en Proveedores solo los que la persona registró.'),
                    'proveedores.ver_todos' => self::p('Ver todos los proveedores', 'alcance', 'Ve los proveedores de todas las personas y puede elegir cualquiera activo al capturar.', sensitive: true),
                    'proveedores.registrar' => self::p('Registrar proveedores', 'operacion', 'Muestra «Nuevo proveedor» (en Proveedores y como acceso desde el formulario de requisición) para darlos de alta; la persona queda como su dueña.'),
                    'proveedores.usar_todos' => self::p('Utilizar cualquier proveedor activo en requisiciones', 'operacion', 'Al capturar requisiciones y plantillas, deja elegir cualquier proveedor activo, no solo los propios. No da acceso al módulo Proveedores ni permite modificarlos.', scoped: false),
                    'proveedores.editar' => self::p('Editar proveedores autorizados', 'administracion', 'Muestra «Editar» en los proveedores que puede ver (los propios o todos, según su alcance).'),
                    'proveedores.desactivar' => self::p('Desactivar proveedores autorizados', 'administracion', 'Permite dar de baja (también en lote) los proveedores que puede ver; dejan de aparecer al capturar.'),
                    'proveedores.reactivar' => self::p('Reactivar proveedores autorizados', 'administracion', 'Muestra «Reactivar» en los proveedores dados de baja que puede ver.'),
                    'proveedores.exportar' => self::p('Exportar proveedores autorizados', 'exportacion', 'Muestra los botones «PDF» y «Excel» en Proveedores para descargar el listado. Solo incluye lo que su alcance le deja ver.'),
                ],
            ],
            'conceptos' => [
                'label' => 'Conceptos',
                'description' => 'Catálogo general para clasificar el gasto. Quien captura ve los conceptos activos en el formulario aunque no tenga acceso a este módulo.',
                'scope' => ['global' => 'conceptos.ver'],
                'permissions' => self::crud('conceptos', 'Ver conceptos', 'Muestra el módulo Conceptos en el menú para consultar el catálogo completo, activos e inactivos.', [
                    'plural' => 'conceptos', 'singular' => 'concepto', 'pantalla' => 'Conceptos', 'boton' => 'Nuevo concepto',
                    'baja' => 'Dejan de aparecer al capturar requisiciones.',
                ]),
            ],
            'corporativos' => [
                'label' => 'Corporativos',
                'description' => 'Empresas del grupo que compran. Desactivar un corporativo también da de baja sus sucursales y áreas.',
                'scope' => ['own' => 'corporativos.ver_propio', 'global' => 'corporativos.ver'],
                'permissions' => [
                    'corporativos.ver_propio' => self::p('Ver mi corporativo', 'alcance', 'Ve en Corporativos solo el corporativo al que pertenece la sucursal de su colaborador.'),
                    'corporativos.ver' => self::p('Ver todos los corporativos', 'alcance', 'Ve todos los corporativos del sistema.', sensitive: true),
                ] + self::actions('corporativos', [
                    'plural' => 'corporativos', 'singular' => 'corporativo', 'pantalla' => 'Corporativos', 'boton' => 'Nuevo corporativo',
                    'alta' => ' (con su logo)',
                    'baja' => 'También se dan de baja sus sucursales y áreas.',
                ]),
            ],
            'sucursales' => [
                'label' => 'Sucursales',
                'description' => 'Ubicaciones de cada corporativo. Las requisiciones y los colaboradores se asignan a una sucursal.',
                'scope' => ['own' => 'sucursales.ver_propia', 'corporativo' => 'sucursales.ver_corporativo', 'global' => 'sucursales.ver'],
                'permissions' => [
                    'sucursales.ver_propia' => self::p('Ver mi sucursal', 'alcance', 'Ve en Sucursales solo la sucursal de su colaborador.'),
                    'sucursales.ver_corporativo' => self::p('Ver sucursales de mi corporativo', 'alcance', 'Ve todas las sucursales del corporativo de su colaborador.'),
                    'sucursales.ver' => self::p('Ver todas las sucursales', 'alcance', 'Ve las sucursales de todos los corporativos.', sensitive: true),
                ] + self::actions('sucursales', [
                    'plural' => 'sucursales', 'singular' => 'sucursal', 'pantalla' => 'Sucursales', 'boton' => 'Nueva sucursal',
                    'alta' => ' en los corporativos que puede ver',
                    'baja' => 'Dejan de aparecer al capturar requisiciones.',
                ]),
            ],
            'areas' => [
                'label' => 'Áreas',
                'description' => 'Departamentos de cada corporativo, que se asignan a los colaboradores.',
                'scope' => ['own' => 'areas.ver_propia', 'corporativo' => 'areas.ver_corporativo', 'global' => 'areas.ver'],
                'permissions' => [
                    'areas.ver_propia' => self::p('Ver mi área', 'alcance', 'Ve en Áreas solo el área de su colaborador.'),
                    'areas.ver_corporativo' => self::p('Ver áreas de mi corporativo', 'alcance', 'Ve todas las áreas del corporativo de su colaborador.'),
                    'areas.ver' => self::p('Ver todas las áreas', 'alcance', 'Ve las áreas de todos los corporativos.', sensitive: true),
                ] + self::actions('areas', [
                    'plural' => 'áreas', 'singular' => 'área', 'pantalla' => 'Áreas', 'boton' => 'Nueva área',
                    'alta' => ' en los corporativos que puede ver',
                    'baja' => 'Ya no se pueden asignar a colaboradores.',
                ]),
            ],
            'colaboradores' => [
                'label' => 'Colaboradores',
                'description' => 'Personas de la organización, tengan o no cuenta para entrar. La sucursal del colaborador vinculado a una cuenta define qué ve esa cuenta con los alcances «mi sucursal» y «mi corporativo».',
                'scope' => ['own' => 'colaboradores.ver_propio', 'sucursal' => 'colaboradores.ver_sucursal', 'corporativo' => 'colaboradores.ver_corporativo', 'global' => 'colaboradores.ver'],
                'permissions' => [
                    'colaboradores.ver_propio' => self::p('Ver mi información de colaborador', 'alcance', 'Ve en Colaboradores solo su propio registro.'),
                    'colaboradores.ver_sucursal' => self::p('Ver colaboradores de mi sucursal', 'alcance', 'Ve a las personas de la sucursal de su colaborador.'),
                    'colaboradores.ver_corporativo' => self::p('Ver colaboradores de mi corporativo', 'alcance', 'Ve a las personas de todas las sucursales del corporativo de su colaborador.'),
                    'colaboradores.ver' => self::p('Ver todos los colaboradores', 'alcance', 'Ve a las personas de todos los corporativos y sucursales.', sensitive: true),
                    'colaboradores.registrar' => self::p('Registrar colaboradores', 'operacion', 'Muestra «Registrar colaborador» para dar de alta personas en las sucursales que su alcance le deja ver. No crea cuenta de acceso: eso es «Registrar usuarios».'),
                    'colaboradores.editar' => self::p('Editar colaboradores autorizados', 'administracion', 'Muestra «Editar» en los colaboradores que puede ver para cambiar sus datos, sucursal y área (solo hacia sucursales de su alcance).'),
                    'colaboradores.desactivar' => self::p('Desactivar colaboradores autorizados', 'administracion', 'Muestra «Dar de baja» (también en lote) en los colaboradores que puede ver. Si la persona tiene cuenta, también se desactiva y ya no puede entrar.', sensitive: true),
                    'colaboradores.reactivar' => self::p('Reactivar colaboradores autorizados', 'administracion', 'Muestra «Reactivar» en los colaboradores dados de baja que puede ver. Su cuenta de acceso no se reactiva sola.'),
                    'colaboradores.exportar' => self::p('Exportar colaboradores autorizados', 'exportacion', 'Muestra los botones «PDF» y «Excel» en Colaboradores para descargar el listado. Solo incluye lo que su alcance le deja ver; la cuenta vinculada aparece solo con «Consultar relación usuario–colaborador».'),
                ],
            ],
            'usuarios' => [
                'label' => 'Usuarios',
                'description' => 'Cuentas para entrar al sistema. Los alcances de sucursal y corporativo se calculan con el colaborador vinculado a cada cuenta; las cuentas sin colaborador solo se ven con «Ver todos los usuarios».',
                'scope' => ['sucursal' => 'usuarios.ver_sucursal', 'corporativo' => 'usuarios.ver_corporativo', 'global' => 'usuarios.ver'],
                'permissions' => [
                    'usuarios.ver_sucursal' => self::p('Ver usuarios de mi sucursal', 'alcance', 'Ve en Usuarios su propia cuenta y las de colaboradores de la sucursal de su colaborador.'),
                    'usuarios.ver_corporativo' => self::p('Ver usuarios de mi corporativo', 'alcance', 'Ve su propia cuenta y las de colaboradores de todas las sucursales del corporativo de su colaborador.'),
                    'usuarios.ver' => self::p('Ver todos los usuarios', 'alcance', 'Ve todas las cuentas del sistema, incluidas las que no tienen colaborador vinculado.', sensitive: true),
                    'usuarios.registrar' => self::p('Registrar usuarios', 'operacion', 'Muestra «Registrar usuario» (y «Crear usuario» en Colaboradores) para crear cuentas de colaboradores de su alcance. Solo puede asignar roles cuyos permisos también tenga; la contraseña temporal llega por correo.', sensitive: true),
                    'usuarios.ver_vinculo' => self::p('Consultar relación usuario–colaborador', 'operacion', 'Muestra qué colaborador corresponde a cada cuenta (en Usuarios) y qué cuenta tiene cada colaborador (en Colaboradores y en su exportación).'),
                    'usuarios.editar' => self::p('Editar usuarios autorizados', 'administracion', 'Muestra «Editar» en las cuentas que puede ver para cambiar nombre, correo y colaborador vinculado. Cambiar el rol, desactivar o reactivar requieren sus propios permisos.', sensitive: true),
                    'usuarios.cambiar_rol' => self::p('Cambiar el rol de usuarios autorizados', 'administracion', 'En la edición de una cuenta, permite cambiarle el rol. Solo puede asignar roles cuyos permisos también tenga. Al marcarlo se agrega solo «Editar usuarios autorizados».', sensitive: true, requires: ['usuarios.editar']),
                    'usuarios.desactivar' => self::p('Desactivar usuarios autorizados', 'administracion', 'Muestra «Desactivar» en las cuentas que puede ver: la persona deja de poder entrar de inmediato. No puede desactivar su propia cuenta ni dejar el sistema sin administrador.', sensitive: true),
                    'usuarios.reactivar' => self::p('Reactivar usuarios autorizados', 'administracion', 'Muestra «Reactivar» en las cuentas desactivadas que puede ver, para devolverles el acceso.'),
                    'usuarios.restablecer_contrasena' => self::p('Restablecer contraseñas', 'administracion', 'En la edición de las cuentas que puede ver, muestra «Restablecer contraseña»: genera una contraseña temporal y la envía al correo de la persona.', sensitive: true),
                ],
            ],
            'roles' => [
                'label' => 'Roles y permisos',
                'description' => 'Qué puede ver y hacer cada grupo de personas, y qué avisos recibe. Un cambio en un rol aplica de inmediato a todas sus cuentas.',
                'scope' => ['global' => 'roles.ver'],
                'permissions' => [
                    'roles.ver' => self::p('Ver roles', 'alcance', 'Muestra el módulo Roles y permisos para consultar cada rol, sus permisos y notificaciones, sin poder modificarlos.'),
                    'roles.registrar' => self::p('Registrar roles', 'administracion', 'Muestra «Registrar rol» para crear roles personalizados con los permisos y notificaciones que se elijan.', sensitive: true),
                    'roles.editar' => self::p('Editar roles y permisos', 'administracion', 'Permite cambiar los permisos, la descripción y las notificaciones de los roles (Administrador conserva siempre todos sus permisos). Junto con «Editar usuarios autorizados» da control total del sistema.', sensitive: true),
                    'roles.eliminar' => self::p('Eliminar roles', 'administracion', 'Permite eliminar roles personalizados que no tengan usuarios asignados. Los roles iniciales del sistema no se pueden eliminar.', sensitive: true),
                ],
            ],
            'logs' => [
                'label' => 'Bitácora',
                'description' => 'Registro de quién hizo qué y cuándo. Es de solo lectura: nadie puede editarlo ni borrarlo.',
                'scope' => ['own' => 'logs.ver_propios', 'sucursal' => 'logs.ver_sucursal', 'corporativo' => 'logs.ver_corporativo', 'global' => 'logs.ver'],
                'permissions' => [
                    'logs.ver_propios' => self::p('Ver mi actividad', 'alcance', 'Ve en Bitácora solo los movimientos que hizo la propia persona.'),
                    'logs.ver_sucursal' => self::p('Ver actividad de mi sucursal', 'alcance', 'Ve los movimientos hechos por personas de la sucursal de su colaborador, además de los propios.'),
                    'logs.ver_corporativo' => self::p('Ver actividad de mi corporativo', 'alcance', 'Ve los movimientos hechos por personas de todas las sucursales del corporativo de su colaborador, además de los propios.'),
                    'logs.ver' => self::p('Ver toda la bitácora', 'alcance', 'Ve todos los movimientos del sistema, de cualquier persona.', sensitive: true),
                ],
            ],
            'configuracion' => [
                'label' => 'Configuración',
                'description' => 'Apariencia del sistema: colores, paleta de gráficas, logo y enlace de la aplicación instalable. Los cambios se ven para todas las personas.',
                'scope' => ['global' => 'configuracion.ver'],
                'permissions' => [
                    'configuracion.ver' => self::p('Ver configuración', 'alcance', 'Muestra el módulo Configuración para consultar los ajustes actuales, sin poder cambiarlos.'),
                    'configuracion.administrar' => self::p('Administrar configuración', 'administracion', 'Permite cambiar colores, paleta de gráficas, logo y enlace del instalador de la app, y restaurar los valores predeterminados.', sensitive: true),
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
                    $groups[] = ['key' => $groupKey, 'label' => $groupLabel, 'description' => self::GROUP_HINTS[$groupKey] ?? '', 'permissions' => $perms];
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
                'descripcion' => 'Control total: ve todo y puede hacer cualquier acción, incluidos usuarios, roles y configuración. Conserva siempre todos los permisos, también los que se agreguen después.',
                'receive_all' => true,
                'topics' => [],
            ],
            self::ROLE_CONTABILIDAD => [
                'descripcion' => 'Ve las requisiciones de todo el sistema; autoriza y registra pagos, acepta o rechaza comprobantes, autoriza y aplica ajustes, y administra corporativos, sucursales, áreas, conceptos y proveedores.',
                'receive_all' => false,
                'topics' => ['requisiciones', 'pagos', 'comprobaciones', 'ajustes'],
            ],
            self::ROLE_COLABORADOR => [
                'descripcion' => 'Captura y envía sus propias requisiciones, sube sus comprobantes, pide ajustes y consulta sus pagos. Administra solo sus proveedores y plantillas.',
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

    /**
     * Acciones estándar de un catálogo organizacional (sin el permiso de ver),
     * con textos propios del módulo. $t: plural, singular, pantalla (nombre
     * del módulo en el menú), boton (botón de alta), y opcionalmente alta
     * (dónde se registra) y baja (efecto adicional de dar de baja).
     *
     * @param  array<string, string>  $t
     */
    private static function actions(string $key, array $t): array
    {
        ['plural' => $plural, 'singular' => $singular, 'pantalla' => $pantalla, 'boton' => $boton] = $t;
        $alta = $t['alta'] ?? '';
        $baja = isset($t['baja']) ? ' '.$t['baja'] : '';

        return [
            "{$key}.registrar" => self::p("Registrar {$plural}", 'operacion', "Muestra el botón «{$boton}» en {$pantalla} y permite dar de alta {$plural}{$alta}."),
            "{$key}.editar" => self::p("Editar {$plural}", 'administracion', "Muestra «Editar» en cada {$singular} que puede ver y permite cambiar sus datos."),
            "{$key}.desactivar" => self::p("Desactivar {$plural}", 'administracion', "Permite dar de baja (también en lote) {$plural} que puede ver. No se borran: se conservan en el historial.{$baja}", sensitive: true),
            "{$key}.reactivar" => self::p("Reactivar {$plural}", 'administracion', "Muestra el botón «Activar» para volver a dar de alta {$plural} que se dieron de baja, dentro de su alcance."),
            "{$key}.exportar" => self::p("Exportar {$plural}", 'exportacion', "Muestra los botones «PDF» y «Excel» en {$pantalla} para descargar el listado filtrado. Solo incluye lo que su alcance le deja ver."),
        ];
    }

    /** Catálogo con un solo nivel de lectura más las acciones estándar. */
    private static function crud(string $key, string $verLabel, string $verDesc, array $t): array
    {
        return ["{$key}.ver" => self::p($verLabel, 'alcance', $verDesc)] + self::actions($key, $t);
    }
}
