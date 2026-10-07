<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { AlertTriangle, BellOff, CheckCheck, CheckCircle2, ExternalLink, Info, Loader2, XCircle } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ConfirmDialog } from '@/Components/ui/dialog'
import { safeInternalUrl, useNotifications } from '@/Composables/useNotifications'
import { useFlashSuccess } from '@/Composables/useFlashSuccess'
import { formatDateTime, formatRelative } from '@/Utils/date'
import type { ErpNotificationItem, Paginated } from '@/types/shared'

const props = defineProps<{
    notifications: Paginated<ErpNotificationItem>
    filters: { filtro: 'todas' | 'no_leidas'; categoria: string | null }
    categorias: { value: string; label: string }[]
    unreadCount: number
}>()

useFlashSuccess()
const bell = useNotifications()

// La campana comparte estado global: se sincroniza con lo que dice el servidor.
watch(() => props.unreadCount, (n) => {
    bell.unread.value = n
    void bell.refresh()
})

const f = reactive({ filtro: props.filters.filtro, categoria: props.filters.categoria })
const loading = ref(false)
const visitOpts = {
    preserveScroll: true,
    preserveState: true,
    onStart: () => (loading.value = true),
    onFinish: () => (loading.value = false),
}

watch(() => [f.filtro, f.categoria], () => {
    router.get(
        route('notificaciones.index'),
        { filtro: f.filtro !== 'todas' ? f.filtro : undefined, categoria: f.categoria || undefined },
        { ...visitOpts, replace: true },
    )
})

const severity = {
    info: { icon: Info, cls: 'text-brand-accent bg-brand-accent/10', bar: 'bg-brand-accent', label: 'Información' },
    success: { icon: CheckCircle2, cls: 'text-brand-success bg-brand-success/10', bar: 'bg-brand-success', label: 'Éxito' },
    warning: { icon: AlertTriangle, cls: 'text-brand-warning bg-brand-warning/10', bar: 'bg-brand-warning', label: 'Atención' },
    danger: { icon: XCircle, cls: 'text-brand-danger bg-brand-danger/10', bar: 'bg-brand-danger', label: 'Importante' },
} as const
const sev = (n: ErpNotificationItem) => severity[n.severity] ?? severity.info

const busyId = ref<string | null>(null)
function markRead(n: ErpNotificationItem, then?: () => void) {
    if (n.read_at) return then?.()
    busyId.value = n.id
    router.patch(route('notificaciones.read', n.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => then?.(),
        onFinish: () => (busyId.value = null),
    })
}

function open(n: ErpNotificationItem) {
    const url = safeInternalUrl(n.url)
    markRead(n, () => url && router.visit(url))
}

const confirmAll = ref(false)
const markingAll = ref(false)
function markAll() {
    markingAll.value = true
    router.post(route('notificaciones.readAll'), {}, {
        preserveScroll: true,
        onSuccess: () => (confirmAll.value = false),
        onFinish: () => (markingAll.value = false),
    })
}

const goPage = (url: string | null) => url && router.visit(url, visitOpts)
const pageLabel = (l: string) =>
    l.replace('&laquo;', '«').replace('&raquo;', '»').replace(/Previous|pagination\.previous/i, 'Anterior').replace(/Next|pagination\.next/i, 'Siguiente')

const emptyText = computed(() => {
    if (f.filtro === 'no_leidas' && !f.categoria) return 'No tienes notificaciones sin leer.'
    if (f.categoria || f.filtro === 'no_leidas') return 'No hay notificaciones con estos filtros.'
    return 'Aún no tienes notificaciones.'
})
</script>

<template>
    <Head title="Notificaciones" />

    <AuthenticatedLayout>
        <template #header>Notificaciones</template>

        <div class="mx-auto w-full min-w-0 max-w-5xl space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <section class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">Centro de notificaciones</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400" aria-live="polite">
                        {{ unreadCount === 0 ? 'Estás al día.' : `${unreadCount} sin leer.` }}
                    </p>
                </div>
                <button v-if="unreadCount > 0" type="button" class="ui-btn-secondary self-start sm:self-auto" @click="confirmAll = true">
                    <CheckCheck class="h-4 w-4" aria-hidden="true" /> Marcar todas como leídas
                </button>
            </section>

            <section class="ui-card space-y-3 p-4" aria-label="Filtros">
                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Estado de lectura">
                    <button
                        v-for="opt in ([['todas', 'Todas'], ['no_leidas', 'No leídas']] as const)"
                        :key="opt[0]"
                        type="button"
                        class="ui-chip"
                        :class="f.filtro === opt[0] ? 'ui-chip-on' : ''"
                        :aria-pressed="f.filtro === opt[0]"
                        @click="f.filtro = opt[0]"
                    >
                        {{ opt[1] }}
                        <span v-if="opt[0] === 'no_leidas' && unreadCount > 0" class="rounded-full bg-brand-danger px-1.5 text-[10px] font-black text-brand-danger-fg">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
                    </button>
                    <span v-if="loading" class="inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-zinc-400" role="status">
                        <Loader2 class="h-3.5 w-3.5 animate-spin" aria-hidden="true" /> Cargando…
                    </span>
                </div>
                <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 sm:flex-wrap sm:overflow-visible" role="group" aria-label="Categoría">
                    <button type="button" class="ui-chip shrink-0" :class="!f.categoria ? 'ui-chip-on' : ''" :aria-pressed="!f.categoria" @click="f.categoria = null">
                        Todas las categorías
                    </button>
                    <button
                        v-for="c in categorias"
                        :key="c.value"
                        type="button"
                        class="ui-chip shrink-0"
                        :class="f.categoria === c.value ? 'ui-chip-on' : ''"
                        :aria-pressed="f.categoria === c.value"
                        @click="f.categoria = c.value"
                    >
                        {{ c.label }}
                    </button>
                </div>
            </section>

            <section class="ui-card overflow-hidden transition-opacity" :class="loading ? 'opacity-60' : ''" :aria-busy="loading">
                <div v-if="notifications.data.length === 0" class="flex flex-col items-center gap-3 p-10 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-zinc-400">
                        <BellOff class="h-6 w-6" aria-hidden="true" />
                    </span>
                    <p class="text-sm font-semibold text-slate-700 dark:text-zinc-200">{{ emptyText }}</p>
                    <button v-if="f.categoria || f.filtro !== 'todas'" type="button" class="ui-btn-secondary" @click="Object.assign(f, { filtro: 'todas', categoria: null })">
                        Ver todas
                    </button>
                </div>

                <ul v-else class="divide-y divide-slate-100 dark:divide-white/5">
                    <li
                        v-for="n in notifications.data"
                        :key="n.id"
                        class="relative flex gap-3 p-4 transition sm:gap-4 sm:px-5"
                        :class="n.read_at ? '' : 'bg-slate-50/70 dark:bg-white/[0.03]'"
                    >
                        <span v-if="!n.read_at" class="absolute inset-y-3 left-0 w-1 rounded-r-full" :class="sev(n).bar" aria-hidden="true" />
                        <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="sev(n).cls">
                            <component :is="sev(n).icon" class="h-4 w-4" aria-hidden="true" />
                            <span class="sr-only">{{ sev(n).label }}</span>
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                                <p class="min-w-0 break-words text-sm text-slate-900 [overflow-wrap:anywhere] dark:text-zinc-100" :class="n.read_at ? 'font-medium' : 'font-bold'">
                                    {{ n.title }}
                                    <span v-if="!n.read_at" class="sr-only">(sin leer)</span>
                                </p>
                                <time
                                    :datetime="n.created_at ?? undefined"
                                    :title="formatDateTime(n.created_at)"
                                    class="shrink-0 text-xs text-slate-500 dark:text-zinc-400"
                                >
                                    {{ formatRelative(n.created_at) }}
                                </time>
                            </div>
                            <p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-600 [overflow-wrap:anywhere] dark:text-zinc-300">{{ n.message }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span class="ui-badge ui-badge-muted">{{ n.category_label }}</span>
                                <button
                                    v-if="safeInternalUrl(n.url)"
                                    type="button"
                                    class="ui-btn-sm"
                                    :disabled="busyId === n.id"
                                    @click="open(n)"
                                >
                                    <ExternalLink class="h-3.5 w-3.5" aria-hidden="true" /> Ver detalle
                                </button>
                                <button
                                    v-if="!n.read_at"
                                    type="button"
                                    class="ui-btn-sm"
                                    :disabled="busyId === n.id"
                                    @click="markRead(n)"
                                >
                                    <Loader2 v-if="busyId === n.id" class="h-3.5 w-3.5 animate-spin" aria-hidden="true" />
                                    <CheckCheck v-else class="h-3.5 w-3.5" aria-hidden="true" />
                                    Marcar como leída
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>

                <nav v-if="notifications.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 dark:border-white/5" aria-label="Paginación">
                    <p class="text-xs text-slate-500 dark:text-zinc-400">Mostrando {{ notifications.from }}–{{ notifications.to }} de {{ notifications.total }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="(l, i) in notifications.links"
                            :key="i"
                            type="button"
                            class="min-h-[38px] min-w-[38px] rounded-xl px-3 text-xs font-semibold transition disabled:opacity-40"
                            :class="l.active ? 'bg-brand-primary text-brand-primary-fg dark:bg-brand-primary/20 dark:text-zinc-50' : 'border border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5'"
                            :disabled="!l.url"
                            :aria-current="l.active ? 'page' : undefined"
                            @click="goPage(l.url)"
                        >
                            {{ pageLabel(l.label) }}
                        </button>
                    </div>
                </nav>
            </section>
        </div>

        <ConfirmDialog
            v-model:open="confirmAll"
            title="Marcar todas como leídas"
            :description="`Se marcarán ${unreadCount} notificación(es) como leídas. Seguirán disponibles en este centro.`"
            confirm-label="Marcar todas"
            :loading="markingAll"
            @confirm="markAll"
        />
    </AuthenticatedLayout>
</template>
