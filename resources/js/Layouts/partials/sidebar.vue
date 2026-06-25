<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import Swal from 'sweetalert2'
import {
    LayoutDashboard,
    Building2,
    MapPin,
    Layers3,
    Users,
    Tags,
    Truck,
    FileText,
    ClipboardList,
    ScrollText,
    LogOut,
} from 'lucide-vue-next'

defineProps<{ current?: string }>()

const open = ref(false)
const reducedMotion = ref(false)

const page = usePage()
const userRole = computed(() => ((page.props as any)?.auth?.user?.rol ?? 'COLABORADOR') as 'ADMIN' | 'CONTADOR' | 'COLABORADOR')

onMounted(() => {
    reducedMotion.value = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false
})

const isDarkTheme = () => document.documentElement.classList.contains('dark')

const confirmLogout = async () => {
    const dark = isDarkTheme()
    const result = await Swal.fire({
        title: '¿Cerrar sesión?',
        text: 'Perderás acceso hasta volver a iniciar sesión.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cerrar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        background: dark ? '#18181b' : '#ffffff',
        color: dark ? '#e4e4e7' : '#111827',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#52525b',
    })
    if (!result.isConfirmed) return
    router.post(route('logout'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Sesión cerrada',
                text: 'Has salido del sistema correctamente.',
                timer: 1600,
                showConfirmButton: false,
                background: dark ? '#18181b' : '#ffffff',
                color: dark ? '#e4e4e7' : '#111827',
                iconColor: '#22c55e',
            })
        },
    })
}

const can = (roles: Array<'ADMIN' | 'CONTADOR' | 'COLABORADOR'>) => roles.includes(userRole.value)

const safeRoute = (name: string): string | null => {
    try {
        // @ts-ignore
        return route(name) as string
    } catch {
        return null
    }
}

const isActive = (name: string) => {
    try {
        return route().current(name)
    } catch {
        return false
    }
}

const onEnter = () => {
    open.value = true
    if (reducedMotion.value) return
    const el = document.getElementById('erp-sidebar')
    el?.animate(
        [{ transform: 'translateX(-2px)', opacity: 0.97 }, { transform: 'translateX(0)', opacity: 1 }],
        { duration: 160, easing: 'ease-out' }
    )
}
const onLeave = () => (open.value = false)

const showTip = computed(() => !open.value)

type Role = 'ADMIN' | 'CONTADOR' | 'COLABORADOR'
type IconComponent = typeof LayoutDashboard

type NavItem = {
    label: string
    routeName: string
    icon: IconComponent
    roles: Role[]
}

type NavGroup = {
    title: string
    roles: Role[]
    items: NavItem[]
}

const rawGroups = computed<NavGroup[]>(() => [
    {
        title: 'General',
        roles: ['ADMIN', 'CONTADOR', 'COLABORADOR'],
        items: [
            { label: 'Dashboard', routeName: 'dashboard', icon: LayoutDashboard, roles: ['ADMIN', 'CONTADOR', 'COLABORADOR'] },
        ],
    },
    {
        title: 'Organización',
        roles: ['ADMIN', 'CONTADOR'],
        items: [
            { label: 'Corporativos', routeName: 'corporativos.index', icon: Building2, roles: ['ADMIN', 'CONTADOR'] },
            { label: 'Sucursales', routeName: 'sucursales.index', icon: MapPin, roles: ['ADMIN', 'CONTADOR'] },
            { label: 'Áreas', routeName: 'areas.index', icon: Layers3, roles: ['ADMIN', 'CONTADOR'] },
        ],
    },
    {
        title: 'Personas',
        roles: ['ADMIN'],
        items: [
            { label: 'Empleados', routeName: 'empleados.index', icon: Users, roles: ['ADMIN'] },
        ],
    },
    {
        title: 'Catálogos',
        roles: ['ADMIN', 'CONTADOR', 'COLABORADOR'],
        items: [
            { label: 'Conceptos', routeName: 'conceptos.index', icon: Tags, roles: ['ADMIN', 'CONTADOR'] },
            { label: 'Proveedores', routeName: 'proveedores.index', icon: Truck, roles: ['ADMIN', 'CONTADOR', 'COLABORADOR'] },
        ],
    },
    {
        title: 'Operación',
        roles: ['ADMIN', 'CONTADOR', 'COLABORADOR'],
        items: [
            { label: 'Requisiciones', routeName: 'requisiciones.index', icon: FileText, roles: ['ADMIN', 'CONTADOR', 'COLABORADOR'] },
            { label: 'Plantillas', routeName: 'plantillas.index', icon: ClipboardList, roles: ['ADMIN', 'CONTADOR', 'COLABORADOR'] },
        ],
    },
    {
        title: 'Auditoría',
        roles: ['ADMIN'],
        items: [
            { label: 'System Log', routeName: 'systemlogs.index', icon: ScrollText, roles: ['ADMIN'] },
        ],
    },
])

const groups = computed(() =>
    rawGroups.value
        .filter(g => can(g.roles))
        .map(g => ({
            title: g.title,
            items: g.items
                .filter(i => can(i.roles))
                .map(i => ({ ...i, href: safeRoute(i.routeName) }))
                .filter(i => !!i.href),
        }))
        .filter(g => g.items.length > 0)
)
</script>

<template>
    <aside
        id="erp-sidebar"
        class="group/sidebar relative flex min-h-dvh flex-col justify-between
               sticky top-0 z-30 shrink-0
               border-r transition-[width] duration-300 ease-out
               bg-white text-slate-800 border-slate-200/80
               dark:bg-zinc-950 dark:text-zinc-100 dark:border-zinc-800/60"
        :class="open ? 'w-60' : 'w-[66px]'"
        @mouseenter="onEnter"
        @mouseleave="onLeave"
    >
        <!-- Logo -->
        <div>
            <div class="flex h-16 items-center border-b border-slate-200/80 dark:border-zinc-800/60 px-3">
                <div
                    class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl
                           bg-slate-900 text-white shadow-md
                           dark:bg-zinc-100 dark:text-zinc-900
                           transition-transform duration-200 group-hover/sidebar:scale-[1.04]"
                >
                    <ApplicationLogo class="h-5 w-5" />
                </div>

                <div
                    class="ml-3 overflow-hidden transition-all duration-300"
                    :class="open ? 'w-36 opacity-100' : 'w-0 opacity-0'"
                >
                    <span class="block text-sm font-black tracking-tight text-slate-900 dark:text-zinc-100 whitespace-nowrap select-none">
                        MR-Lana ERP
                    </span>
                    <span class="block text-[10px] font-medium text-slate-400 dark:text-zinc-500 whitespace-nowrap">
                        Sistema integrado
                    </span>
                </div>
            </div>

            <!-- Navegación -->
            <nav class="mt-3 px-2 space-y-4 overflow-hidden">
                <div v-for="g in groups" :key="g.title" class="space-y-0.5">

                    <!-- Título de grupo (solo visible con sidebar abierta) -->
                    <div
                        class="overflow-hidden transition-all duration-200"
                        :class="open ? 'max-h-8 opacity-100 mb-1' : 'max-h-0 opacity-0'"
                    >
                        <span class="block px-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 select-none">
                            {{ g.title }}
                        </span>
                    </div>

                    <!-- Items -->
                    <Link
                        v-for="item in g.items"
                        :key="item.routeName"
                        :href="item.href"
                        :preserve-state="false"
                        :preserve-scroll="true"
                        class="sidebar-nav-item group relative flex items-center rounded-xl px-2.5 py-2.5
                               text-sm font-medium cursor-pointer
                               transition-all duration-200
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300/60
                               dark:focus-visible:ring-zinc-600/60"
                        :class="isActive(item.routeName)
                            ? 'bg-slate-900 text-white shadow-sm dark:bg-zinc-100 dark:text-zinc-900'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-zinc-800/70 dark:hover:text-zinc-100'"
                    >
                        <!-- Indicador lateral activo -->
                        <span
                            v-if="isActive(item.routeName)"
                            class="absolute left-0 top-1/2 -translate-y-1/2 w-0.5 h-5 rounded-r-full
                                   bg-white dark:bg-zinc-900"
                        ></span>

                        <!-- Icono -->
                        <component
                            :is="item.icon"
                            class="h-[18px] w-[18px] flex-shrink-0 transition-all duration-200"
                            :class="[
                                isActive(item.routeName)
                                    ? 'text-white dark:text-zinc-900'
                                    : 'text-slate-500 dark:text-zinc-500 group-hover:text-slate-800 dark:group-hover:text-zinc-200 group-hover:scale-110 group-hover:translate-x-0.5',
                            ]"
                        />

                        <!-- Label con fade -->
                        <div
                            class="overflow-hidden transition-all duration-200 ml-2.5"
                            :class="open ? 'w-36 opacity-100' : 'w-0 opacity-0'"
                        >
                            <span class="block whitespace-nowrap text-sm font-semibold">{{ item.label }}</span>
                        </div>

                        <!-- Tooltip cuando está colapsada -->
                        <span
                            v-if="showTip"
                            class="pointer-events-none absolute left-[58px] top-1/2 -translate-y-1/2 z-50
                                   whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold
                                   bg-slate-900 text-white shadow-xl opacity-0 -translate-x-1
                                   group-hover:opacity-100 group-hover:translate-x-0
                                   transition-all duration-150
                                   dark:bg-zinc-100 dark:text-zinc-900"
                        >
                            {{ item.label }}
                        </span>
                    </Link>
                </div>
            </nav>
        </div>

        <!-- Footer: rol + logout -->
        <div class="border-t border-slate-200/80 dark:border-zinc-800/60 p-2">
            <!-- Rol (solo visible abierto) -->
            <div
                class="overflow-hidden transition-all duration-200 px-3"
                :class="open ? 'max-h-8 opacity-100 mb-2' : 'max-h-0 opacity-0'"
            >
                <span class="text-[10px] font-semibold text-slate-400 dark:text-zinc-500 select-none">
                    Rol: <span class="font-black text-slate-600 dark:text-zinc-300">{{ userRole }}</span>
                </span>
            </div>

            <!-- Botón Logout -->
            <button
                type="button"
                @click="confirmLogout"
                class="group relative flex items-center w-full rounded-xl px-2.5 py-2.5
                       text-sm font-medium transition-all duration-200
                       text-slate-500 hover:bg-red-50 hover:text-red-700
                       dark:text-zinc-500 dark:hover:bg-red-950/40 dark:hover:text-red-300
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400/30"
            >
                <LogOut
                    class="h-[18px] w-[18px] flex-shrink-0 transition-all duration-200
                           group-hover:scale-110 group-hover:translate-x-0.5"
                />

                <div
                    class="overflow-hidden transition-all duration-200 ml-2.5"
                    :class="open ? 'w-36 opacity-100' : 'w-0 opacity-0'"
                >
                    <span class="block whitespace-nowrap font-semibold">Cerrar sesión</span>
                </div>

                <!-- Tooltip -->
                <span
                    v-if="showTip"
                    class="pointer-events-none absolute left-[58px] top-1/2 -translate-y-1/2 z-50
                           whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold
                           bg-red-600 text-white shadow-xl opacity-0 -translate-x-1
                           group-hover:opacity-100 group-hover:translate-x-0
                           transition-all duration-150"
                >
                    Cerrar sesión
                </span>
            </button>
        </div>
    </aside>
</template>

<style scoped>
/* Transición suave del ancho sin flash */
aside {
    will-change: width;
}

/* El indicador activo sin animación parpadeante */
.sidebar-nav-item {
    overflow: visible;
}

@media (prefers-reduced-motion: reduce) {
    aside,
    .sidebar-nav-item,
    .sidebar-nav-item * {
        transition-duration: 0ms !important;
        animation: none !important;
    }
}
</style>
