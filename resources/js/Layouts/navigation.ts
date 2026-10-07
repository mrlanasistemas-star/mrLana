import type { Component } from 'vue'
import {
    Bell,
    Building2,
    ClipboardList,
    FileText,
    KeyRound,
    LayoutDashboard,
    Layers3,
    MapPin,
    ScrollText,
    Settings,
    Tags,
    Truck,
    UserCog,
    Users,
} from 'lucide-vue-next'

export type NavItem = {
    label: string
    routeName: string
    /** Patrón para marcar activo (p. ej. 'usuarios.*'). */
    activePattern: string
    icon: Component
    /** Basta con tener alguno de estos permisos. */
    anyOf: string[]
}

export type NavGroup = { title: string; items: NavItem[] }

/**
 * Menú principal. Cada opción se muestra solo si el usuario tiene alguno de
 * los permisos indicados; el backend vuelve a validar cada ruta.
 */
export const NAVIGATION: NavGroup[] = [
    {
        title: 'General',
        items: [
            { label: 'Dashboard', routeName: 'dashboard', activePattern: 'dashboard*', icon: LayoutDashboard, anyOf: ['dashboard.ver'] },
            { label: 'Notificaciones', routeName: 'notificaciones.index', activePattern: 'notificaciones.*', icon: Bell, anyOf: ['notificaciones.ver'] },
        ],
    },
    {
        title: 'Operación',
        items: [
            { label: 'Requisiciones', routeName: 'requisiciones.index', activePattern: 'requisiciones.*', icon: FileText, anyOf: ['requisiciones.ver_todos', 'requisiciones.ver_propios'] },
            { label: 'Plantillas', routeName: 'plantillas.index', activePattern: 'plantillas.*', icon: ClipboardList, anyOf: ['plantillas.ver_todos', 'plantillas.ver_propios'] },
        ],
    },
    {
        title: 'Organización',
        items: [
            { label: 'Corporativos', routeName: 'corporativos.index', activePattern: 'corporativos.*', icon: Building2, anyOf: ['corporativos.ver'] },
            { label: 'Sucursales', routeName: 'sucursales.index', activePattern: 'sucursales.*', icon: MapPin, anyOf: ['sucursales.ver'] },
            { label: 'Áreas', routeName: 'areas.index', activePattern: 'areas.*', icon: Layers3, anyOf: ['areas.ver'] },
        ],
    },
    {
        title: 'Personas y accesos',
        items: [
            { label: 'Colaboradores', routeName: 'colaboradores.index', activePattern: 'colaboradores.*', icon: Users, anyOf: ['colaboradores.ver'] },
            { label: 'Usuarios', routeName: 'usuarios.index', activePattern: 'usuarios.*', icon: UserCog, anyOf: ['usuarios.ver'] },
            { label: 'Roles y permisos', routeName: 'roles.index', activePattern: 'roles.*', icon: KeyRound, anyOf: ['roles.ver'] },
        ],
    },
    {
        title: 'Catálogos',
        items: [
            { label: 'Conceptos', routeName: 'conceptos.index', activePattern: 'conceptos.*', icon: Tags, anyOf: ['conceptos.ver'] },
            { label: 'Proveedores', routeName: 'proveedores.index', activePattern: 'proveedores.*', icon: Truck, anyOf: ['proveedores.ver'] },
        ],
    },
    {
        title: 'Sistema',
        items: [
            { label: 'Configuración', routeName: 'configuracion.edit', activePattern: 'configuracion.*', icon: Settings, anyOf: ['configuracion.ver', 'configuracion.administrar'] },
            { label: 'Bitácora', routeName: 'systemlogs.index', activePattern: 'systemlogs.*', icon: ScrollText, anyOf: ['logs.ver'] },
        ],
    },
]

