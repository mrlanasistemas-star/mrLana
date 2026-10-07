<script setup lang="ts">
/**
 * Navbar: hamburguesa (móvil/tableta), título, descarga de la app, campana,
 * cambio de tema y menú de usuario. Diseñado para no desbordar desde 360 px.
 */
import { computed, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { ChevronDown, LifeBuoy, LogOut, Menu, Moon, Sun, UserRound, BookOpen } from 'lucide-vue-next'
import { DropdownMenuItem, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui'
import { DropdownMenuContent } from '@/Components/ui/dropdown-menu'
import DownloadAppButton from '@/Components/layout/DownloadAppButton.vue'
import NotificationBell from '@/Components/layout/NotificationBell.vue'
import { useLogout } from '@/Composables/useLogout'
import { usePermissions } from '@/Composables/usePermissions'
import { useSidebar } from '@/Composables/useSidebar'
import { useTheme } from '@/Composables/useTheme'
import type { SharedProps } from '@/types/shared'

const page = usePage<SharedProps>()
const SUPPORT_URL = 'https://soporte.mr-lana.com/'

const { isDark, toggle, init } = useTheme()
onMounted(() => init())

const { openMobile, mobileOpen } = useSidebar()
const { confirmLogout } = useLogout()
const { can, roles } = usePermissions()

const user = computed(() => page.props.auth?.user)
const initials = computed(() => {
    const parts = String(user.value?.name ?? 'ML').trim().split(/\s+/).filter(Boolean)
    return (parts.slice(0, 2).map((p) => p[0] ?? '').join('') || 'ML').toUpperCase()
})

const menuItemClass =
    'flex min-h-[40px] cursor-pointer select-none items-center gap-2.5 rounded-lg px-3 text-sm text-slate-700 outline-none ' +
    'data-[highlighted]:bg-slate-100 data-[highlighted]:text-slate-900 dark:text-zinc-200 dark:data-[highlighted]:bg-white/10'
</script>

<template>
    <nav
        class="relative flex h-16 items-center justify-between gap-2 border-b px-3 backdrop-blur-md sm:px-6 lg:px-8
               bg-white/85 border-slate-200/80 dark:bg-zinc-950/80 dark:border-zinc-800/60"
        aria-label="Barra superior"
    >
        <div class="flex min-w-0 items-center gap-2">
            <button
                type="button"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-700 transition
                       hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60
                       dark:text-zinc-200 dark:hover:bg-white/10 lg:hidden"
                aria-label="Abrir menú"
                :aria-expanded="mobileOpen"
                @click="openMobile"
            >
                <Menu class="h-5 w-5" aria-hidden="true" />
            </button>
            <h1 class="min-w-0 truncate text-sm font-bold text-slate-900 dark:text-zinc-100 sm:text-base">
                <slot name="title">Dashboard</slot>
            </h1>
        </div>

        <div class="flex shrink-0 items-center gap-1.5 sm:gap-2.5">
            <DownloadAppButton class="hidden xl:inline-flex" variant="full" />
            <DownloadAppButton class="xl:hidden" variant="compact" />

            <NotificationBell v-if="can('notificaciones.ver')" />

            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border shadow-sm transition
                       border-slate-200/80 bg-white/90 text-amber-500 hover:shadow-md
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60
                       dark:border-zinc-700/70 dark:bg-zinc-900/40 dark:text-zinc-200"
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
                        class="group flex h-10 items-center gap-2 rounded-full border px-1 text-left text-sm shadow-sm transition sm:pl-1 sm:pr-2.5
                               border-slate-200/80 bg-white/90 text-slate-900 hover:shadow-md
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60
                               dark:border-zinc-700/70 dark:bg-zinc-900/40 dark:text-zinc-100"
                        aria-label="Menú de usuario"
                    >
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-zinc-200 dark:text-zinc-900">
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
                    class="z-[300] w-64 rounded-2xl border-slate-200 bg-white p-1.5 shadow-2xl dark:border-white/10 dark:bg-zinc-950"
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
                    <DropdownMenuItem as-child>
                        <a :href="SUPPORT_URL" target="_blank" rel="noopener noreferrer" :class="menuItemClass">
                            <LifeBuoy class="h-4 w-4" aria-hidden="true" /> Soporte (tickets)
                        </a>
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
