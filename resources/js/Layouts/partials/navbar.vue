<script setup lang="ts">
/**
 * Navbar: hamburguesa (móvil/tableta), título, descarga de la app, campana,
 * cambio de tema y menú de usuario. Diseñado para no desbordar desde 360 px.
 */
import { computed, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { ChevronDown, ChevronRight, LogOut, Menu, Moon, Sun, UserRound, BookOpen } from 'lucide-vue-next'
import { DropdownMenuItem, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui'
import { DropdownMenuContent } from '@/Components/ui/dropdown-menu'
import DownloadAppButton from '@/Components/layout/DownloadAppButton.vue'
import NotificationBell from '@/Components/layout/NotificationBell.vue'
import { useLogout } from '@/Composables/useLogout'
import { usePermissions } from '@/Composables/usePermissions'
import { useSidebar } from '@/Composables/useSidebar'
import { useTheme } from '@/Composables/useTheme'
import type { SharedProps } from '@/types/shared'
import { NAVIGATION } from '@/Layouts/navigation'

const page = usePage<SharedProps>()

const { isDark, toggle, init } = useTheme()
onMounted(() => init())

const { openMobile, mobileOpen } = useSidebar()
const { confirmLogout } = useLogout()
const { can, canView, roles } = usePermissions()

const user = computed(() => page.props.auth?.user)
const initials = computed(() => {
    const parts = String(user.value?.name ?? 'ML').trim().split(/\s+/).filter(Boolean)
    return (parts.slice(0, 2).map((p) => p[0] ?? '').join('') || 'ML').toUpperCase()
})

/**
 * Migas de pan: sección del menú y, en las páginas internas de un módulo
 * (detalle, comprobar, editar…), un enlace de regreso a su listado.
 */
const crumbs = computed(() => {
    void page.url
    try {
        for (const g of NAVIGATION) {
            const item = g.items.find((i) => route().current(i.activePattern))
            if (!item) continue
            // En el listado del módulo el título ya es el módulo; el dashboard cambia de ruta según el rol.
            const onIndex = route().current(item.routeName) || item.key === 'dashboard'
            return { section: g.title, module: onIndex ? null : { label: item.label, href: route(item.routeName) } }
        }
    } catch {
        /* ruta fuera del menú */
    }
    return { section: null, module: null }
})

const menuItemClass =
    'flex min-h-[40px] cursor-pointer select-none items-center gap-2.5 rounded-lg px-3 text-sm text-slate-700 outline-none ' +
    'data-[highlighted]:bg-brand-tint data-[highlighted]:text-brand-primary dark:text-zinc-200 dark:data-[highlighted]:bg-white/10 dark:data-[highlighted]:text-zinc-50'
</script>

<template>
    <nav
        class="relative flex h-16 items-center justify-between gap-2 border-b px-3 backdrop-blur-md sm:px-6 lg:px-8
               bg-white/85 border-slate-200/80 dark:bg-zinc-950/85 dark:border-white/[0.06]"
        aria-label="Barra superior"
    >
        <div class="flex min-w-0 items-center gap-2">
            <button
                type="button"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-700 transition
                       hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40
                       dark:text-zinc-200 dark:hover:bg-white/10 lg:hidden"
                aria-label="Abrir menú"
                :aria-expanded="mobileOpen"
                @click="openMobile"
            >
                <Menu class="h-5 w-5" aria-hidden="true" />
            </button>
            <div class="flex min-w-0 items-center gap-2">
                <nav v-if="crumbs.section || crumbs.module" aria-label="Ruta de navegación" class="contents">
                    <span v-if="crumbs.section" class="hidden shrink-0 items-center gap-2 text-sm font-medium text-slate-500 dark:text-zinc-400 lg:inline-flex">
                        {{ crumbs.section }}
                        <ChevronRight class="h-4 w-4 text-slate-300 dark:text-zinc-600" aria-hidden="true" />
                    </span>
                    <span v-if="crumbs.module" class="hidden shrink-0 items-center gap-2 sm:inline-flex">
                        <Link
                            :href="crumbs.module.href"
                            class="rounded-md px-1 text-sm font-semibold text-slate-600 underline-offset-4 transition hover:text-brand-primary hover:underline
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 dark:text-zinc-300 dark:hover:text-zinc-50"
                        >
                            {{ crumbs.module.label }}
                        </Link>
                        <ChevronRight class="h-4 w-4 text-slate-300 dark:text-zinc-600" aria-hidden="true" />
                    </span>
                </nav>
                <h1 class="min-w-0 truncate text-[15px] font-semibold tracking-tight text-slate-900 dark:text-zinc-50 sm:text-lg">
                    <slot name="title">Dashboard</slot>
                </h1>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-1.5 sm:gap-2.5">
            <DownloadAppButton class="hidden xl:inline-flex" variant="full" />
            <DownloadAppButton class="xl:hidden" variant="compact" />

            <!-- Envoltura con caja propia: ancla estable del recorrido -->
            <div v-if="canView('notificaciones')" data-tour="campana" class="inline-flex">
                <NotificationBell />
            </div>

            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border transition
                       border-slate-200 bg-white text-slate-600 hover:border-brand-primary/30 hover:bg-brand-tint/60 hover:text-brand-primary
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40
                       dark:border-white/10 dark:bg-white/[0.04] dark:text-zinc-200 dark:hover:bg-white/10 dark:hover:text-zinc-50"
                :aria-label="isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
                @click="toggle"
            >
                <Moon v-if="isDark" class="h-[18px] w-[18px]" aria-hidden="true" />
                <Sun v-else class="h-[18px] w-[18px]" aria-hidden="true" />
            </button>

            <DropdownMenuRoot>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="group flex h-10 items-center gap-2 rounded-full border px-1 text-left text-sm transition sm:pl-1 sm:pr-2.5
                               border-slate-200 bg-white text-slate-900 hover:border-brand-primary/30
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40
                               dark:border-white/10 dark:bg-white/[0.04] dark:text-zinc-100"
                        aria-label="Menú de usuario"
                    >
                        <span class="ui-brand-gradient flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold text-white">
                            {{ initials }}
                        </span>
                        <span class="hidden max-w-[11rem] flex-col leading-tight md:flex">
                            <span class="truncate text-xs font-semibold">{{ user?.name ?? 'Usuario' }}</span>
                            <span class="truncate text-[10px] text-slate-500 dark:text-zinc-400">{{ roles.join(', ') || user?.email }}</span>
                        </span>
                        <ChevronDown class="hidden h-4 w-4 text-slate-500 transition-transform group-data-[state=open]:rotate-180 sm:block" aria-hidden="true" />
                    </button>
                </DropdownMenuTrigger>

                <DropdownMenuContent
                    align="end"
                    :side-offset="8"
                    :collision-padding="12"
                    class="z-[300] w-64 rounded-2xl border-slate-200 bg-white p-1.5 shadow-2xl dark:border-white/10 dark:bg-zinc-900"
                >
                    <div class="px-3 py-2">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-zinc-100">{{ user?.name }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-zinc-400">{{ user?.email }}</p>
                    </div>
                    <DropdownMenuSeparator class="my-1 h-px bg-slate-100 dark:bg-white/10" />
                    <DropdownMenuItem as-child>
                        <Link :href="route('profile.edit')" :class="menuItemClass">
                            <UserRound class="h-4 w-4" aria-hidden="true" /> Mi perfil
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <Link :href="route('ayuda.guia')" :class="menuItemClass">
                            <BookOpen class="h-4 w-4" aria-hidden="true" /> Guía del sistema
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator class="my-1 h-px bg-slate-100 dark:bg-white/10" />
                    <DropdownMenuItem
                        :class="[menuItemClass, 'text-red-600 data-[highlighted]:bg-red-50 data-[highlighted]:text-red-700 dark:text-red-300 dark:data-[highlighted]:bg-red-500/10']"
                        @select="confirmLogout"
                    >
                        <LogOut class="h-4 w-4" aria-hidden="true" /> Cerrar sesión
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenuRoot>
        </div>
    </nav>
</template>
