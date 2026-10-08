import type { Component } from 'vue'
import {
    Banknote,
    Bell,
    Building2,
    CircleHelp,
    ClipboardList,
    FileText,
    KeyRound,
    LayoutDashboard,
    Layers3,
    MapPin,
    Receipt,
    ScrollText,
    Settings,
    Tags,
    Truck,
    UserCog,
    Users,
} from 'lucide-vue-next'
import type { Visibility } from '@/Composables/usePermissions'

export type NavItem = Visibility & {
    /** Identificador estable (también usado por los recorridos: data-tour="nav-<key>"). */
    key: string
    label: string
    routeName: string
    /** Patrón para marcar activo (p. ej. 'usuarios.*'). */
    activePattern: string
    icon: Component
}

export type NavGroup = { title: string; items: NavItem[] }

/**
 * Menú principal. Cada opción se muestra si el usuario puede ver el módulo
 * (`views`, alcance resuelto en el servidor) o tiene alguno de los permisos
 * (`anyOf`). Sin reglas, la ve cualquier cuenta. El backend vuelve a validar
 * cada ruta.
 */
export const NAVIGATION: NavGroup[] = [
    {
        title: 'General',
        items: [
            { key: 'dashboard', label: 'Dashboard', routeName: 'dashboard', activePattern: 'dashboard*', icon: LayoutDashboard, views: ['dashboard'] },
            { key: 'notificaciones', label: 'Notificaciones', routeName: 'notificaciones.index', activePattern: 'notificaciones.*', icon: Bell, views: ['notificaciones'] },
        ],
    },
    {
        title: 'Operación',
        items: [
            { key: 'requisiciones', label: 'Requisiciones', routeName: 'requisiciones.index', activePattern: 'requisiciones.*', icon: FileText, views: ['requisiciones'] },
            { key: 'plantillas', label: 'Plantillas', routeName: 'plantillas.index', activePattern: 'plantillas.*', icon: ClipboardList, views: ['plantillas'] },
            { key: 'pagos', label: 'Pagos', routeName: 'pagos.index', activePattern: 'pagos.*', icon: Banknote, views: ['pagos'] },
            { key: 'comprobantes', label: 'Comprobantes', routeName: 'comprobantes.index', activePattern: 'comprobantes.*', icon: Receipt, views: ['comprobaciones'] },
        ],
    },
    {
        title: 'Organización',
        items: [
            { key: 'corporativos', label: 'Corporativos', routeName: 'corporativos.index', activePattern: 'corporativos.*', icon: Building2, views: ['corporativos'] },
            { key: 'sucursales', label: 'Sucursales', routeName: 'sucursales.index', activePattern: 'sucursales.*', icon: MapPin, views: ['sucursales'] },
            { key: 'areas', label: 'Áreas', routeName: 'areas.index', activePattern: 'areas.*', icon: Layers3, views: ['areas'] },
        ],
    },
    {
        title: 'Personas y accesos',
        items: [
            { key: 'colaboradores', label: 'Colaboradores', routeName: 'colaboradores.index', activePattern: 'colaboradores.*', icon: Users, views: ['colaboradores'] },
            { key: 'usuarios', label: 'Usuarios', routeName: 'usuarios.index', activePattern: 'usuarios.*', icon: UserCog, views: ['usuarios'] },
            { key: 'roles', label: 'Roles y permisos', routeName: 'roles.index', activePattern: 'roles.*', icon: KeyRound, anyOf: ['roles.ver'] },
        ],
    },
    {
        title: 'Catálogos',
        items: [
            { key: 'conceptos', label: 'Conceptos', routeName: 'conceptos.index', activePattern: 'conceptos.*', icon: Tags, anyOf: ['conceptos.ver'] },
            { key: 'proveedores', label: 'Proveedores', routeName: 'proveedores.index', activePattern: 'proveedores.*', icon: Truck, views: ['proveedores'] },
        ],
    },
    {
        title: 'Sistema',
        items: [
            { key: 'configuracion', label: 'Configuración', routeName: 'configuracion.edit', activePattern: 'configuracion.*', icon: Settings, anyOf: ['configuracion.ver', 'configuracion.administrar'] },
            { key: 'bitacora', label: 'Bitácora', routeName: 'systemlogs.index', activePattern: 'systemlogs.*', icon: ScrollText, views: ['logs'] },
        ],
    },
    {
        title: 'Soporte',
        items: [
            // Disponible para cualquier cuenta autenticada.
            { key: 'ayuda', label: 'Ayuda', routeName: 'ayuda.guia', activePattern: 'ayuda.*', icon: CircleHelp },
        ],
    },
]
