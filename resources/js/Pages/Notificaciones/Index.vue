<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import {
    AlertTriangle, Banknote, Bell, BellOff, CheckCheck, CheckCircle2, ChevronRight, FileText, Inbox, Info, Loader2,
    Mail, MailOpen, Receipt, Scale, Search, Settings, ShieldCheck, UserRound, XCircle,
} from 'lucide-vue-next'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ConfirmDialog } from '@/Components/ui/dialog'
import { safeInternalUrl, useNotifications } from '@/Composables/useNotifications'
import { useFlashSuccess } from '@/Composables/useFlashSuccess'
import { formatDateTime, formatRelative } from '@/Utils/date'
import type { ErpNotificationItem, Paginated } from '@/types/shared'

/** Con "Ver todas las notificaciones" llegan también las de otras personas (solo lectura). */
type Row = ErpNotificationItem & { own?: boolean; recipient?: { id: number | null; name: string; email: string | null } | null }

const props = defineProps<{
    notifications: Paginated<Row>
    /** El módulo se ajusta solo según el permiso del rol. */
    alcance: 'propias' | 'todas'
    destinatarios: { id: number; nombre: string }[]
    filters: { filtro: 'todas' | 'no_leidas'; categoria: string | null; q?: string; destinatario?: number | null }
    categorias: { value: string; label: string }[]
    unreadCount: number
    unreadByCategory: Record<string, number>
}>()

useFlashSuccess()
const bell = useNotifications()

// La campana comparte estado global: se sincroniza con lo que dice el servidor.
watch(() => props.unreadCount, (n) => {
    bell.unread.value = n
    void bell.refresh()
})

const todas = computed(() => props.alcance === 'todas')
const f = reactive({ filtro: props.filters.filtro, categoria: props.filters.categoria, q: props.filters.q ?? '', destinatario: props.filters.destinatario ?? null })
const loading = ref(false)
const visitOpts = {
    preserveScroll: true,
    preserveState: true,
    onStart: () => (loading.value = true),
    onFinish: () => (loading.value = false),
}

function apply() {
    router.get(
        route('notificaciones.index'),
        {
            filtro: f.filtro !== 'todas' ? f.filtro : undefined,
            categoria: f.categoria || undefined,
            q: f.q || undefined,
            destinatario: f.destinatario || undefined,
        },
        { ...visitOpts, replace: true },
    )
}
watch(() => [f.filtro, f.categoria, f.destinatario], apply)
let qTimer: ReturnType<typeof setTimeout> | undefined
watch(() => f.q, () => {
    clearTimeout(qTimer)
    qTimer = setTimeout(apply, 350)
})

const categoryIcon: Record<string, unknown> = {
    requisiciones: FileText,
    pagos: Banknote,
    comprobaciones: Receipt,
    ajustes: Scale,
    seguridad: ShieldCheck,
    sistema: Settings,
}

const severity = {
    info: { icon: Info, chip: 'text-brand-accent bg-brand-accent/10 ring-brand-accent/20', bar: 'bg-brand-accent', label: 'Información' },
    success: { icon: CheckCircle2, chip: 'text-brand-success bg-brand-success/10 ring-brand-success/20', bar: 'bg-brand-success', label: 'Éxito' },
    warning: { icon: AlertTriangle, chip: 'text-brand-warning bg-brand-warning/10 ring-brand-warning/20', bar: 'bg-brand-warning', label: 'Atención' },
    danger: { icon: XCircle, chip: 'text-brand-danger bg-brand-danger/10 ring-brand-danger/20', bar: 'bg-brand-danger', label: 'Importante' },
} as const
const sev = (n: Row) => severity[n.severity] ?? severity.info
/** Las ajenas se consultan sin marcarlas como leídas. */
const isOwn = (n: Row) => n.own !== false

/* Agrupación por día: Hoy, Ayer o la fecha. */
const dayLabel = (iso: string | null) => {
    if (!iso) return 'Sin fecha'
    const d = new Date(iso)
    const today = new Date()
    const startOf = (x: Date) => new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime()
    const diff = Math.round((startOf(today) - startOf(d)) / 86400000)
    if (diff === 0) return 'Hoy'
    if (diff === 1) return 'Ayer'
    return new Intl.DateTimeFormat('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: d.getFullYear() === today.getFullYear() ? undefined : 'numeric' }).format(d)
}
const groups = computed(() => {
    const out: { label: string; items: Row[] }[] = []
    for (const n of props.notifications.data) {
        const label = dayLabel(n.created_at)
        const last = out[out.length - 1]
        if (last && last.label === label) last.items.push(n)
        else out.push({ label, items: [n] })
    }
    return out
})

const busyId = ref<string | null>(null)
function markRead(n: Row, then?: () => void) {
    if (n.read_at || !isOwn(n)) return then?.()
    busyId.value = n.id
    router.patch(route('notificaciones.read', n.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => then?.(),
        onFinish: () => (busyId.value = null),
    })
}

function open(n: Row) {
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

const navItem = (on: boolean) => [
    'group flex min-h-[40px] w-auto items-center gap-2 rounded-xl px-3 text-left text-sm font-medium transition lg:min-h-[44px] lg:w-full lg:gap-3',
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50',
    on
        ? 'bg-brand-primary/[0.07] text-slate-900 ring-1 ring-inset ring-brand-primary/15 dark:bg-white/[0.07] dark:text-zinc-50 dark:ring-white/10'
        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-white/[0.05] dark:hover:text-zinc-100',
]
</script>

<template>
    <Head title="Notificaciones" />

    <AuthenticatedLayout>
        <template #header>Notificaciones</template>

        <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <section data-tour="notificaciones-encabezado" class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-accent/10 text-brand-accent">
                        <Bell class="h-5 w-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">Centro de notificaciones</h2>
                        <p class="text-sm text-slate-500 dark:text-zinc-400" aria-live="polite">
                            <template v-if="todas">Avisos de todas las personas · tú tienes {{ unreadCount }} sin leer.</template>
                            <template v-else>{{ unreadCount === 0 ? 'Estás al día.' : `${unreadCount} sin leer de ${notifications.total} en esta vista.` }}</template>
                        </p>
                    </div>
                </div>
                <button v-if="unreadCount > 0" type="button" class="ui-btn-secondary self-start sm:self-auto" @click="confirmAll = true">
                    <CheckCheck class="h-4 w-4" aria-hidden="true" /> Marcar todas como leídas
                </button>
            </section>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-[17rem_minmax(0,1fr)] xl:grid-cols-[19rem_minmax(0,1fr)]">
                <!-- Panel de filtros -->
                <aside class="ui-card h-fit space-y-4 p-3 lg:sticky lg:top-20" aria-label="Filtros">
                    <div class="relative">
                        <label for="not-q" class="sr-only">Buscar</label>
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input id="not-q" v-model="f.q" type="search" class="ui-input pl-9" placeholder="Buscar en título o mensaje…" autocomplete="off" />
                    </div>
                    <div v-if="todas" data-tour="notificaciones-destinatario">
                        <SearchableSelect
                            id="not-dest"
                            v-model="f.destinatario"
                            :options="destinatarios"
                            label="Destinatario"
                            placeholder="Todas las personas"
                            search-placeholder="Buscar persona…"
                            nullable
                            null-label="Todas las personas"
                            label-key="nombre"
                            value-key="id"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-white/[0.04]" role="group" aria-label="Estado de lectura">
                        <button
                            v-for="opt in ([['todas', 'Todas', Inbox], ['no_leidas', 'No leídas', Mail]] as const)"
                            :key="opt[0]"
                            type="button"
                            class="inline-flex min-h-[38px] items-center justify-center gap-1.5 rounded-lg text-xs font-semibold transition"
                            :class="f.filtro === opt[0]
                                ? 'bg-white text-slate-900 shadow-sm dark:bg-white/10 dark:text-zinc-50'
                                : 'text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-zinc-100'"
                            :aria-pressed="f.filtro === opt[0]"
                            @click="f.filtro = opt[0]"
                        >
                            <component :is="opt[2]" class="h-3.5 w-3.5" aria-hidden="true" />
                            {{ opt[1] }}
                            <span v-if="opt[0] === 'no_leidas' && unreadCount > 0" class="rounded-full bg-brand-danger px-1.5 text-[10px] font-black text-white">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
                        </button>
                    </div>

                    <div>
                        <p class="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Categorías</p>
                        <nav class="flex flex-wrap gap-1.5 lg:flex-col lg:flex-nowrap lg:gap-1" aria-label="Categorías">
                            <button type="button" :class="navItem(!f.categoria)" :aria-pressed="!f.categoria" @click="f.categoria = null">
                                <Inbox class="h-4 w-4 shrink-0 text-slate-400 group-hover:text-current" aria-hidden="true" />
                                <span class="whitespace-nowrap lg:flex-1">Todas</span>
                                <span v-if="unreadCount" class="hidden rounded-full bg-slate-200 px-2 text-[11px] font-bold tabular-nums text-slate-600 dark:bg-white/10 dark:text-zinc-300 lg:inline">{{ unreadCount }}</span>
                            </button>
                            <button
                                v-for="c in categorias"
                                :key="c.value"
                                type="button"
                                :class="navItem(f.categoria === c.value)"
                                :aria-pressed="f.categoria === c.value"
                                @click="f.categoria = c.value"
                            >
                                <component :is="categoryIcon[c.value] ?? Bell" class="h-4 w-4 shrink-0 text-slate-400 group-hover:text-current" aria-hidden="true" />
                                <span class="whitespace-nowrap lg:flex-1">{{ c.label }}</span>
                                <span v-if="unreadByCategory[c.value]" class="hidden rounded-full bg-brand-danger/10 px-2 text-[11px] font-bold tabular-nums text-brand-danger lg:inline">
                                    {{ unreadByCategory[c.value] }}
                                </span>
                            </button>
                        </nav>
                    </div>
                </aside>

                <!-- Lista -->
                <section data-tour="notificaciones-lista" class="ui-card min-w-0 overflow-hidden transition-opacity" :class="loading ? 'opacity-60' : ''" :aria-busy="loading">
                    <div v-if="loading" class="flex items-center gap-2 border-b border-slate-100 px-5 py-2 text-xs text-slate-500 dark:border-white/[0.06] dark:text-zinc-400" role="status">
                        <Loader2 class="h-3.5 w-3.5 animate-spin" aria-hidden="true" /> Cargando…
                    </div>

                    <div v-if="notifications.data.length === 0" class="flex flex-col items-center gap-3 px-6 py-16 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-zinc-500">
                            <BellOff class="h-7 w-7" aria-hidden="true" />
                        </span>
                        <p class="text-base font-semibold text-slate-700 dark:text-zinc-200">{{ emptyText }}</p>
                        <p class="max-w-sm text-sm text-slate-500 dark:text-zinc-400">Aquí verás avisos de requisiciones, pagos, comprobaciones, ajustes y cambios de seguridad.</p>
                        <button v-if="f.categoria || f.filtro !== 'todas' || f.q || f.destinatario" type="button" class="ui-btn-secondary" @click="Object.assign(f, { filtro: 'todas', categoria: null, q: '', destinatario: null })">
                            Ver todas
                        </button>
                    </div>

                    <div v-for="g in groups" v-else :key="g.label">
                        <h3 class="border-b border-slate-100 bg-slate-50/80 px-5 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-500 backdrop-blur first-letter:uppercase dark:border-white/[0.06] dark:bg-zinc-900/95 dark:text-zinc-400">
                            {{ g.label }}
                        </h3>
                        <ul class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                            <li
                                v-for="n in g.items"
                                :key="n.id"
                                class="group relative flex gap-4 px-4 py-4 transition-colors hover:bg-slate-50 sm:px-5 dark:hover:bg-white/[0.03]"
                                :class="n.read_at ? '' : 'bg-brand-accent/[0.03]'"
                            >
                                <span v-if="!n.read_at" class="absolute inset-y-3 left-0 w-1 rounded-r-full" :class="sev(n).bar" aria-hidden="true" />

                                <span class="relative mt-0.5 block h-11 w-11 shrink-0">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 transition group-hover:scale-105 motion-reduce:transform-none dark:bg-white/[0.06] dark:text-zinc-300">
                                        <component :is="categoryIcon[n.category] ?? Bell" class="h-5 w-5" aria-hidden="true" />
                                    </span>
                                    <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full ring-2 ring-white dark:ring-zinc-900" :class="sev(n).chip">
                                        <component :is="sev(n).icon" class="h-3 w-3" aria-hidden="true" />
                                        <span class="sr-only">{{ sev(n).label }}</span>
                                    </span>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                                        <p class="min-w-0 break-words text-sm text-slate-900 [overflow-wrap:anywhere] dark:text-zinc-100" :class="n.read_at ? 'font-medium' : 'font-bold'">
                                            {{ n.title }}
                                            <span v-if="!n.read_at" class="sr-only">(sin leer)</span>
                                        </p>
                                        <time :datetime="n.created_at ?? undefined" :title="formatDateTime(n.created_at)" class="shrink-0 text-xs tabular-nums text-slate-500 dark:text-zinc-400">
                                            {{ formatRelative(n.created_at) }}
                                        </time>
                                    </div>
                                    <p class="mt-1 whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-600 [overflow-wrap:anywhere] dark:text-zinc-300">{{ n.message }}</p>
                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <span class="ui-badge" :class="sev(n).chip">{{ n.category_label }}</span>
                                        <span v-if="n.recipient" class="inline-flex max-w-full items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-white/10 dark:text-zinc-300" :title="n.recipient.email ?? undefined">
                                            <UserRound class="h-3 w-3 shrink-0" aria-hidden="true" /> <span class="truncate">Para {{ n.recipient.name }}</span> · {{ n.read_at ? 'leída' : 'sin leer' }}
                                        </span>
                                        <button v-if="safeInternalUrl(n.url)" type="button" class="ui-btn-sm" :disabled="busyId === n.id" @click="open(n)">
                                            Ver detalle <ChevronRight class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5 motion-reduce:transform-none" aria-hidden="true" />
                                        </button>
                                        <button v-if="!n.read_at && isOwn(n)" type="button" class="ui-btn-sm" :disabled="busyId === n.id" @click="markRead(n)">
                                            <Loader2 v-if="busyId === n.id" class="h-3.5 w-3.5 animate-spin" aria-hidden="true" />
                                            <MailOpen v-else class="h-3.5 w-3.5" aria-hidden="true" />
                                            Marcar como leída
                                        </button>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <nav v-if="notifications.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 dark:border-white/[0.06]" aria-label="Paginación">
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
