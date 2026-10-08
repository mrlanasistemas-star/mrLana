import {
    Banknote, Bell, BellRing, Building2, CircleHelp, ClipboardList, Compass, FileText, KeyRound, Layers3,
    LayoutDashboard, MapPin, Receipt, ScrollText, Send, Settings, Tags, Truck, UserCog, Users,
} from 'lucide-vue-next'
import type { TourDefinition, TourStep } from './types'

/**
 * Registro central de recorridos. Cada paso apunta a un elemento estable
 * mediante `data-tour="…"` (nunca a clases CSS). Si un elemento no existe
 * —por permisos o por el diseño responsivo— el motor salta el paso.
 *
 * Las reglas de visibilidad usan los mismos permisos/alcances que el menú,
 * así nadie ve recorridos de módulos a los que no tiene acceso.
 */

/** Recorrido de una pantalla de catálogo: encabezado de ayuda + listado. */
function listado(id: string, module: string, route: string, icon: TourDefinition['icon'], description: string, listBody: string): TourDefinition {
    return {
        id,
        module,
        title: `Recorrido: ${module}`,
        description,
        icon,
        match: `${route.split('.')[0]}.*`,
        visibility: { views: [id] },
        steps: [
            { id: `${id}-intro`, route, title: module, body: description },
            { id: `${id}-lista`, route, target: `${id}-lista`, title: 'Listado', body: listBody, tip: 'Solo ves los registros de tu alcance; editar o dar de baja también se limita a ellos.' },
        ],
    }
}

export const TOURS: TourDefinition[] = [
    {
        id: 'intro',
        module: 'Bienvenida',
        title: 'Conoce el sistema',
        description: 'Menú, avisos y dónde pedir ayuda.',
        icon: Compass,
        visibility: {},
        steps: [
            { id: 'intro-hola', title: 'Te damos la bienvenida', body: 'Este recorrido te muestra los controles principales. Puedes avanzar con la flecha derecha, regresar con la izquierda y salir con Escape.' },
            { id: 'intro-menu', target: 'menu-principal', title: 'Menú principal', body: 'Aquí están solo los módulos a los que tienes acceso según tu rol.', placement: 'right' },
            { id: 'intro-campana', target: 'campana', title: 'Tus notificaciones', body: 'La campana muestra tus avisos sin leer y se actualiza en tiempo real.', visibility: { views: ['notificaciones'] } },
            { id: 'intro-ayuda', target: 'ayuda-boton', title: 'Ayuda en cualquier pantalla', body: 'Este botón abre los recorridos de la pantalla en la que estés y la guía escrita.' },
        ],
    },
    {
        id: 'dashboard',
        module: 'Dashboard',
        title: 'Recorrido: Dashboard',
        description: 'Vistas por alcance, filtros, indicadores y exportación.',
        icon: LayoutDashboard,
        match: 'dashboard*',
        visibility: { views: ['dashboard'] },
        steps: [
            { id: 'dash-vistas', route: 'dashboard', target: 'dashboard-vistas', title: 'Elige la vista', body: 'Cambia entre Mi dashboard, Mi sucursal, Mi corporativo y General según tus permisos. Empieza en la más amplia.' },
            { id: 'dash-encabezado', route: 'dashboard', target: 'dashboard-encabezado', title: 'Qué estás viendo', body: 'El encabezado indica el alcance y el periodo de las cifras.' },
            { id: 'dash-filtros', route: 'dashboard', target: 'dashboard-filtros', title: 'Filtros', body: 'Periodo, concepto, estatus y —si tu vista lo permite— corporativo y sucursal. Los filtros solo acotan; nunca amplían tu alcance.' },
            { id: 'dash-kpis', route: 'dashboard', target: 'dashboard-indicadores', title: 'Indicadores', body: 'Gasto, número de requisiciones, ticket promedio, pagado y pendientes, comparados con el periodo anterior.' },
            { id: 'dash-exportar', route: 'dashboard', target: 'dashboard-exportar', title: 'Exportar', body: 'El PDF o Excel contiene exactamente la vista y los filtros actuales.', visibility: { anyOf: ['reportes.dashboard'] } },
        ],
    },
    {
        id: 'requisiciones',
        module: 'Requisiciones',
        title: 'Recorrido: Requisiciones',
        description: 'Buscar, filtrar, abrir y dar seguimiento.',
        icon: FileText,
        match: 'requisiciones.index',
        visibility: { views: ['requisiciones'] },
        steps: [
            { id: 'req-nueva', route: 'requisiciones.index', target: 'requisiciones-nueva', title: 'Nueva requisición', body: 'Abre el formulario de captura.', visibility: { anyOf: ['requisiciones.registrar'] } },
            { id: 'req-filtros', route: 'requisiciones.index', target: 'requisiciones-filtros', title: 'Busca y filtra', body: 'Por folio, proveedor, concepto, sucursal, fechas y más.' },
            { id: 'req-lista', route: 'requisiciones.index', target: 'requisiciones-lista', title: 'Tus requisiciones', body: 'Cada tarjeta muestra estatus, montos, fechas y observaciones completas.' },
            { id: 'req-acciones', route: 'requisiciones.index', target: 'requisiciones-acciones', title: 'Acciones visibles', body: 'Ver, Pagos, Comprobaciones, Ajustes y PDF son enlaces.', tip: 'Clic derecho → «Abrir en otra pestaña» o Ctrl + clic para abrir varias requisiciones a la vez.' },
            { id: 'req-exportar', route: 'requisiciones.index', target: 'requisiciones-exportar', title: 'Exportar', body: 'Descarga el listado con los mismos filtros y alcance.', visibility: { anyOf: ['requisiciones.exportar'] } },
        ],
    },
    {
        id: 'requisicion-crear',
        module: 'Nueva requisición',
        title: 'Recorrido: Capturar una requisición',
        description: 'Solicitante, sucursal, items y envío.',
        icon: Send,
        match: 'requisiciones.registrar',
        visibility: { anyOf: ['requisiciones.registrar'] },
        steps: [
            { id: 'cap-datos', route: 'requisiciones.registrar', target: 'requisicion-datos', title: 'Datos generales', body: 'Comprador, sucursal y solicitante se cargan con tu colaborador. Se bloquean si no tienes permisos especiales de captura.' },
            { id: 'cap-solicitante', route: 'requisiciones.registrar', target: 'requisicion-solicitante', title: 'Solicitante', body: 'Con «Elegir solicitante» puedes capturar a nombre de otra persona de las sucursales que puedes elegir.', tip: 'Si eliges a alguien de otra sucursal, usa «Usar datos del solicitante»; nada cambia en silencio.', visibility: { anyOf: ['requisiciones.elegir_solicitante'] } },
            { id: 'cap-items', route: 'requisiciones.registrar', target: 'requisicion-items', title: 'Items', body: 'Agrega al menos uno con cantidad, descripción y precio. Los totales se calculan solos.' },
            { id: 'cap-enviar', route: 'requisiciones.registrar', target: 'requisicion-enviar', title: 'Guarda o envía', body: 'Borrador para terminar después; Enviar la pasa a revisión.' },
        ],
    },
    {
        id: 'plantillas',
        module: 'Plantillas',
        title: 'Recorrido: Plantillas',
        description: 'Gastos frecuentes listos para reutilizar.',
        icon: ClipboardList,
        match: 'plantillas.*',
        visibility: { views: ['plantillas'] },
        steps: [
            { id: 'pla-nueva', route: 'plantillas.index', target: 'plantillas-nueva', title: 'Nueva plantilla', body: 'Guarda encabezado e items de un gasto que se repite.', visibility: { anyOf: ['plantillas.registrar'] } },
            { id: 'pla-lista', route: 'plantillas.index', target: 'plantillas-lista', title: 'Tus plantillas', body: 'Usa una para abrir una requisición precargada. Se aplican tus mismos permisos de captura.' },
        ],
    },
    {
        id: 'pagos',
        module: 'Pagos',
        title: 'Recorrido: Pagos',
        description: 'Consulta de pagos y comprobantes bancarios.',
        icon: Banknote,
        match: 'pagos.*',
        visibility: { views: ['pagos'] },
        steps: [
            { id: 'pag-filtros', route: 'pagos.index', target: 'pagos-filtros', title: 'Filtros', body: 'Por folio, beneficiario, tipo, quién registró, quién autorizó y fechas.' },
            { id: 'pag-lista', route: 'pagos.index', target: 'pagos-lista', title: 'Pagos', body: 'Heredan el alcance de la requisición: solo ves los de requisiciones que puedes consultar.' },
        ],
    },
    {
        id: 'comprobantes',
        module: 'Comprobantes',
        title: 'Recorrido: Comprobantes',
        description: 'Evidencias del gasto y su revisión.',
        icon: Receipt,
        match: 'comprobantes.*',
        visibility: { views: ['comprobaciones'] },
        steps: [
            { id: 'cmp-filtros', route: 'comprobantes.index', target: 'comprobantes-filtros', title: 'Filtros', body: 'Por estatus, tipo, quién cargó, quién revisó y fechas.' },
            { id: 'cmp-lista', route: 'comprobantes.index', target: 'comprobantes-lista', title: 'Comprobantes', body: 'Vista previa y descarga de los comprobantes de tu alcance.' },
        ],
    },
    {
        id: 'notificaciones',
        module: 'Notificaciones',
        title: 'Recorrido: Mis notificaciones',
        description: 'Tus avisos, filtros y marcar como leídas.',
        icon: Bell,
        match: 'notificaciones.index',
        visibility: { views: ['notificaciones'] },
        steps: [
            { id: 'not-encabezado', route: 'notificaciones.index', target: 'notificaciones-encabezado', title: 'Centro de notificaciones', body: 'Aquí solo están tus avisos. Puedes marcarlos todos como leídos.' },
            { id: 'not-lista', route: 'notificaciones.index', target: 'notificaciones-lista', title: 'Avisos', body: 'Abre un aviso para ir al registro relacionado; se marca como leído.' },
        ],
    },
    {
        id: 'notificaciones-todas',
        module: 'Todas las notificaciones',
        title: 'Recorrido: Todas las notificaciones',
        description: 'Consulta administrativa de solo lectura.',
        icon: BellRing,
        match: 'notificaciones.all',
        visibility: { anyOf: ['notificaciones.ver_todas'] },
        steps: [
            { id: 'nt-encabezado', route: 'notificaciones.all', target: 'notificaciones-todas-header', title: 'Solo lectura', body: 'Abrir un aviso desde aquí no lo marca como leído para su destinatario.' },
            { id: 'nt-filtros', route: 'notificaciones.all', target: 'notificaciones-todas-filtros', title: 'Filtros', body: 'Por destinatario, categoría, estado de lectura o texto.' },
        ],
    },
    listado('corporativos', 'Corporativos', 'corporativos.index', Building2, 'Empresas del grupo que compran.', 'Corporativos de tu alcance: el tuyo o todos.'),
    listado('sucursales', 'Sucursales', 'sucursales.index', MapPin, 'Ubicaciones de cada corporativo.', 'Tu sucursal, las de tu corporativo o todas.'),
    listado('areas', 'Áreas', 'areas.index', Layers3, 'Departamentos de cada corporativo.', 'Tu área, las de tu corporativo o todas.'),
    {
        id: 'colaboradores',
        module: 'Colaboradores',
        title: 'Recorrido: Colaboradores',
        description: 'Personas de la organización.',
        icon: Users,
        match: 'colaboradores.*',
        visibility: { views: ['colaboradores'] },
        steps: [
            { id: 'col-filtros', route: 'colaboradores.index', target: 'colaboradores-filtros', title: 'Filtros', body: 'Busca por nombre, sucursal, área, estatus y si tiene cuenta.' },
            { id: 'col-lista', route: 'colaboradores.index', target: 'colaboradores-lista', title: 'Colaboradores', body: 'Contadores y exportaciones usan el mismo alcance que este listado.' },
        ],
    },
    {
        id: 'usuarios',
        module: 'Usuarios',
        title: 'Recorrido: Usuarios',
        description: 'Cuentas de acceso y su rol.',
        icon: UserCog,
        match: 'usuarios.*',
        visibility: { views: ['usuarios'] },
        steps: [
            { id: 'usr-filtros', route: 'usuarios.index', target: 'usuarios-filtros', title: 'Filtros', body: 'Por nombre, correo, estado y rol.' },
            { id: 'usr-lista', route: 'usuarios.index', target: 'usuarios-lista', title: 'Cuentas', body: 'Solo puedes asignar roles con permisos que tú también tienes; nadie puede desactivar su propia cuenta.' },
        ],
    },
    {
        id: 'roles',
        module: 'Roles y permisos',
        title: 'Recorrido: Roles y permisos',
        description: 'Alcances, acciones y notificaciones por rol.',
        icon: KeyRound,
        match: 'roles.*',
        visibility: { anyOf: ['roles.ver'] },
        steps: [
            { id: 'rol-encabezado', route: 'roles.index', target: 'roles-encabezado', title: 'Roles', body: 'Cada rol define qué módulos ve cada persona, con qué alcance y qué puede hacer.' },
            { id: 'rol-lista', route: 'roles.index', target: 'roles-lista', title: 'Editar un rol', body: 'Por módulo eliges el alcance (Sin acceso, Propio, Sucursal, Corporativo, Global) y las acciones. Antes de guardar verás un resumen con lo que cambia.' },
        ],
    },
    listado('conceptos', 'Conceptos', 'conceptos.index', Tags, 'Clasificación del gasto.', 'Conceptos activos e inactivos.'),
    listado('proveedores', 'Proveedores', 'proveedores.index', Truck, 'A quién se paga.', 'Tus proveedores o los de todos, según tu alcance.'),
    {
        id: 'configuracion',
        module: 'Configuración',
        title: 'Recorrido: Configuración',
        description: 'Colores, logo y aplicación.',
        icon: Settings,
        match: 'configuracion.*',
        visibility: { anyOf: ['configuracion.ver', 'configuracion.administrar'] },
        steps: [
            { id: 'cfg-encabezado', route: 'configuracion.edit', target: 'configuracion-encabezado', title: 'Configuración', body: 'Ajusta colores, logo y paleta de gráficas con vista previa en claro y oscuro.' },
        ],
    },
    {
        id: 'bitacora',
        module: 'Bitácora',
        title: 'Recorrido: Bitácora',
        description: 'Quién hizo qué y cuándo.',
        icon: ScrollText,
        match: 'systemlogs.*',
        visibility: { views: ['logs'] },
        steps: [
            { id: 'log-filtros', route: 'systemlogs.index', target: 'bitacora-filtros', title: 'Filtros', body: 'Por módulo, acción, usuario, fechas o texto.' },
            { id: 'log-lista', route: 'systemlogs.index', target: 'bitacora-lista', title: 'Movimientos', body: 'Es de solo lectura; los datos sensibles nunca se muestran.' },
        ],
    },
    {
        id: 'ayuda',
        module: 'Ayuda',
        title: 'Recorrido: Centro de ayuda',
        description: 'Guía escrita y recorridos por módulo.',
        icon: CircleHelp,
        match: 'ayuda.*',
        visibility: {},
        steps: [
            { id: 'ayu-encabezado', route: 'ayuda.guia', target: 'ayuda-encabezado', title: 'Centro de ayuda', body: 'Inicia el recorrido completo o el de cada módulo.' },
            { id: 'ayu-buscador', route: 'ayuda.guia', target: 'ayuda-buscador', title: 'Buscador', body: 'Encuentra un tema por nombre o palabra clave.' },
            { id: 'ayu-modulos', route: 'ayuda.guia', target: 'ayuda-modulos', title: 'Módulos', body: 'Solo aparecen los módulos y acciones a los que tienes acceso; los ya vistos se marcan.' },
        ],
    },
]

/** Pasos visibles para el usuario según su regla. */
export const visibleSteps = (steps: TourStep[], visible: (r: NonNullable<TourStep['visibility']>) => boolean) =>
    steps.filter((s) => !s.visibility || visible(s.visibility))

export const tourById = (id: string) => TOURS.find((t) => t.id === id)
