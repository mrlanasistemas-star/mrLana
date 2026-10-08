import type { Component } from 'vue'
import {
    ArrowLeftRight,
    Banknote,
    Bell,
    BellRing,
    Building2,
    CircleHelp,
    ClipboardList,
    FileDown,
    FileText,
    KeyRound,
    LayoutDashboard,
    Layers3,
    MapPin,
    Monitor,
    Receipt,
    ScrollText,
    Send,
    Settings,
    Smartphone,
    Tags,
    Trash2,
    Truck,
    UserCog,
    UserRound,
    Users,
} from 'lucide-vue-next'

/**
 * Contenido de la Guía del sistema.
 *
 * Cada módulo declara qué lo hace visible: poder ver el módulo (`views`,
 * alcance resuelto en el servidor) o alguno de los permisos (`anyOf`). Si
 * aplica, indica los módulos del catálogo cuyo acceso se resume como «Tu
 * acceso» (`catalogo`) y el recorrido interactivo asociado (`tour`). Las
 * claves solo se usan para filtrar: la interfaz muestra siempre textos humanos.
 *
 * Los textos describen el comportamiento real del sistema; si cambia una
 * regla de negocio, actualiza el módulo correspondiente y el PDF
 * (`npm run guia:pdf`).
 */

/** Texto que solo aparece a quien tiene alguno de los permisos o puede ver alguno de los módulos. */
export type Condicional = { texto: string; anyOf?: string[]; views?: string[] }
export type Item = string | Condicional

export type Paso = { titulo: string; texto: string; anyOf?: string[]; views?: string[] }
export type Estado = { nombre: string; texto: string; tono: Tono }
export type Tono = 'gris' | 'azul' | 'ambar' | 'verde' | 'rojo' | 'violeta'

export type GuiaModulo = {
    id: string
    nombre: string
    grupo: string
    icono: Component
    resumen: string
    /** Visible si el usuario tiene alguno de estos permisos… */
    anyOf: string[]
    /** …o puede ver alguno de estos módulos. Sin ninguno = visible para todos. */
    views?: string[]
    /** Recorrido interactivo del módulo (registro de recorridos). */
    tour?: string
    /** Ruta del módulo (se enlaza solo si el usuario puede abrirlo). */
    ruta?: string
    /** Módulos del catálogo de permisos para el resumen «Tu acceso». */
    catalogo?: string[]
    paraQue: string
    quien: string
    pasos: Paso[]
    acciones: Item[]
    filtros?: Item[]
    estados?: Estado[]
    errores: Item[]
    advertencias: Item[]
    consejos: Item[]
    /** Palabras extra para el buscador. */
    claves?: string
}

export const GRUPOS = ['General', 'Operación', 'Organización', 'Personas y accesos', 'Catálogos', 'Sistema', 'Tu cuenta y la app'] as const

export const ESTADOS_REQUISICION: Estado[] = [
    { nombre: 'Borrador', tono: 'gris', texto: 'Guardada sin enviar. La edita y envía quien la creó, o quien puede editar cualquier borrador dentro de su alcance.' },
    { nombre: 'Capturada', tono: 'azul', texto: 'Enviada a revisión. Espera la autorización del pago; aquí todavía puede solicitarse su eliminación.' },
    { nombre: 'Pago autorizado', tono: 'violeta', texto: 'Se autorizó y programó la fecha de pago. Sigue así mientras haya pagos parciales.' },
    { nombre: 'Pagada', tono: 'verde', texto: 'Se registró el pago total. El solicitante ya puede cargar comprobantes.' },
    { nombre: 'Por comprobar', tono: 'ambar', texto: 'Tiene comprobantes cargados, pero lo aprobado aún no cubre el total.' },
    { nombre: 'Comprobación aceptada', tono: 'verde', texto: 'Los comprobantes aprobados cubren el total. El ciclo está completo.' },
    { nombre: 'Pago rechazado', tono: 'rojo', texto: 'Quien autoriza pagos rechazó el pago de una requisición capturada; el motivo queda en la bitácora y se avisa al solicitante.' },
    { nombre: 'Comprobación rechazada', tono: 'rojo', texto: 'Puede aparecer en requisiciones anteriores. Hoy, un comprobante rechazado regresa la requisición a «Por comprobar».' },
    { nombre: 'Eliminada', tono: 'rojo', texto: 'Baja lógica: no se borra, se conserva en la pestaña «Eliminadas» y en la bitácora.' },
]

export const MODULOS: GuiaModulo[] = [
    /* ------------------------------------------------------------------ General */
    {
        id: 'dashboard',
        nombre: 'Dashboard',
        grupo: 'General',
        icono: LayoutDashboard,
        resumen: 'Indicadores y gráficas de gasto en cuatro vistas: mi dashboard, mi sucursal, mi corporativo y general.',
        anyOf: [],
        views: ['dashboard'],
        tour: 'dashboard',
        ruta: 'dashboard',
        catalogo: ['dashboard', 'reportes'],
        paraQue: 'Es la vista inicial del sistema: resume montos, conteos por estatus y tendencias del alcance que elijas.',
        quien: 'El dashboard tiene sus propios permisos: «Ver mi dashboard de gastos» (solo lo tuyo), «Ver dashboard de mi sucursal», «Ver dashboard de mi corporativo» y «Ver dashboard general». Si tienes varios, cambias de vista con las pestañas superiores; la inicial es la más amplia.',
        pasos: [
            { titulo: 'Elige la vista', texto: 'Mi dashboard, Mi sucursal, Mi corporativo o General, según tus permisos.' },
            { titulo: 'Elige el periodo', texto: 'Usa «Este mes», «Mes anterior», «Últimos 30 o 90 días», «Este año» o un rango personalizado.' },
            { titulo: 'Aplica filtros', texto: 'Acota por corporativo, sucursal u otros filtros disponibles; los indicadores y gráficas se recalculan.' },
            { titulo: 'Revisa indicadores y gráficas', texto: 'Compara montos por estatus, tendencias y actividad para detectar pendientes.' },
            { titulo: 'Exporta si lo necesitas', texto: 'El PDF o Excel contiene exactamente la vista y los filtros que estás viendo.', anyOf: ['reportes.dashboard'] },
        ],
        acciones: [
            'Cambiar periodo y filtros.',
            'Abrir el detalle desde los indicadores y la actividad reciente.',
            { texto: 'Exportar el reporte del dashboard en PDF o Excel.', anyOf: ['reportes.dashboard'] },
        ],
        filtros: ['Periodo (predefinido o rango de fechas).', 'Corporativo y sucursal (solo los de tu vista).', 'Concepto y estatus.'],
        errores: [
            'Ver cifras «en cero»: normalmente el periodo no tiene movimientos; amplía el rango.',
            'Comparar reportes con filtros distintos: anota siempre el periodo y los filtros usados.',
            'No ver la pestaña de sucursal o corporativo: tu cuenta necesita un colaborador con sucursal asignada.',
        ],
        advertencias: ['Los borradores y las requisiciones eliminadas no se suman como gasto pagado.'],
        consejos: ['Para cierres y auditoría usa meses completos.', 'Los colores de las gráficas se ajustan en Configuración.'],
        claves: 'indicadores graficas kpi reporte periodo',
    },
    {
        id: 'notificaciones',
        nombre: 'Notificaciones',
        grupo: 'General',
        icono: Bell,
        resumen: 'Tus avisos en tiempo real de requisiciones, pagos, comprobantes, ajustes y seguridad.',
        anyOf: [],
        views: ['notificaciones'],
        tour: 'notificaciones',
        ruta: 'notificaciones.index',
        catalogo: ['notificaciones'],
        paraQue: 'Te avisa de lo que requiere tu atención: requisiciones enviadas, pagos autorizados o completados, comprobantes revisados, ajustes y cambios de cuentas.',
        quien: 'Personas con «Ver mis notificaciones»: la campana y este centro muestran solo tus avisos. Los temas que recibe cada rol se eligen en «Roles y permisos»; un tema nunca te deja abrir registros fuera de tu alcance. Además recibes las resoluciones de tus propias solicitudes.',
        pasos: [
            { titulo: 'Revisa la campana', texto: 'El contador de la barra superior muestra los avisos sin leer; ábrela para ver los más recientes.' },
            { titulo: 'Abre el centro de notificaciones', texto: 'Consulta el historial completo, filtrado por categoría y estado de lectura.' },
            { titulo: 'Entra al registro', texto: 'Cada aviso enlaza a la requisición o pantalla relacionada.' },
            { titulo: 'Márcalas como leídas', texto: 'Una por una o todas a la vez con «Marcar todas».' },
        ],
        acciones: ['Abrir el registro relacionado.', 'Marcar una o todas como leídas.', 'Filtrar por categoría y por leídas/no leídas.'],
        filtros: ['Categoría: Requisiciones, Pagos, Comprobaciones, Ajustes, Usuarios y seguridad, Sistema.', 'Estado de lectura.'],
        errores: [
            'No llegan avisos en tiempo real: recarga la página; si la conexión en vivo se interrumpe, el sistema vuelve a consultar cada cierto tiempo.',
            'Esperar avisos de un tema que tu rol no recibe: pide al administrador que lo active en tu rol.',
        ],
        advertencias: ['Algunos avisos también se envían por correo; si no te llegan, revisa la carpeta de spam.'],
        consejos: ['Atiende primero los avisos marcados como «Importante» o «Atención».'],
        claves: 'campana avisos alertas tiempo real correo',
    },

    /* ---------------------------------------------------------------- Operación */
    {
        id: 'requisiciones',
        nombre: 'Requisiciones',
        grupo: 'Operación',
        icono: FileText,
        resumen: 'Solicitudes de compra o gasto: listado, búsqueda, detalle, estatus y PDF.',
        anyOf: [],
        views: ['requisiciones'],
        tour: 'requisiciones',
        ruta: 'requisiciones.index',
        catalogo: ['requisiciones'],
        paraQue: 'Concentra todas las solicitudes de gasto y su avance: desde el borrador hasta la comprobación completa.',
        quien: 'Depende de tu alcance: «Ver mis requisiciones» (las que creaste o solicitaste), «de mi sucursal», «de mi corporativo» o «Ver todas las requisiciones». Un alcance mayor incluye lo tuyo.',
        pasos: [
            { titulo: 'Elige la pestaña', texto: '«Activas», «Borradores», «Capturadas» o «Eliminadas». Por defecto se muestran 20 registros por página.' },
            { titulo: 'Busca y filtra', texto: 'Busca por folio, proveedor, concepto, sucursal u observaciones y combina filtros.' },
            { titulo: 'Abre el detalle', texto: 'Desde el folio ves items, proveedor, pagos, comprobantes, ajustes e historial.' },
            { titulo: 'Da seguimiento', texto: 'Los botones de cada tarjeta (Ver, Pagos, Comprobaciones, Ajustes, PDF) son enlaces: con clic derecho → «Abrir en otra pestaña» o Ctrl + clic abres varias requisiciones a la vez.' },
        ],
        acciones: [
            'Ver el detalle y copiar el folio.',
            'Abrir varias requisiciones en pestañas distintas.',
            { texto: 'Descargar o imprimir el PDF de una requisición.', anyOf: ['requisiciones.imprimir'] },
            { texto: 'Crear una requisición nueva.', anyOf: ['requisiciones.registrar'] },
            { texto: 'Editar y enviar tus borradores.', anyOf: ['requisiciones.editar'] },
            { texto: 'Editar y enviar borradores de otras personas dentro de tu alcance.', anyOf: ['requisiciones.editar_cualquiera'] },
            { texto: 'Exportar el listado filtrado a PDF o Excel.', anyOf: ['requisiciones.exportar'] },
            { texto: 'Eliminar requisiciones (baja lógica).', anyOf: ['requisiciones.eliminar'] },
            { texto: 'Solicitar la eliminación de una requisición capturada.', anyOf: ['requisiciones.solicitar_eliminacion'] },
        ],
        filtros: [
            'Búsqueda libre.',
            'Estatus, corporativo, sucursal, solicitante, concepto y proveedor.',
            'Fecha de registro y fecha de pago (desde / hasta).',
            'Orden y registros por página.',
        ],
        estados: ESTADOS_REQUISICION,
        errores: [
            'No encontrar una requisición: revisa la pestaña (las eliminadas están aparte) y limpia los filtros.',
            'No ver el botón de editar: solo los borradores se editan; una requisición enviada se corrige con un ajuste de monto.',
        ],
        advertencias: ['Las requisiciones no se borran físicamente: la eliminación es lógica y queda en bitácora.'],
        consejos: ['Comparte siempre el folio al pedir apoyo.', 'Usa la exportación con los mismos filtros que revisaste en pantalla.'],
        claves: 'folio listado pestañas compra gasto',
    },
    {
        id: 'requisiciones-crear',
        nombre: 'Crear, guardar y enviar',
        grupo: 'Operación',
        icono: Send,
        resumen: 'Cómo capturar una requisición, guardarla como borrador y enviarla a revisión.',
        anyOf: ['requisiciones.registrar'],
        ruta: 'requisiciones.registrar',
        catalogo: ['requisiciones'],
        paraQue: 'Registrar un gasto con todos sus datos para que pueda autorizarse y pagarse.',
        quien: 'Personas con permiso para registrar requisiciones. Guardar borradores y enviar son permisos aparte. Sin permisos especiales, el solicitante es tu colaborador y la sucursal y el comprador son los suyos (se muestran bloqueados).',
        pasos: [
            { titulo: 'Abre «Nueva»', texto: 'Desde Requisiciones, o desde una plantilla para precargar datos.' },
            { titulo: 'Revisa solicitante, sucursal y comprador', texto: 'Se cargan solos con los datos de tu colaborador.' },
            { titulo: 'Elige otra sucursal de tu corporativo', texto: 'Solo aparecen las sucursales activas de tu corporativo.', anyOf: ['requisiciones.elegir_sucursal_corporativo'] },
            { titulo: 'Elige otro corporativo', texto: 'Al cambiar de corporativo se limpia la sucursal si ya no le pertenece.', anyOf: ['requisiciones.elegir_corporativo'] },
            { titulo: 'Captura a nombre de otra persona', texto: 'Elige el solicitante entre los colaboradores de las sucursales que puedes elegir. Si es de otra sucursal, usa «Usar datos del solicitante». Quedas registrado como quien capturó.', anyOf: ['requisiciones.elegir_solicitante'] },
            { titulo: 'Completa el encabezado', texto: 'Concepto y proveedor activo son obligatorios. La fecha de requisición es hoy o posterior.' },
            { titulo: 'Fecha esperada de pago (opcional)', texto: 'Si la indicas, no puede ser anterior a la fecha de requisición.' },
            { titulo: 'Agrega los items', texto: 'Al menos una, con cantidad mayor a 0, descripción, precio unitario e indicación de si genera IVA. Los totales se calculan solos.' },
            { titulo: 'Guarda o envía', texto: '«Guardar» la deja como Borrador para seguir después. «Enviar» la pasa a Capturada y avisa a quien revisa.' },
        ],
        acciones: [
            { texto: 'Guardar como borrador.', anyOf: ['requisiciones.guardar_borrador'] },
            { texto: 'Enviar a revisión.', anyOf: ['requisiciones.enviar'] },
            'Partir de una plantilla.',
            { texto: 'Editar un borrador antes de enviarlo.', anyOf: ['requisiciones.editar'] },
        ],
        errores: [
            '«Selecciona un proveedor activo»: el proveedor es obligatorio para guardar y para enviar, y debe estar activo.',
            'Fecha de requisición en el pasado: el sistema solo acepta hoy o fechas posteriores.',
            'Fecha esperada de pago anterior a la fecha de requisición.',
            'Items sin descripción o con cantidad en cero.',
            '«Tu usuario no está vinculado a un colaborador»: pide a un administrador que vincule tu cuenta.',
        ],
        advertencias: [
            'Una vez enviada ya no se edita: los cambios de monto se piden con un ajuste.',
            'Revisa los datos bancarios del proveedor antes de enviar; se usan para pagar.',
        ],
        consejos: [
            'Guarda como borrador si te falta información y envíala cuando esté completa.',
            'Para gastos que se repiten, crea una plantilla.',
            'Usa «Observaciones» para dar contexto a quien autoriza.',
        ],
        claves: 'nueva capturar borrador enviar items productos iva proveedor fecha',
    },
    {
        id: 'ajustes',
        nombre: 'Ajustes de monto',
        grupo: 'Operación',
        icono: ArrowLeftRight,
        resumen: 'Devoluciones, faltantes e incrementos autorizados sobre el monto de una requisición.',
        anyOf: ['ajustes.solicitar', 'ajustes.solicitar_cualquiera', 'ajustes.autorizar', 'ajustes.rechazar', 'ajustes.aplicar'],
        views: ['ajustes'],
        ruta: 'requisiciones.index',
        catalogo: ['ajustes'],
        paraQue: 'Corregir el total de una requisición ya enviada sin editarla, con autorización y rastro completo.',
        quien: 'Los ajustes heredan el alcance de la requisición: de tus requisiciones, de tu sucursal, de tu corporativo o todos. Solicitar, autorizar, rechazar y aplicar son permisos separados.',
        pasos: [
            { titulo: 'Abre la requisición', texto: 'En el detalle entra a «Ajustes».' },
            { titulo: 'Solicita el ajuste', texto: 'Elige el tipo (devolución, faltante o incremento autorizado), el monto y el motivo.', anyOf: ['ajustes.solicitar', 'ajustes.solicitar_cualquiera'] },
            { titulo: 'Autoriza o rechaza', texto: 'Quien revisa aprueba o rechaza; al rechazar debe escribir el motivo.', anyOf: ['ajustes.autorizar', 'ajustes.rechazar'] },
            { titulo: 'Aplica el ajuste', texto: 'Un ajuste aprobado se aplica y cambia el total de la requisición.', anyOf: ['ajustes.aplicar'] },
        ],
        acciones: [
            'Consultar el historial de ajustes con monto anterior y nuevo.',
            { texto: 'Solicitar ajustes en tus requisiciones o cancelar uno pendiente propio.', anyOf: ['ajustes.solicitar'] },
            { texto: 'Solicitar ajustes en requisiciones de otras personas dentro de tu alcance.', anyOf: ['ajustes.solicitar_cualquiera'] },
            { texto: 'Autorizar ajustes.', anyOf: ['ajustes.autorizar'] },
            { texto: 'Rechazar ajustes.', anyOf: ['ajustes.rechazar'] },
            { texto: 'Aplicar ajustes aprobados.', anyOf: ['ajustes.aplicar'] },
        ],
        estados: [
            { nombre: 'Pendiente', tono: 'ambar', texto: 'Esperando revisión.' },
            { nombre: 'Aprobado', tono: 'azul', texto: 'Autorizado, falta aplicarlo.' },
            { nombre: 'Aplicado', tono: 'verde', texto: 'El total de la requisición ya cambió.' },
            { nombre: 'Rechazado', tono: 'rojo', texto: 'No procede; se conserva el motivo.' },
            { nombre: 'Cancelado', tono: 'gris', texto: 'Lo retiró quien lo solicitó.' },
        ],
        errores: [
            'No aparece el botón para solicitar: la requisición debe estar enviada (no borrador ni eliminada).',
            'Monto en cero: el ajuste debe ser mayor a 0.',
        ],
        advertencias: ['Una devolución reduce el total; un faltante o incremento lo aumenta. Revisa el sentido antes de aplicar.'],
        consejos: ['Explica en el motivo el origen del ajuste (ticket, nota o acuerdo).'],
        claves: 'devolucion faltante incremento monto corregir',
    },
    {
        id: 'eliminaciones',
        nombre: 'Solicitudes de eliminación',
        grupo: 'Operación',
        icono: Trash2,
        resumen: 'Pedir y autorizar la baja de una requisición enviada por error.',
        anyOf: ['requisiciones.solicitar_eliminacion', 'requisiciones.autorizar_eliminacion', 'requisiciones.eliminar'],
        ruta: 'requisiciones.index',
        catalogo: ['requisiciones'],
        paraQue: 'Dar de baja una requisición capturada que no debe seguir su curso, con autorización de otra persona.',
        quien: 'Quien puede solicitar eliminación la pide; quien puede autorizar eliminación la aprueba o rechaza.',
        pasos: [
            { titulo: 'Solicita', texto: 'En el detalle de una requisición «Capturada», elige solicitar eliminación y escribe el motivo (mínimo 5 caracteres).', anyOf: ['requisiciones.solicitar_eliminacion'] },
            { titulo: 'Espera la revisión', texto: 'La requisición muestra que tiene una solicitud pendiente; puedes cancelarla mientras no se resuelva.', anyOf: ['requisiciones.solicitar_eliminacion'] },
            { titulo: 'Revisa', texto: 'Aprueba (la requisición pasa a «Eliminada») o rechaza con un comentario.', anyOf: ['requisiciones.autorizar_eliminacion'] },
        ],
        acciones: [
            { texto: 'Solicitar o cancelar una solicitud de eliminación.', anyOf: ['requisiciones.solicitar_eliminacion'] },
            { texto: 'Aprobar o rechazar solicitudes.', anyOf: ['requisiciones.autorizar_eliminacion'] },
            { texto: 'Eliminar directamente (baja lógica).', anyOf: ['requisiciones.eliminar'] },
        ],
        estados: [
            { nombre: 'Pendiente', tono: 'ambar', texto: 'Esperando revisión.' },
            { nombre: 'Aprobada', tono: 'rojo', texto: 'La requisición quedó eliminada.' },
            { nombre: 'Rechazada', tono: 'gris', texto: 'La requisición continúa su curso.' },
        ],
        errores: [
            'Solo se puede solicitar en requisiciones «Capturadas» (sin autorizar ni pagar).',
            'Solo puede haber una solicitud pendiente por requisición.',
        ],
        advertencias: ['Una requisición con pago autorizado o pagada ya no se elimina; usa un ajuste.'],
        consejos: ['Si es un borrador, no necesitas solicitud: corrígelo o descártalo antes de enviarlo.'],
        claves: 'borrar baja cancelar eliminar requisicion',
    },
    {
        id: 'plantillas',
        nombre: 'Plantillas',
        grupo: 'Operación',
        icono: ClipboardList,
        resumen: 'Requisiciones frecuentes guardadas para crearlas en segundos.',
        anyOf: [],
        views: ['plantillas'],
        tour: 'plantillas',
        ruta: 'plantillas.index',
        catalogo: ['plantillas'],
        paraQue: 'Guardar encabezado e items de gastos recurrentes (renta, servicios, insumos) para no capturarlos cada vez.',
        quien: 'Con «Ver mis plantillas» solo las tuyas; con «Ver todas las plantillas», las de todas las personas. Al crear o editar se aplican tus mismos permisos de captura: una plantilla no permite elegir solicitante, sucursal o corporativo que no podrías elegir en una requisición.',
        pasos: [
            { titulo: 'Crea la plantilla', texto: 'Captura corporativo, sucursal, solicitante, proveedor, concepto e items.', anyOf: ['plantillas.registrar'] },
            { titulo: 'Úsala', texto: 'Desde el listado elige «Usar» para abrir una requisición nueva con los datos precargados.' },
            { titulo: 'Revisa y envía', texto: 'Ajusta fecha, cantidades o precios y guarda o envía como cualquier requisición.' },
        ],
        acciones: [
            'Usar una plantilla para crear una requisición.',
            { texto: 'Crear plantillas.', anyOf: ['plantillas.registrar'] },
            { texto: 'Editar tus plantillas.', anyOf: ['plantillas.editar'] },
            { texto: 'Dar de baja y reactivar tus plantillas.', anyOf: ['plantillas.eliminar'] },
            { texto: 'Editar o dar de baja plantillas de otras personas.', anyOf: ['plantillas.editar_cualquiera', 'plantillas.eliminar_cualquiera'] },
        ],
        errores: ['La requisición creada desde una plantilla valida todo de nuevo: si el proveedor ya no está activo, elige otro.'],
        advertencias: ['Cambiar una plantilla no modifica las requisiciones creadas antes con ella.'],
        consejos: ['Nombra las plantillas por gasto y sucursal, por ejemplo «Renta · Sucursal Centro».'],
        claves: 'recurrente repetir precargar',
    },
    {
        id: 'pagos',
        nombre: 'Pagos',
        grupo: 'Operación',
        icono: Banknote,
        resumen: 'Autorización, rechazo, registro y consulta de pagos con su comprobante.',
        anyOf: [],
        views: ['pagos'],
        tour: 'pagos',
        ruta: 'pagos.index',
        catalogo: ['pagos'],
        paraQue: 'Programar y registrar los pagos de cada requisición y consultar todos los pagos con su comprobante bancario.',
        quien: 'Los pagos heredan el alcance de la requisición: «Ver pagos de mis requisiciones», «de mi sucursal», «de mi corporativo» o «Ver todos los pagos». Autorizar, rechazar, registrar y descargar comprobantes son permisos aparte.',
        pasos: [
            { titulo: 'Autoriza el pago', texto: 'En una requisición «Capturada», indica la fecha programada y autoriza. Queda registrado quién autorizó.', anyOf: ['pagos.autorizar'] },
            { titulo: 'O recházalo', texto: 'Si no procede, rechaza con un motivo; el solicitante recibe el aviso.', anyOf: ['pagos.rechazar'] },
            { titulo: 'Registra el pago', texto: 'En «Pagar», captura tipo, monto, fecha real, referencia y sube el comprobante (PDF o imagen, hasta 12 MB). Se permiten pagos parciales.', anyOf: ['pagos.registrar'] },
            { titulo: 'Consulta', texto: 'En el módulo Pagos filtra, abre la vista previa del comprobante o descárgalo.' },
        ],
        acciones: [
            { texto: 'Ver la vista previa y descargar el comprobante del pago.', anyOf: ['pagos.descargar'] },
            'Abrir la requisición relacionada.',
            { texto: 'Autorizar pagos.', anyOf: ['pagos.autorizar'] },
            { texto: 'Rechazar pagos con motivo.', anyOf: ['pagos.rechazar'] },
            { texto: 'Definir la fecha general de pago cuando el total está cubierto.', anyOf: ['pagos.editar'] },
            { texto: 'Registrar pagos totales o parciales.', anyOf: ['pagos.registrar'] },
            { texto: 'Exportar el listado a PDF o Excel.', anyOf: ['pagos.exportar'] },
        ],
        filtros: [
            'Búsqueda por folio, beneficiario o referencia.',
            'Requisición, solicitante, quien registró el pago y quien lo autorizó.',
            'Tipo de pago: transferencia, efectivo, tarjeta, cheque u otro.',
            'Corporativo y fecha de pago (desde / hasta).',
        ],
        errores: [
            '«El monto excede lo pendiente»: la suma de pagos no puede superar el total de la requisición.',
            'Archivo no permitido: solo PDF, JPG, PNG o WebP.',
            '«Autorizó: No registrado» en pagos antiguos: antes no se guardaba ese dato; no es un error.',
        ],
        advertencias: ['Con el pago total la requisición pasa a «Pagada» y se avisa al solicitante para que compruebe.'],
        consejos: ['Captura la referencia bancaria: facilita conciliar.', 'Revisa la vista previa antes de guardar para confirmar que subiste el archivo correcto.'],
        claves: 'autorizar transferencia efectivo cheque tarjeta parcial comprobante bancario',
    },
    {
        id: 'comprobantes',
        nombre: 'Comprobantes',
        grupo: 'Operación',
        icono: Receipt,
        resumen: 'Facturas, tickets y notas que justifican cada gasto, con su revisión.',
        anyOf: [],
        views: ['comprobaciones'],
        tour: 'comprobantes',
        ruta: 'comprobantes.index',
        catalogo: ['comprobaciones'],
        paraQue: 'Comprobar en qué se gastó el dinero de cada requisición y llevar el control de lo aprobado y lo pendiente.',
        quien: 'Las comprobaciones heredan el alcance de la requisición (mis requisiciones, mi sucursal, mi corporativo o todas). Subir, aceptar, rechazar y eliminar son permisos aparte.',
        pasos: [
            { titulo: 'Abre «Comprobar»', texto: 'En el detalle de una requisición pagada entra a la pantalla de comprobación.', anyOf: ['comprobaciones.subir', 'comprobaciones.subir_cualquiera'] },
            { titulo: 'Sube el archivo', texto: 'Elige tipo (factura, ticket, nota u otro), fecha de emisión y monto. Formatos: PDF, imagen, Excel o Word, hasta 10 MB.', anyOf: ['comprobaciones.subir', 'comprobaciones.subir_cualquiera'] },
            { titulo: 'Revisa', texto: 'Acepta o rechaza cada comprobante; al rechazar escribe el motivo para que el solicitante lo corrija.', anyOf: ['comprobaciones.aceptar', 'comprobaciones.rechazar'] },
            { titulo: 'Consulta', texto: 'En el módulo Comprobantes ve la vista previa en tarjeta o en grande, descarga y filtra.' },
        ],
        acciones: [
            'Vista previa (imágenes y PDF) y descarga.',
            'Abrir la requisición relacionada.',
            { texto: 'Subir comprobantes a tus requisiciones.', anyOf: ['comprobaciones.subir'] },
            { texto: 'Subir comprobantes a requisiciones de otras personas dentro de tu alcance.', anyOf: ['comprobaciones.subir_cualquiera'] },
            { texto: 'Aceptar comprobantes.', anyOf: ['comprobaciones.aceptar'] },
            { texto: 'Rechazar comprobantes.', anyOf: ['comprobaciones.rechazar'] },
            { texto: 'Eliminar los comprobantes que cargaste y aún no se aprueban.', anyOf: ['comprobaciones.eliminar_propios'] },
            { texto: 'Eliminar cualquier comprobante dentro de tu alcance.', anyOf: ['comprobaciones.eliminar'] },
            { texto: 'Editar folios de factura.', anyOf: ['comprobaciones.administrar_folios'] },
            { texto: 'Exportar el listado a PDF o Excel.', anyOf: ['comprobaciones.exportar'] },
        ],
        filtros: ['Búsqueda por folio, proveedor, archivo o comentario.', 'Requisición, solicitante, estatus, tipo, quien cargó y quien revisó.', 'Fecha (desde / hasta).'],
        estados: [
            { nombre: 'Pendiente', tono: 'ambar', texto: 'Cargado, sin revisar.' },
            { nombre: 'Aprobado', tono: 'verde', texto: 'Cuenta para cubrir el total de la requisición.' },
            { nombre: 'Rechazado', tono: 'rojo', texto: 'No cuenta; revisa el comentario y sube uno corregido.' },
        ],
        errores: [
            '«El monto supera lo pendiente»: la suma de comprobantes no rechazados no puede exceder el total.',
            'Archivo de más de 10 MB o en un formato no permitido.',
            'No poder abrir un archivo: solo se muestran archivos de requisiciones a las que tienes acceso.',
        ],
        advertencias: ['Subir comprobantes no exige que el proveedor siga activo, para poder comprobar requisiciones anteriores.'],
        consejos: ['Sube archivos legibles y nombrados con el folio.', 'Cuando lo aprobado cubre el total, la requisición pasa a «Comprobación aceptada».'],
        claves: 'factura ticket nota comprobar evidencia vista previa',
    },

    /* ------------------------------------------------------------- Organización */
    catalogoSimple('corporativos', 'Corporativos', 'Organización', Building2, 'Empresas o razones sociales que compran.', 'Registrar las empresas del grupo que realizan las compras; cada sucursal pertenece a un corporativo.', [
        'Dar de baja un corporativo impide usarlo en registros nuevos y en sus sucursales; el historial se conserva.',
    ]),
    catalogoSimple('sucursales', 'Sucursales', 'Organización', MapPin, 'Ubicaciones de cada corporativo.', 'Definir dónde se origina cada gasto. Las requisiciones y los colaboradores se asignan a una sucursal.', [
        'No puedes reactivar una sucursal si su corporativo está dado de baja.',
    ]),
    catalogoSimple('areas', 'Áreas', 'Organización', Layers3, 'Departamentos o áreas de trabajo.', 'Clasificar a los colaboradores por área dentro de su corporativo.', [
        'Un colaborador no puede asignarse a un área dada de baja.',
    ]),

    /* ------------------------------------------------------- Personas y accesos */
    {
        id: 'colaboradores',
        nombre: 'Colaboradores',
        grupo: 'Personas y accesos',
        icono: Users,
        resumen: 'Personas de la organización, con o sin cuenta de acceso.',
        anyOf: [],
        views: ['colaboradores'],
        tour: 'colaboradores',
        ruta: 'colaboradores.index',
        catalogo: ['colaboradores'],
        paraQue: 'Registrar a las personas que solicitan gastos. Un colaborador puede existir sin cuenta; la cuenta se crea en Usuarios.',
        quien: 'Según tu alcance: tu propio registro, tu sucursal, tu corporativo o todos. Búsquedas, contadores y exportaciones usan el mismo alcance.',
        pasos: [
            { titulo: 'Registra', texto: 'Nombre, apellidos, correo, puesto, sucursal y área.', anyOf: ['colaboradores.registrar'] },
            { titulo: 'Vincula una cuenta (opcional)', texto: 'Si la persona usará el sistema, créale un usuario en Usuarios y vincúlalo.' },
            { titulo: 'Da de baja', texto: 'Cuando la persona deja la organización.', anyOf: ['colaboradores.desactivar'] },
        ],
        acciones: [
            'Buscar y filtrar por sucursal, área y si tiene cuenta.',
            { texto: 'Registrar colaboradores.', anyOf: ['colaboradores.registrar'] },
            { texto: 'Editar colaboradores.', anyOf: ['colaboradores.editar'] },
            { texto: 'Dar de baja (también desactiva su cuenta).', anyOf: ['colaboradores.desactivar'] },
            { texto: 'Reactivar colaboradores.', anyOf: ['colaboradores.reactivar'] },
            { texto: 'Exportar a PDF o Excel.', anyOf: ['colaboradores.exportar'] },
        ],
        errores: ['No se pueden vincular dos cuentas al mismo colaborador.', 'La sucursal o el área deben estar activas.'],
        advertencias: [
            'Dar de baja a un colaborador desactiva también su cuenta de acceso: ya no podrá iniciar sesión y se cierran sus sesiones abiertas.',
            'Reactivar al colaborador no reactiva su cuenta; eso se hace en Usuarios.',
            'No puedes dar de baja al colaborador vinculado a tu propia cuenta ni dejar al sistema sin administradores.',
        ],
        consejos: ['Antes de registrar, busca para no duplicar personas.'],
        claves: 'empleados personas baja personal',
    },
    {
        id: 'usuarios',
        nombre: 'Usuarios',
        grupo: 'Personas y accesos',
        icono: UserCog,
        resumen: 'Cuentas de acceso: alta, rol, activación y contraseñas.',
        anyOf: [],
        views: ['usuarios'],
        tour: 'usuarios',
        ruta: 'usuarios.index',
        catalogo: ['usuarios'],
        paraQue: 'Dar y quitar acceso al sistema. Cada cuenta tiene un rol que define qué puede ver y hacer.',
        quien: 'Según tu alcance: usuarios de tu sucursal, de tu corporativo o todos. Registrar, editar, cambiar el rol, desactivar, reactivar y restablecer contraseñas son permisos aparte. Solo puedes asignar roles con permisos que tú también tienes.',
        pasos: [
            { titulo: 'Crea la cuenta', texto: 'Nombre, correo, rol y, si aplica, el colaborador vinculado. La persona recibe su acceso por correo.', anyOf: ['usuarios.registrar'] },
            { titulo: 'Cambia el rol', texto: 'Edita la cuenta y elige otro rol; el cambio aplica en su siguiente acción.', anyOf: ['usuarios.cambiar_rol'] },
            { titulo: 'Desactiva', texto: 'La persona deja de poder entrar de inmediato y se cierran sus sesiones abiertas.', anyOf: ['usuarios.desactivar'] },
        ],
        acciones: [
            'Buscar y filtrar por estado y rol.',
            { texto: 'Registrar cuentas.', anyOf: ['usuarios.registrar'] },
            { texto: 'Editar nombre, correo y colaborador vinculado.', anyOf: ['usuarios.editar'] },
            { texto: 'Cambiar el rol de una cuenta.', anyOf: ['usuarios.cambiar_rol'] },
            { texto: 'Consultar qué colaborador tiene cada cuenta.', anyOf: ['usuarios.ver_vinculo'] },
            { texto: 'Desactivar cuentas.', anyOf: ['usuarios.desactivar'] },
            { texto: 'Reactivar cuentas.', anyOf: ['usuarios.reactivar'] },
            { texto: 'Enviar una contraseña temporal por correo.', anyOf: ['usuarios.restablecer_contrasena'] },
        ],
        filtros: ['Búsqueda por nombre o correo.', 'Estado: activos o inactivos.', 'Rol.'],
        estados: [
            { nombre: 'Activa', tono: 'verde', texto: 'Puede iniciar sesión.' },
            { nombre: 'Inactiva', tono: 'gris', texto: 'No puede iniciar sesión; su historial se conserva.' },
        ],
        errores: [
            'Correo ya registrado: cada cuenta usa un correo único.',
            '«Esta acción dejaría al sistema sin ningún administrador activo»: asigna antes la administración a otra cuenta.',
            'No puedes desactivar tu propia cuenta.',
        ],
        advertencias: ['Si el correo no sale, la contraseña anterior se conserva para no dejar a la persona sin acceso.'],
        consejos: ['Desactiva en lugar de borrar: conserva la trazabilidad.', 'Revisa periódicamente las cuentas inactivas y sus roles.'],
        claves: 'cuenta acceso contraseña desactivar login',
    },
    {
        id: 'roles',
        nombre: 'Roles y permisos',
        grupo: 'Personas y accesos',
        icono: KeyRound,
        resumen: 'Qué puede ver y hacer cada rol, y qué notificaciones recibe.',
        anyOf: ['roles.ver'],
        tour: 'roles',
        ruta: 'roles.index',
        catalogo: ['roles'],
        paraQue: 'Definir permisos por módulo para cada rol, incluidos roles personalizados, y los temas de notificación que recibe.',
        quien: 'Personas con permiso para ver roles. Crear, editar y eliminar roles son permisos aparte.',
        pasos: [
            { titulo: 'Crea o abre un rol', texto: 'Dale un nombre y una descripción clara.', anyOf: ['roles.registrar', 'roles.editar'] },
            { titulo: 'Elige el alcance de cada módulo', texto: 'Sin acceso, Propio, Sucursal, Corporativo o Global. Elegir uno incluye los inferiores.', anyOf: ['roles.editar', 'roles.registrar'] },
            { titulo: 'Marca las acciones', texto: 'Agrupadas en operación, captura especial, administración y exportación. Las sensibles están señaladas.', anyOf: ['roles.editar', 'roles.registrar'] },
            { titulo: 'Revisa el resumen', texto: 'Antes de guardar verás módulos, alcances globales, acciones sensibles y qué perderán los usuarios del rol.', anyOf: ['roles.editar', 'roles.registrar'] },
            { titulo: 'Elige notificaciones', texto: 'Indica qué temas recibe el rol o si recibe todos.', anyOf: ['roles.editar'] },
            { titulo: 'Asigna el rol', texto: 'Desde Usuarios, a cada cuenta.' },
        ],
        acciones: [
            'Consultar roles y sus permisos.',
            { texto: 'Crear roles personalizados.', anyOf: ['roles.registrar'] },
            { texto: 'Editar permisos y notificaciones.', anyOf: ['roles.editar'] },
            { texto: 'Eliminar roles.', anyOf: ['roles.eliminar'] },
        ],
        errores: ['El sistema impide quitar permisos de administración si nadie más los conserva.'],
        advertencias: ['Los cambios afectan a todas las cuentas con ese rol.', 'Ocultar un botón no es seguridad: el sistema vuelve a validar cada acción con estos permisos.'],
        consejos: ['Da el mínimo necesario y amplía después.', 'Una acción nunca da acceso global por sí sola: si necesita ver registros, se asigna el alcance mínimo.', 'Usa el filtro «Con advertencias» para revisar accesos globales y acciones sensibles.'],
        claves: 'permisos rol perfil acceso personalizado',
    },

    /* ---------------------------------------------------------------- Catálogos */
    catalogoSimple('conceptos', 'Conceptos', 'Catálogos', Tags, 'Clasificación del gasto.', 'Clasificar cada requisición (papelería, servicios, viáticos…) para reportes y gráficas.', [
        'Un concepto dado de baja ya no aparece al crear requisiciones; las anteriores lo conservan.',
    ]),
    {
        id: 'proveedores',
        nombre: 'Proveedores',
        grupo: 'Catálogos',
        icono: Truck,
        resumen: 'A quién se paga: razón social, RFC y datos bancarios.',
        anyOf: [],
        views: ['proveedores'],
        tour: 'proveedores',
        ruta: 'proveedores.index',
        catalogo: ['proveedores'],
        paraQue: 'Mantener los datos de pago de cada proveedor. Toda requisición necesita un proveedor activo.',
        quien: 'Con «Ver mis proveedores», los que registraste; con «Ver todos los proveedores», los de todas las personas. «Utilizar cualquier proveedor activo» permite elegirlos al capturar sin poder administrarlos.',
        pasos: [
            { titulo: 'Busca primero', texto: 'Por razón social o RFC, para no duplicar.' },
            { titulo: 'Registra', texto: 'Razón social, RFC, banco y CLABE interbancaria.', anyOf: ['proveedores.registrar'] },
            { titulo: 'Úsalo', texto: 'Ya aparece al crear requisiciones mientras esté activo.' },
        ],
        acciones: [
            'Buscar y filtrar por estatus.',
            { texto: 'Registrar proveedores.', anyOf: ['proveedores.registrar'] },
            { texto: 'Editar datos.', anyOf: ['proveedores.editar'] },
            { texto: 'Dar de baja y reactivar.', anyOf: ['proveedores.desactivar', 'proveedores.reactivar'] },
            { texto: 'Exportar a PDF o Excel.', anyOf: ['proveedores.exportar'] },
        ],
        errores: ['CLABE incorrecta: verifica sus 18 dígitos.', 'No aparece al crear una requisición: el proveedor está dado de baja.'],
        advertencias: ['Cambiar la CLABE afecta los pagos futuros; confírmala con el proveedor.'],
        consejos: ['Captura la razón social tal como aparece en sus facturas.'],
        claves: 'rfc clabe banco razon social',
    },

    /* ------------------------------------------------------------------ Sistema */
    {
        id: 'configuracion',
        nombre: 'Configuración',
        grupo: 'Sistema',
        icono: Settings,
        resumen: 'Colores, logo, paleta de gráficas e instalación de la aplicación.',
        anyOf: ['configuracion.ver', 'configuracion.administrar'],
        tour: 'configuracion',
        ruta: 'configuracion.edit',
        catalogo: ['configuracion'],
        paraQue: 'Adaptar la apariencia del ERP a la marca y definir cómo se instala la aplicación.',
        quien: 'Quien puede ver la configuración la consulta; solo quien puede administrarla guarda cambios.',
        pasos: [
            { titulo: 'Colores del sistema', texto: 'Principal, acento, botones, éxito, advertencia y peligro, con vista previa en claro y oscuro.' },
            { titulo: 'Logo', texto: 'PNG, JPG o WebP de hasta 2 MB; se muestra en el menú lateral.' },
            { titulo: 'Gráficas', texto: 'Paleta de colores HEX de 6 dígitos (por ejemplo #2563EB).' },
            { titulo: 'Aplicación', texto: 'Déjalo vacío para instalar la aplicación web (recomendado) o indica una URL de instalador propio.' },
            { titulo: 'Guarda', texto: 'Los cambios se aplican a todos los usuarios.', anyOf: ['configuracion.administrar'] },
        ],
        acciones: ['Previsualizar colores.', { texto: 'Guardar, descartar cambios o restaurar valores predeterminados.', anyOf: ['configuracion.administrar'] }],
        errores: ['Color con formato inválido: usa # y 6 dígitos hexadecimales.', 'Logo demasiado pesado (más de 2 MB).'],
        advertencias: ['Elige colores con buen contraste: el sistema calcula el color de texto, pero tonos muy claros dificultan la lectura.'],
        consejos: ['Revisa la vista previa en modo claro y oscuro antes de guardar.'],
        claves: 'colores logo marca tema graficas paleta',
    },
    {
        id: 'bitacora',
        nombre: 'Bitácora',
        grupo: 'Sistema',
        icono: ScrollText,
        resumen: 'Quién hizo qué, cuándo y qué cambió, campo por campo.',
        anyOf: [],
        views: ['logs'],
        tour: 'bitacora',
        ruta: 'systemlogs.index',
        catalogo: ['logs'],
        paraQue: 'Auditar altas, cambios, bajas lógicas, reactivaciones y cambios de estatus en todo el sistema.',
        quien: 'Según tu alcance: tu actividad, la de tu sucursal, la de tu corporativo o toda la bitácora. Los datos sensibles (contraseñas, tokens) nunca se muestran.',
        pasos: [
            { titulo: 'Filtra', texto: 'Por módulo, acción, usuario, fechas o texto.' },
            { titulo: 'Abre el detalle', texto: 'Muestra cada campo con su valor «Antes» y «Después».' },
            { titulo: 'Sigue el historial', texto: 'Desde un registro puedes ver todos sus movimientos.' },
        ],
        acciones: ['Consultar el detalle de cambios.', 'Ver el historial completo de un registro.'],
        filtros: ['Búsqueda.', 'Módulo.', 'Acción: creación, actualización, baja, reactivación, cambio de estatus, eliminación.', 'Usuario.', 'Fechas.'],
        errores: ['Entradas sin usuario: son procesos automáticos del sistema.'],
        advertencias: ['La bitácora es de solo lectura y no se puede modificar.'],
        consejos: ['Para investigar una requisición, filtra por su folio.'],
        claves: 'auditoria historial log cambios trazabilidad',
    },

    {
        id: 'notificaciones-todas',
        nombre: 'Todas las notificaciones',
        grupo: 'Sistema',
        icono: BellRing,
        resumen: 'Consulta administrativa de los avisos de todas las personas.',
        anyOf: ['notificaciones.ver_todas'],
        ruta: 'notificaciones.all',
        catalogo: ['notificaciones'],
        tour: 'notificaciones-todas',
        paraQue: 'Revisar qué avisos se enviaron, a quién y si ya los leyeron, sin entrar a la cuenta de nadie.',
        quien: 'Solo quien tiene «Ver todas las notificaciones».',
        pasos: [
            { titulo: 'Filtra', texto: 'Por destinatario, categoría, estado de lectura o texto.' },
            { titulo: 'Revisa', texto: 'Cada tarjeta muestra destinatario, categoría, título, mensaje, estado y fecha.' },
            { titulo: 'Abre el registro', texto: 'El enlace lleva a la pantalla relacionada; el sistema vuelve a validar tu acceso.' },
        ],
        acciones: ['Filtrar por destinatario, categoría y estado.', 'Abrir el registro relacionado.'],
        errores: ['No poder abrir un registro: el enlace respeta tu propio alcance.'],
        advertencias: ['Es de solo lectura: abrir un aviso desde aquí no lo marca como leído para su destinatario.'],
        consejos: ['Úsala para confirmar si una persona recibió un aviso importante.'],
        claves: 'auditoria avisos destinatario leidas',
    },

    /* --------------------------------------------------------- Tu cuenta y app */
    {
        id: 'ayuda',
        nombre: 'Ayuda y recorridos',
        grupo: 'Tu cuenta y la app',
        icono: CircleHelp,
        resumen: 'Esta guía y los recorridos interactivos dentro del sistema.',
        anyOf: [],
        ruta: 'ayuda.guia',
        tour: 'general',
        paraQue: 'Aprender a usar cada módulo con un recorrido que resalta los controles reales de la pantalla.',
        quien: 'Cualquier cuenta. Solo se muestran los módulos y acciones a los que tienes acceso.',
        pasos: [
            { titulo: 'Abre el botón de ayuda', texto: 'El botón con la brújula (abajo a la derecha) muestra los recorridos de la pantalla actual.' },
            { titulo: 'Inicia un recorrido', texto: 'Avanza con «Siguiente» o la flecha derecha; regresa con «Atrás» o la flecha izquierda; sal con Escape.' },
            { titulo: 'Recorrido completo', texto: 'Recorre todos tus módulos; al terminar vuelves a la pantalla donde estabas.' },
        ],
        acciones: ['Iniciar el recorrido completo o el de un módulo.', 'Saltar un paso o un módulo.', 'Leer la guía escrita de cada módulo.'],
        errores: ['Un paso se salta solo si el control no existe en tu pantalla (por permisos o por el tamaño de pantalla).'],
        advertencias: ['Los recorridos navegan entre módulos; guarda tus cambios antes de iniciar uno.'],
        consejos: ['Los recorridos que ya viste se marcan en esta página.'],
        claves: 'tour recorrido tutorial ayuda guia',
    },
    {
        id: 'perfil',
        nombre: 'Perfil',
        grupo: 'Tu cuenta y la app',
        icono: UserRound,
        resumen: 'Tus datos, tu contraseña y el tema claro u oscuro.',
        anyOf: [],
        ruta: 'profile.edit',
        paraQue: 'Mantener actualizados tu nombre, correo y contraseña.',
        quien: 'Todas las personas con cuenta activa.',
        pasos: [
            { titulo: 'Abre tu perfil', texto: 'Desde el menú de usuario (tu nombre, arriba a la derecha).' },
            { titulo: 'Actualiza tus datos', texto: 'Nombre y correo.' },
            { titulo: 'Cambia tu contraseña', texto: 'Escribe la actual y la nueva dos veces.' },
        ],
        acciones: ['Editar nombre y correo.', 'Cambiar contraseña.', 'Alternar modo claro/oscuro desde la barra superior.', 'Cerrar sesión.'],
        errores: ['«La contraseña actual es incorrecta».', 'La confirmación de la nueva contraseña no coincide.'],
        advertencias: ['Si recibiste una contraseña temporal, cámbiala al entrar.'],
        consejos: ['Usa una contraseña única que no uses en otros sitios.', '¿Olvidaste tu contraseña? Usa «¿Olvidaste tu contraseña?» en la pantalla de inicio de sesión.'],
        claves: 'contraseña cuenta tema oscuro claro datos',
    },
    {
        id: 'app-escritorio',
        nombre: 'Instalar en computadora',
        grupo: 'Tu cuenta y la app',
        icono: Monitor,
        resumen: 'Usa el ERP como aplicación en Windows o macOS con Chrome o Edge.',
        anyOf: [],
        paraQue: 'Abrir el ERP en su propia ventana, desde el menú Inicio o el escritorio, sin buscar la pestaña del navegador.',
        quien: 'Cualquier persona con acceso, desde https://erp.mr-lana.com.',
        pasos: [
            { titulo: 'Abre el ERP en Chrome o Edge', texto: 'La dirección debe comenzar con https://.' },
            { titulo: 'Presiona «Instalar app»', texto: 'Botón en la barra superior. Si el navegador no muestra el aviso, sigue el siguiente paso.' },
            { titulo: 'Instálala desde el navegador', texto: 'Ícono de instalar en la barra de direcciones, o menú ⋮ → «Transmitir, guardar y compartir» → «Instalar página como app» (Chrome) / «Aplicaciones» → «Instalar este sitio como una aplicación» (Edge).' },
            { titulo: 'Ábrela', texto: 'Búscala como «MR-Lana» en el menú Inicio o en tus aplicaciones.' },
        ],
        acciones: ['Instalar, abrir y desinstalar la aplicación desde el navegador.'],
        errores: ['Firefox y Safari en computadora no instalan aplicaciones: usa Chrome o Edge.', 'Sin https:// no se puede instalar.'],
        advertencias: ['La aplicación necesita conexión a internet; es el mismo ERP en otra ventana.'],
        consejos: ['Ánclala a la barra de tareas para abrirla con un clic.'],
        claves: 'pwa instalar aplicacion windows escritorio chrome edge',
    },
    {
        id: 'app-android',
        nombre: 'Instalar en Android',
        grupo: 'Tu cuenta y la app',
        icono: Smartphone,
        resumen: 'Agrega el ERP a la pantalla principal de tu teléfono.',
        anyOf: [],
        paraQue: 'Consultar requisiciones, notificaciones y comprobantes desde el teléfono como una app más.',
        quien: 'Cualquier persona con acceso.',
        pasos: [
            { titulo: 'Abre el ERP en Chrome', texto: 'Entra a https://erp.mr-lana.com e inicia sesión.' },
            { titulo: 'Toca «Instalar app»', texto: 'O el menú ⋮ (arriba a la derecha) → «Instalar app» o «Agregar a pantalla principal».' },
            { titulo: 'Confirma', texto: 'El ícono de MR-Lana aparece junto a tus aplicaciones.' },
        ],
        acciones: ['Instalar y abrir la app.', 'En iPhone o iPad: Safari → Compartir → «Agregar a pantalla de inicio».'],
        errores: ['No aparece la opción: actualiza Chrome y verifica que la dirección empiece con https://.'],
        advertencias: ['Para subir comprobantes desde el teléfono, toma fotos nítidas y completas.'],
        consejos: ['Activa las notificaciones del navegador para no perder avisos.'],
        claves: 'pwa telefono movil celular android iphone',
    },
    {
        id: 'exportaciones',
        nombre: 'Exportaciones PDF y Excel',
        grupo: 'Tu cuenta y la app',
        icono: FileDown,
        resumen: 'Descarga reportes con los mismos filtros que ves en pantalla.',
        anyOf: [
            'reportes.dashboard', 'requisiciones.exportar', 'pagos.exportar', 'comprobaciones.exportar', 'corporativos.exportar',
            'sucursales.exportar', 'areas.exportar', 'colaboradores.exportar', 'conceptos.exportar', 'proveedores.exportar',
        ],
        paraQue: 'Compartir información (PDF) o analizarla (Excel) sin capturarla de nuevo.',
        quien: 'Cada módulo tiene su propio permiso de exportación; solo verás los botones PDF y Excel donde lo tengas.',
        pasos: [
            { titulo: 'Filtra primero', texto: 'La exportación respeta búsqueda, filtros y orden actuales.' },
            { titulo: 'Elige el formato', texto: 'PDF para enviar o imprimir; Excel para tablas dinámicas y conciliaciones.' },
            { titulo: 'Espera la descarga', texto: 'El botón muestra un indicador mientras se genera el archivo.' },
        ],
        acciones: [
            { texto: 'Dashboard.', anyOf: ['reportes.dashboard'] },
            { texto: 'Requisiciones (listado y PDF individual).', anyOf: ['requisiciones.exportar'] },
            { texto: 'Pagos.', anyOf: ['pagos.exportar'] },
            { texto: 'Comprobantes.', anyOf: ['comprobaciones.exportar'] },
            { texto: 'Catálogos y colaboradores.', anyOf: ['corporativos.exportar', 'sucursales.exportar', 'areas.exportar', 'colaboradores.exportar', 'conceptos.exportar', 'proveedores.exportar'] },
        ],
        errores: ['Archivo vacío: los filtros no devuelven registros.', 'Descarga bloqueada: permite descargas para este sitio en tu navegador.'],
        advertencias: ['Editar un archivo exportado no cambia el sistema; la información oficial es la del ERP.'],
        consejos: ['Anota el periodo y los filtros al compartir un reporte.'],
        claves: 'reporte descargar pdf excel xlsx imprimir',
    },
]

/** Catálogos con alta, edición, baja, reactivación y exportación. */
function catalogoSimple(id: string, nombre: string, grupo: string, icono: Component, resumen: string, paraQue: string, advertencias: string[]): GuiaModulo {
    const p = (accion: string) => `${id}.${accion}`
    const minus = nombre.toLowerCase()

    return {
        id,
        nombre,
        grupo,
        icono,
        resumen,
        anyOf: [],
        views: [id],
        tour: id,
        ruta: `${id}.index`,
        catalogo: [id],
        paraQue,
        quien: id === 'conceptos'
            ? 'Personas con permiso para ver conceptos. Registrar, editar, dar de baja, reactivar y exportar son permisos aparte.'
            : `Según tu alcance: solo lo tuyo, lo de tu corporativo o todo. Editar, dar de baja, reactivar y exportar solo aplican a ${minus} que puedes ver.`,
        pasos: [
            { titulo: 'Busca', texto: 'Antes de registrar, confirma que no exista.' },
            { titulo: 'Registra', texto: 'Completa los datos obligatorios y guarda.', anyOf: [p('registrar')] },
            { titulo: 'Mantén actualizado', texto: 'Edita o da de baja lo que ya no se use.', anyOf: [p('editar'), p('desactivar')] },
        ],
        acciones: [
            'Buscar y filtrar por estatus.',
            { texto: `Registrar ${minus}.`, anyOf: [p('registrar')] },
            { texto: `Editar ${minus}.`, anyOf: [p('editar')] },
            { texto: 'Dar de baja (baja lógica).', anyOf: [p('desactivar')] },
            { texto: 'Reactivar.', anyOf: [p('reactivar')] },
            { texto: 'Exportar a PDF o Excel.', anyOf: [p('exportar')] },
        ],
        estados: [
            { nombre: 'Activo', tono: 'verde', texto: 'Disponible para registros nuevos.' },
            { nombre: 'Inactivo', tono: 'gris', texto: 'Se conserva para el historial, pero no se puede elegir.' },
        ],
        errores: ['Nombre duplicado o datos obligatorios vacíos.'],
        advertencias,
        consejos: ['Prefiere dar de baja en lugar de eliminar: conserva la trazabilidad.'],
    }
}
