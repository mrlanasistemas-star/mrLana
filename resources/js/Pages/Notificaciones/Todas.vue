<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Eye, ExternalLink, Inbox, Loader2, Mail, MailOpen, Search, ShieldCheck, X } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import { safeInternalUrl } from '@/Composables/useNotifications'
import { formatDateTime, formatRelative } from '@/Utils/date'
import type { ErpNotificationItem, Paginated } from '@/types/shared'

/**
 * Consulta administrativa de todas las notificaciones (solo lectura).
 * Abrir un enlace desde aquí NO marca la notificación como leída para su
 * destinatario: esta vista nunca llama a los endpoints de lectura.
 */
type Row = ErpNotificationItem & { recipient: { id: number; name: string; email: string } | null }

const props = defineProps<{
    notifications: Paginated<Row>
    filters: { estado: 'todas' | 'leidas' | 'no_leidas'; categoria: string | null; q: string; destinatario: number | null }
    categorias: { value: string; label: string }[]
    destinatarios: { id: number; nombre: string }[]
}>()

const f = reactive({ ...props.filters })
const loading = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

function apply() {
    router.get(
        route('notificaciones.all'),
        {
            estado: f.estado !== 'todas' ? f.estado : undefined,
            categoria: f.categoria || undefined,
            q: f.q || undefined,
            destinatario: f.destinatario || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true, onStart: () => (loading.value = true), onFinish: () => (loading.value = false) },
    )
}

watch(() => [f.estado, f.categoria, f.destinatario], apply)
watch(() => f.q, () => {
    clearTimeout(timer)
    timer = setTimeout(apply, 350)
})

const clear = () => Object.assign(f, { estado: 'todas', categoria: null, q: '', destinatario: null })
const goPage = (url: string | null) => url && router.visit(url, { preserveScroll: true, preserveState: true })
const pageLabel = (l: string) =>
    l.replace('&laquo;', '«').replace('&raquo;', '»').replace(/Previous|pagination\.previous/i, 'Anterior').replace(/Next|pagination\.next/i, 'Siguiente')

const estados = [
    { value: 'todas', label: 'Todas' },
    { value: 'no_leidas', label: 'No leídas' },
    { value: 'leidas', label: 'Leídas' },
] as const

const sevClass: Record<string, string> = {
    info: 'bg-brand-accent',
    success: 'bg-brand-success',
    warning: 'bg-brand-warning',
    danger: 'bg-brand-danger',
}
</script>

<template>
    <Head title="Todas las notificaciones" />

    <AuthenticatedLayout>
        <template #header>Todas las notificaciones</template>

        <div class="w-full min-w-0 space-y-4 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <section class="ui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5" data-tour="notificaciones-todas-header">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-accent/10 text-brand-accent">
                        <ShieldCheck class="h-5 w-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-black tracking-tight text-slate-900 dark:text-zinc-100">Consulta de notificaciones</h2>
                        <p class="text-sm text-slate-500 dark:text-zinc-400">
                            Solo lectura. Abrir un aviso desde aquí no lo marca como leído para su destinatario.
                        </p>
                    </div>
                </div>
                <Link :href="route('notificaciones.index')" class="ui-btn-secondary shrink-0">Mis notificaciones</Link>
            </section>

            <!-- Filtros -->
            <section class="ui-card grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Filtros" data-tour="notificaciones-todas-filtros">
                <div class="relative min-w-0">
                    <label for="nt-q" class="ui-label">Buscar</label>
                    <Search class="pointer-events-none absolute left-3 top-[38px] h-4 w-4 text-slate-400" aria-hidden="true" />
                    <input id="nt-q" v-model="f.q" type="search" class="ui-input pl-9" placeholder="Título o mensaje…" autocomplete="off" />
                </div>
                <SearchableSelect
                    id="nt-dest"
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
                <div class="min-w-0">
                    <label for="nt-cat" class="ui-label">Categoría</label>
                    <select id="nt-cat" v-model="f.categoria" class="ui-input">
                        <option :value="null">Todas</option>
                        <option v-for="c in categorias" :key="c.value" :value="c.value">{{ c.label }}</option>
                    </select>
                </div>
                <div class="min-w-0">
                    <span class="ui-label">Estado</span>
                    <div class="flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-white/5" role="radiogroup" aria-label="Estado de lectura">
                        <button
                            v-for="e in estados"
                            :key="e.value"
                            type="button"
                            role="radio"
                            :aria-checked="f.estado === e.value"
                            class="min-h-[36px] flex-1 rounded-lg px-2 text-xs font-semibold transition-colors duration-150"
                            :class="f.estado === e.value ? 'bg-white text-slate-900 shadow-sm dark:bg-zinc-800 dark:text-zinc-100' : 'text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-zinc-100'"
                            @click="f.estado = e.value"
                        >
                            {{ e.label }}
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-2 sm:col-span-2 xl:col-span-4">
                    <p class="text-xs text-slate-500 dark:text-zinc-400">
                        <Loader2 v-if="loading" class="mr-1 inline h-3.5 w-3.5 animate-spin" aria-hidden="true" />
                        {{ notifications.total }} notificación(es)
                    </p>
                    <button type="button" class="ui-btn-sm" @click="clear"><X class="h-3.5 w-3.5" aria-hidden="true" /> Limpiar</button>
                </div>
            </section>

            <!-- Listado en tarjetas (sin tablas incómodas en móvil) -->
            <ul class="space-y-2" :class="loading ? 'opacity-60 transition-opacity' : ''">
                <li
                    v-for="n in notifications.data"
                    :key="n.id"
                    class="group relative min-w-0 overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm transition-all duration-200
                           hover:-translate-y-px hover:shadow-md dark:border-white/10 dark:bg-neutral-900/90 motion-reduce:transform-none"
                >
                    <span class="absolute inset-y-0 left-0 w-1" :class="sevClass[n.severity] ?? sevClass.info" aria-hidden="true" />
                    <div class="flex flex-col gap-3 pl-2 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="ui-badge">{{ n.category_label }}</span>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="n.read_at ? 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-zinc-300' : 'bg-brand-accent/10 text-brand-accent'"
                                >
                                    <component :is="n.read_at ? MailOpen : Mail" class="h-3 w-3" aria-hidden="true" />
                                    {{ n.read_at ? 'Leída' : 'No leída' }}
                                </span>
                                <time class="text-[11px] text-slate-500 dark:text-zinc-400" :datetime="n.created_at ?? undefined" :title="formatDateTime(n.created_at)">
                                    {{ formatRelative(n.created_at) }}
                                </time>
                            </div>
                            <p class="mt-2 break-words text-sm font-bold text-slate-900 dark:text-zinc-100">{{ n.title }}</p>
                            <p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-600 dark:text-zinc-300">{{ n.message }}</p>
                        </div>
                        <div class="flex min-w-0 shrink-0 flex-col gap-2 lg:w-64 lg:items-end">
                            <p class="min-w-0 text-xs text-slate-500 dark:text-zinc-400 lg:text-right">
                                <span class="block font-semibold text-slate-700 dark:text-zinc-200">{{ n.recipient?.name ?? 'Cuenta eliminada' }}</span>
                                <span class="block break-all">{{ n.recipient?.email }}</span>
                            </p>
                            <a
                                v-if="safeInternalUrl(n.url)"
                                :href="safeInternalUrl(n.url)!"
                                class="ui-btn-sm w-full justify-center transition-transform hover:-translate-y-px lg:w-auto"
                                title="Abre el registro relacionado (se vuelve a validar tu acceso)"
                            >
                                <Eye class="h-3.5 w-3.5" aria-hidden="true" /> Abrir registro <ExternalLink class="h-3 w-3 opacity-60" aria-hidden="true" />
                            </a>
                        </div>
                    </div>
                </li>
            </ul>

            <div v-if="notifications.data.length === 0" class="ui-card flex flex-col items-center gap-2 p-10 text-center">
                <Inbox class="h-8 w-8 text-slate-300" aria-hidden="true" />
                <p class="text-sm font-semibold text-slate-500 dark:text-zinc-400">No hay notificaciones con estos filtros.</p>
            </div>

            <nav v-if="notifications.last_page > 1" class="flex flex-wrap items-center justify-center gap-1.5" aria-label="Paginación">
                <button
                    v-for="l in notifications.links"
                    :key="l.label + String(l.url)"
                    type="button"
                    :disabled="!l.url"
                    class="inline-flex h-10 min-w-10 items-center justify-center rounded-xl border px-3 text-xs font-bold transition disabled:opacity-40"
                    :class="l.active ? 'border-slate-900 bg-slate-900 text-white dark:border-white/20 dark:bg-white/15' : 'border-slate-200 bg-white hover:bg-slate-50 dark:border-white/10 dark:bg-white/5'"
                    @click="goPage(l.url)"
                >
                    {{ pageLabel(l.label) }}
                </button>
            </nav>
        </div>
    </AuthenticatedLayout>
</template>
