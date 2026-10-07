<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Bell, CheckCheck, Loader2 } from 'lucide-vue-next'
import { DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui'
import { DropdownMenuContent } from '@/Components/ui/dropdown-menu'
import NotificationItem from '@/Components/layout/NotificationItem.vue'
import { safeInternalUrl, useNotifications } from '@/Composables/useNotifications'
import type { ErpNotificationItem, SharedProps } from '@/types/shared'

const page = usePage<SharedProps>()
const { items, unread, loading, pulse, lastError, start, refresh, markAsRead, markAllAsRead } = useNotifications()

const open = ref(false)
const ringing = ref(false)

onMounted(() => {
    const user = page.props.auth?.user
    if (user) start(user.id, page.props.notifications?.unread_count ?? 0)
})

watch(pulse, () => {
    ringing.value = false
    requestAnimationFrame(() => (ringing.value = true))
    window.setTimeout(() => (ringing.value = false), 900)
})

watch(open, (o) => {
    if (o) void refresh()
})

const badge = computed(() => (unread.value > 99 ? '99+' : String(unread.value)))
const ariaLabel = computed(() =>
    unread.value > 0 ? `Notificaciones: ${unread.value} sin leer` : 'Notificaciones: no hay pendientes',
)

async function openItem(item: ErpNotificationItem) {
    await markAsRead(item)
    open.value = false
    const url = safeInternalUrl(item.url)
    if (url) router.visit(url)
}
</script>

<template>
    <DropdownMenuRoot v-model:open="open">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border shadow-sm transition
                       border-slate-200/80 bg-white/90 text-slate-700 hover:bg-white hover:shadow-md
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60
                       dark:border-zinc-700/70 dark:bg-zinc-900/40 dark:text-zinc-200 dark:hover:bg-zinc-900/60"
                :aria-label="ariaLabel"
            >
                <Bell class="h-[18px] w-[18px]" :class="ringing ? 'bell-ring' : ''" aria-hidden="true" />
                <span
                    v-if="unread > 0"
                    class="absolute -right-1 -top-1 min-w-[20px] rounded-full bg-brand-danger px-1.5 py-0.5 text-center
                           text-[10px] font-bold leading-none text-brand-danger-fg ring-2 ring-white dark:ring-zinc-950"
                    :class="ringing ? 'badge-pop' : ''"
                    aria-hidden="true"
                >
                    {{ badge }}
                </span>
                <span class="sr-only" aria-live="polite">{{ ariaLabel }}</span>
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            :side-offset="8"
            :collision-padding="12"
            class="z-[300] w-[min(24rem,calc(100vw-1.5rem))] rounded-2xl border-slate-200 bg-white p-0 shadow-2xl
                   dark:border-white/10 dark:bg-zinc-950"
        >
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-white/10">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-900 dark:text-zinc-100">Notificaciones</p>
                    <p class="text-xs text-slate-500 dark:text-zinc-400">
                        {{ unread > 0 ? `${unread} sin leer` : 'Estás al día' }}
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold text-slate-600 transition
                           hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60
                           disabled:opacity-40 dark:text-zinc-300 dark:hover:bg-white/10"
                    :disabled="unread === 0"
                    @click="markAllAsRead"
                >
                    <CheckCheck class="h-4 w-4" aria-hidden="true" />
                    Marcar todas como leídas
                </button>
            </div>

            <div class="max-h-[min(26rem,60dvh)] overflow-y-auto overscroll-contain">
                <div v-if="loading && items.length === 0" class="flex items-center justify-center gap-2 py-10 text-sm text-slate-500">
                    <Loader2 class="h-4 w-4 animate-spin" aria-hidden="true" /> Cargando…
                </div>
                <p v-else-if="lastError" class="px-4 py-6 text-center text-sm text-rose-600 dark:text-rose-400" role="alert">
                    {{ lastError }}
                </p>
                <div v-else-if="items.length === 0" class="px-4 py-10 text-center">
                    <Bell class="mx-auto h-8 w-8 text-slate-300 dark:text-zinc-700" aria-hidden="true" />
                    <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-zinc-300">Sin notificaciones</p>
                    <p class="text-xs text-slate-500 dark:text-zinc-500">Aquí verás avisos de requisiciones, pagos y ajustes.</p>
                </div>
                <ul v-else class="divide-y divide-slate-100 dark:divide-white/5">
                    <li v-for="item in items" :key="item.id">
                        <NotificationItem :item="item" compact @open="openItem(item)" @read="markAsRead(item)" />
                    </li>
                </ul>
            </div>

            <div class="border-t border-slate-100 p-2 dark:border-white/10">
                <Link
                    :href="route('notificaciones.index')"
                    class="flex min-h-[40px] items-center justify-center rounded-xl text-sm font-semibold text-slate-700 transition
                           hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60
                           dark:text-zinc-200 dark:hover:bg-white/10"
                    @click="open = false"
                >
                    Ver todas
                </Link>
            </div>
        </DropdownMenuContent>
    </DropdownMenuRoot>
</template>

<style scoped>
@keyframes bell-ring {
    0%, 100% { transform: rotate(0); }
    20% { transform: rotate(14deg); }
    40% { transform: rotate(-12deg); }
    60% { transform: rotate(8deg); }
    80% { transform: rotate(-4deg); }
}
@keyframes badge-pop {
    0% { transform: scale(0.6); }
    60% { transform: scale(1.15); }
    100% { transform: scale(1); }
}
.bell-ring { animation: bell-ring 0.8s ease-in-out; transform-origin: top center; }
.badge-pop { animation: badge-pop 0.35s ease-out; }
@media (prefers-reduced-motion: reduce) {
    .bell-ring, .badge-pop { animation: none; }
}
</style>
