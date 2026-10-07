<script setup lang="ts">
    /**
     * ==========================================================
     * SystemLogs/Index.vue
     * ----------------------------------------------------------
     * - Filtros realtime con debounce + Inertia router.get
     * - Responsive: cards en móvil/tablet, tabla solo xl+
     * - Dark mode (colores neutros)
     * - Detalle SweetAlert2 responsive (max-width inteligente)
     * ==========================================================
     */

    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import { Head, router } from '@inertiajs/vue3'
    import { computed, reactive, watch } from 'vue'
    import Swal from 'sweetalert2'

    type UserMini = { id: number; name: string; email?: string; rol?: string }

    type SystemLogRow = {
        id: number
        user_id: number | null
        accion: string | null
        tabla: string | null
        registro_id: string | number | null
        ip_address: string | null
        user_agent: string | null
        descripcion: string | null
        created_at: string
        user?: UserMini | null
    }

    type PaginationLink = { url: string | null; label: string; active: boolean }

    const props = defineProps<{
        logs: {
            data: SystemLogRow[]
            links: PaginationLink[]
            current_page: number
            last_page: number
            total: number
            per_page: number
            from: number | null
            to: number | null
        }
        filters: {
            from: string
            to: string
            tabla: string
            accion: string
            user_id: number | null
            ip: string
            q: string
            perPage: number
        }
        tablas: string[]
        acciones: string[]
        usuarios: { id: number; name: string }[]
    }>()

    /**
     * Estado UI:
     * user_id en select -> '' cuando es “Todos”
     */
    const state = reactive({
        from: props.filters.from ?? '',
        to: props.filters.to ?? '',
        tabla: props.filters.tabla ?? '',
        accion: props.filters.accion ?? '',
        user_id: (props.filters.user_id ?? '') as number | '',
        ip: props.filters.ip ?? '',
        q: props.filters.q ?? '',
        perPage: props.filters.perPage ?? 15,
    })

    const defaultPerPage = 15

    const hasActiveFilters = computed(() => {
        return Boolean(
            state.from ||
            state.to ||
            state.tabla ||
            state.accion ||
            state.user_id ||
            state.ip ||
            state.q ||
            Number(state.perPage) !== defaultPerPage
        )
    })

    /**
     * Chips de filtros activos (para tablet/móvil queda clarísimo “qué está pegando”)
     */
    const activeFilterChips = computed(() => {
        const chips: Array<{ key: string; label: string; value: string }> = []

        if (state.from) chips.push({ key: 'from', label: 'Desde', value: state.from })
        if (state.to) chips.push({ key: 'to', label: 'Hasta', value: state.to })
        if (state.tabla) chips.push({ key: 'tabla', label: 'Tabla', value: state.tabla })
        if (state.accion) chips.push({ key: 'accion', label: 'Acción', value: state.accion })
        if (state.user_id) {
            const u = props.usuarios.find((x) => x.id === state.user_id)
            chips.push({ key: 'user_id', label: 'Usuario', value: u?.name ?? String(state.user_id) })
        }
        if (state.ip) chips.push({ key: 'ip', label: 'IP', value: state.ip })
        if (state.q) chips.push({ key: 'q', label: 'Búsqueda', value: state.q })
        if (Number(state.perPage) !== defaultPerPage) chips.push({ key: 'perPage', label: 'Por página', value: String(state.perPage) })

        return chips
    })

    let t: number | undefined

    function applyFilters() {
        router.get(
            route('systemlogs.index'),
            {
            from: state.from || undefined,
            to: state.to || undefined,
            tabla: state.tabla || undefined,
            accion: state.accion || undefined,
            user_id: state.user_id || undefined,
            ip: state.ip || undefined,
            q: state.q || undefined,
            perPage: state.perPage || defaultPerPage,
            },
            {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            }
        )
    }

    watch(
        () => ({ ...state }),
        () => {
            if (t) window.clearTimeout(t)
            t = window.setTimeout(() => applyFilters(), 320)
        },
        { deep: true }
    )

    function clearFilters() {
        state.from = ''
        state.to = ''
        state.tabla = ''
        state.accion = ''
        state.user_id = ''
        state.ip = ''
        state.q = ''
        state.perPage = defaultPerPage
        applyFilters()
    }

    function clearOneFilter(key: string) {
        if (key === 'from') state.from = ''
        if (key === 'to') state.to = ''
        if (key === 'tabla') state.tabla = ''
        if (key === 'accion') state.accion = ''
        if (key === 'user_id') state.user_id = ''
        if (key === 'ip') state.ip = ''
        if (key === 'q') state.q = ''
        if (key === 'perPage') state.perPage = defaultPerPage
        applyFilters()
    }

    function goTo(url: string | null) {
        if (!url) return
        router.visit(url, { preserveState: true, preserveScroll: true })
    }

    /**
     * Labels paginación: limpia HTML + entidades
     */
    function formatLabel(label: string) {
        const cleaned = String(label)
            .replace(/&laquo;|&raquo;|&hellip;/g, '')
            .replace(/<[^>]*>/g, '')
            .trim()

        const low = cleaned.toLowerCase()
        if (low.includes('previous') || low.includes('anterior')) return 'Atrás'
        if (low.includes('next') || low.includes('siguiente')) return 'Siguiente'

        return cleaned || '…'
    }

    /**
     * SweetAlert2 theme classes
     */
    const isDark = computed(() => document.documentElement.classList.contains('dark'))

    function swalBaseClasses() {
        return {
            popup:
            'rounded-3xl shadow-2xl border ' +
            'border-slate-200/70 dark:border-white/10 ' +
            'bg-white dark:bg-neutral-950 text-slate-900 dark:text-neutral-100',
            title: 'text-slate-900 dark:text-neutral-100',
            htmlContainer: 'text-slate-700 dark:text-neutral-200 !m-0',
            confirmButton:
            'rounded-2xl px-4 py-2 font-semibold bg-slate-900 text-white hover:bg-slate-800 ' +
            'dark:bg-zinc-300 dark:text-neutral-900 dark:hover:bg-zinc-200 transition active:scale-[0.98]',
        }
    }

    function escapeHtml(value: string) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
    }

    function formatDateTime(value: string): { date: string; time: string } {
        const d = new Date(value)

        if (Number.isNaN(d.getTime())) {
            return { date: '—', time: '' }
        }

        return {
            date: d.toLocaleDateString('es-MX', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            }),
            time: d.toLocaleTimeString('es-MX', {
            hour: '2-digit',
            minute: '2-digit',
            }),
        }
    }

    /**
     * Badges sobrios por acción (sin colores chillones).
     * Nota: ya registraremos "ELIMINACION" cuando activo pase a false (Trait).
     */
    function badgeForAccion(a: string | null) {
        const v = (a ?? '').toUpperCase()

        if (v.includes('ELIM')) {
            return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/20'
        }
        if (v.includes('ACTIVACION') || (v.includes('ACT') && !v.includes('ACTUAL'))) {
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20'
        }
        if (v.includes('CRE')) {
            return 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-white/5 dark:text-neutral-100 dark:border-white/10'
        }
        if (v.includes('LOGIN')) {
            return 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-white/5 dark:text-neutral-100 dark:border-white/10'
        }

        // ACTUALIZACION / otros
        return 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-white/5 dark:text-neutral-100 dark:border-white/10'
    }

    /**
     * SweetAlert2: detalle responsive
     * - width dinámico: en móvil no “revienta”
     */
    function openDetail(row: SystemLogRow) {
        const userName = row.user?.name ?? 'N/A'
        const userEmail = row.user?.email ?? ''
        const accion = row.accion ?? 'N/A'
        const tabla = row.tabla ?? 'N/A'
        const registro = row.registro_id ?? 'N/A'
        const ip = row.ip_address ?? 'N/A'
        const ua = row.user_agent ?? 'N/A'
        const desc = row.descripcion ?? 'Sin descripción'
        const fecha = row.created_at

        const vw = typeof window !== 'undefined' ? window.innerWidth : 1200
        const width = Math.min(900, Math.max(320, Math.floor(vw * 0.92)))

        Swal.fire({
            title: 'Detalle del log',
            confirmButtonText: 'Cerrar',
            customClass: swalBaseClasses(),
            width,
            html: `
            <div class="text-left">
                <div class="grid gap-3">
                <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 p-4">
                    <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-slate-900 dark:text-neutral-100">${escapeHtml(userName)}</div>
                        ${
                        userEmail
                            ? `<div class="text-xs text-slate-600 dark:text-neutral-300">${escapeHtml(userEmail)}</div>`
                            : `<div class="text-xs text-slate-500 dark:text-neutral-400">Sin email</div>`
                        }
                        <div class="text-xs text-slate-500 dark:text-neutral-400 mt-1">Usuario ID: ${row.user_id ?? 'N/A'}</div>
                    </div>
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold ${badgeForAccion(accion)}">
                        ${escapeHtml(accion)}
                    </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-white dark:bg-neutral-950/40 p-4">
                    <div class="text-xs font-semibold text-slate-600 dark:text-neutral-300">Tabla</div>
                    <div class="text-sm font-semibold text-slate-900 dark:text-neutral-100 mt-1">${escapeHtml(tabla)}</div>
                    <div class="text-xs text-slate-500 dark:text-neutral-400 mt-1">Registro: ${escapeHtml(String(registro))}</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-white dark:bg-neutral-950/40 p-4">
                    <div class="text-xs font-semibold text-slate-600 dark:text-neutral-300">Origen</div>
                    <div class="text-sm font-semibold text-slate-900 dark:text-neutral-100 mt-1">${escapeHtml(ip)}</div>
                    <div class="text-xs text-slate-500 dark:text-neutral-400 mt-1">Fecha: ${escapeHtml(fecha)}</div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-white dark:bg-neutral-950/40 p-4">
                    <div class="text-xs font-semibold text-slate-600 dark:text-neutral-300">Descripción</div>
                    <div class="text-sm text-slate-800 dark:text-neutral-100 mt-2 leading-relaxed">
                    ${escapeHtml(desc).replace(/\n/g, '<br/>')}
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-white dark:bg-neutral-950/40 p-4">
                    <div class="text-xs font-semibold text-slate-600 dark:text-neutral-300">Agente</div>
                    <div class="text-xs text-slate-700 dark:text-neutral-200 mt-2 break-words">
                    ${escapeHtml(ua)}
                    </div>
                </div>
                </div>
            </div>
            `,
            didOpen: () => {
            // no “inventamos” clases, solo respetamos el html + el theme
            const popup = Swal.getPopup()
            if (popup) popup.classList.toggle('dark', isDark.value)
            },
        })
    }
</script>

<template>
    <Head title="Logs" />

    <AuthenticatedLayout>
        <template #header>
        <div class="min-w-0">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-neutral-100">Logs del sistema</h2>
        </div>
        </template>

        <div class="w-full min-w-0 overflow-x-hidden px-3 sm:px-6 lg:px-8 py-4 sm:py-6">
            <div class="w-full max-w-none mx-0 min-w-0">
                <div class="rounded-2xl border border-slate-200/70 bg-white shadow-sm
                    dark:border-white/10 dark:bg-neutral-950/60">
                    <!-- Header + filtros -->
                    <div class="p-4 sm:p-5 border-b border-slate-200/70 dark:border-white/10">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-slate-900 dark:text-neutral-100">
                                Auditoría operativa
                                </div>
                                <div class="text-xs text-slate-500 dark:text-neutral-400">
                                Filtra por tabla/acción/usuario y acota por fechas.
                                </div>
                            </div>

                            <button type="button" @click="clearFilters"
                                :disabled="!hasActiveFilters"
                                class="rounded-xl px-4 py-2 text-sm font-semibold
                                border border-slate-200 bg-slate-50 text-slate-800
                                hover:bg-slate-100 active:scale-[0.99] transition
                                disabled:opacity-50 disabled:cursor-not-allowed
                                dark:border-white/10 dark:bg-white/5 dark:text-neutral-100 dark:hover:bg-white/10">
                                Limpiar filtros
                            </button>
                        </div>

                        <!-- Chips -->
                        <div v-if="activeFilterChips.length" class="mt-3 flex flex-wrap gap-2">
                            <button v-for="c in activeFilterChips" :key="c.key"
                                type="button" @click="clearOneFilter(c.key)"
                                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold
                                border-slate-200 bg-white text-slate-700 hover:bg-slate-50
                                dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-200 dark:hover:bg-white/5"
                                :title="`Quitar filtro: ${c.label}`">
                                <span class="text-slate-500 dark:text-neutral-400">{{ c.label }}:</span>
                                <span class="truncate max-w-[180px]">{{ c.value }}</span>
                                <span class="text-slate-400 dark:text-neutral-500">×</span>
                            </button>
                        </div>

                        <!-- Grid filtros -->
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 w-full min-w-0">
                            <div class="min-w-0">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Desde</label>
                                <input v-model="state.from"
                                type="date" class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 bg-white text-slate-900
                                    focus:border-slate-400 focus:ring-0
                                    dark:border-white/10 dark:bg-neutral-900/60 dark:text-neutral-100"/>
                            </div>

                            <div class="min-w-0">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Hasta</label>
                                <input v-model="state.to" type="date"
                                class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 bg-white text-slate-900
                                focus:border-slate-400 focus:ring-0
                                dark:border-white/10 dark:bg-neutral-900/60 dark:text-neutral-100"/>
                            </div>

                            <div class="min-w-0">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Tabla</label>
                                <select v-model="state.tabla"
                                class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 bg-white text-slate-900
                                focus:border-slate-400 focus:ring-0
                                dark:border-white/10 dark:bg-neutral-900/60 dark:text-neutral-100">
                                    <option value="">Todas</option>
                                    <option v-for="t in props.tablas" :key="t" :value="t">{{ t }}</option>
                                </select>
                            </div>

                            <div class="min-w-0">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Acción</label>
                                <select v-model="state.accion"
                                class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 bg-white text-slate-900
                                focus:border-slate-400 focus:ring-0
                                dark:border-white/10 dark:bg-neutral-900/60 dark:text-neutral-100">
                                    <option value="">Todas</option>
                                    <option v-for="a in props.acciones" :key="a" :value="a">{{ a }}</option>
                                </select>
                            </div>

                            <div class="min-w-0">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Usuario</label>
                                <select v-model="state.user_id"
                                class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 bg-white text-slate-900
                                focus:border-slate-400 focus:ring-0
                                dark:border-white/10 dark:bg-neutral-900/60 dark:text-neutral-100">
                                    <option value="">Todos</option>
                                    <option v-for="u in props.usuarios" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                            </div>

                            <div class="min-w-0">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">IP</label>
                                <input v-model="state.ip" type="text"
                                placeholder="Ej. 192.168"
                                class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400
                                focus:border-slate-400 focus:ring-0
                                dark:border-white/10 dark:bg-neutral-900/60 dark:text-neutral-100 dark:placeholder:text-neutral-500"/>
                            </div>

                            <div class="sm:col-span-2 lg:col-span-4 min-w-0">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Búsqueda</label>
                                <input v-model="state.q" type="text"
                                placeholder="Descripción, tabla, acción, registro, IP..."
                                class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400
                                        focus:border-slate-400 focus:ring-0
                                        dark:border-white/10 dark:bg-neutral-900/60 dark:text-neutral-100 dark:placeholder:text-neutral-500"/>
                            </div>

                            <div class="min-w-0">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Por página</label>
                                <select v-model="state.perPage"
                                class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 bg-white text-slate-900
                                focus:border-slate-400 focus:ring-0
                                dark:border-white/10 dark:bg-neutral-900/60 dark:text-neutral-100">
                                    <option :value="10">10</option>
                                    <option :value="15">15</option>
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                    <option :value="100">100</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="text-sm text-slate-600 dark:text-neutral-300">
                                Mostrando
                                <span class="font-semibold text-slate-900 dark:text-neutral-100">{{ props.logs.from ?? 0 }}</span>
                                a
                                <span class="font-semibold text-slate-900 dark:text-neutral-100">{{ props.logs.to ?? 0 }}</span>
                                de
                                <span class="font-semibold text-slate-900 dark:text-neutral-100">{{ props.logs.total }}</span>
                            </div>

                            <div class="text-xs text-slate-500 dark:text-neutral-400">
                                Consejo: tabla + fechas = auditoría express.
                            </div>
                        </div>
                    </div>

                    <!-- TABLA solo xl+ -->
                    <div class="hidden xl:block">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 dark:bg-white/5">
                                    <tr class="text-left text-slate-600 dark:text-neutral-300">
                                        <th class="px-4 py-3 font-semibold">Fecha</th>
                                        <th class="px-4 py-3 font-semibold">Usuario</th>
                                        <th class="px-4 py-3 font-semibold">Acción</th>
                                        <th class="px-4 py-3 font-semibold">Tabla</th>
                                        <th class="px-4 py-3 font-semibold">Registro</th>
                                        <th class="px-4 py-3 font-semibold">IP</th>
                                        <th class="px-4 py-3 font-semibold text-right">Detalle</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr v-for="row in props.logs.data" :key="row.id"
                                    class="border-t border-slate-200/70 hover:bg-slate-50/60 transition
                                    dark:border-white/10 dark:hover:bg-white/5">
                                        <td class="px-4 py-3 whitespace-nowrap text-slate-900 dark:text-neutral-100">
                                            <div class="leading-tight">
                                                <div class="font-semibold">{{ formatDateTime(row.created_at).date }}</div>
                                                <div class="text-xs text-slate-500 dark:text-neutral-400">{{ formatDateTime(row.created_at).time }}</div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-slate-900 dark:text-neutral-100">
                                                {{ row.user?.name ?? 'N/A' }}
                                            </div>
                                            <div class="text-xs text-slate-500 dark:text-neutral-400">
                                                ID: {{ row.user_id ?? 'N/A' }}
                                            </div>
                                        </td>

                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                                                :class="badgeForAccion(row.accion)">
                                                {{ row.accion ?? 'N/A' }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-3 whitespace-nowrap text-slate-900 dark:text-neutral-100">
                                            {{ row.tabla ?? 'N/A' }}
                                        </td>

                                        <td class="px-4 py-3 whitespace-nowrap text-slate-900 dark:text-neutral-100">
                                            {{ row.registro_id ?? 'N/A' }}
                                        </td>

                                        <td class="px-4 py-3 whitespace-nowrap text-slate-900 dark:text-neutral-100">
                                            {{ row.ip_address ?? 'N/A' }}
                                        </td>

                                        <td class="px-4 py-3 whitespace-nowrap text-right">
                                            <button type="button"
                                                @click="openDetail(row)"
                                                class="rounded-xl px-3 py-1.5 text-sm font-semibold
                                                bg-slate-900 text-white hover:bg-slate-800 active:scale-[0.99] transition
                                                dark:bg-zinc-300 dark:text-neutral-900 dark:hover:bg-zinc-200">
                                                Mostrar
                                            </button>
                                        </td>
                                    </tr>

                                    <tr v-if="props.logs.data.length === 0">
                                        <td colspan="7" class="px-4 py-10 text-center text-slate-600 dark:text-neutral-300">
                                        No hay registros con los filtros actuales.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- CARDS (móvil + tablet + laptop mediana) -->
                    <div class="xl:hidden p-4 sm:p-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div v-for="row in props.logs.data" :key="row.id"
                                class="rounded-2xl border border-slate-200/70 bg-white shadow-sm
                                hover:shadow-md transition
                                dark:border-white/10 dark:bg-neutral-950/40">
                                <div class="p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="font-semibold text-slate-900 dark:text-neutral-100 truncate">
                                                {{ row.user?.name ?? 'N/A' }}
                                            </div>
                                            <div class="text-xs text-slate-500 dark:text-neutral-400">
                                                <span class="font-medium">{{ formatDateTime(row.created_at).date }}</span>
                                                <span class="text-slate-400 dark:text-neutral-500">·</span>
                                                <span>{{ formatDateTime(row.created_at).time }}</span>
                                                <span class="text-slate-400 dark:text-neutral-500">·</span>
                                            </div>
                                        </div>

                                        <span class="shrink-0 inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                                        :class="badgeForAccion(row.accion)">
                                            {{ row.accion ?? 'N/A' }}
                                        </span>
                                    </div>

                                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-700 dark:text-neutral-200">
                                        <div class="min-w-0"><span class="font-semibold">Tabla:</span> {{ row.tabla ?? 'N/A' }}</div>
                                        <div class="min-w-0"><span class="font-semibold">Registro:</span> {{ row.registro_id ?? 'N/A' }}</div>
                                        <div class="min-w-0"><span class="font-semibold">IP:</span> {{ row.ip_address ?? 'N/A' }}</div>
                                        <div class="min-w-0 truncate"><span class="font-semibold">UA:</span> {{ row.user_agent ?? 'N/A' }}</div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="button"
                                        @click="openDetail(row)"
                                        class="w-full rounded-xl px-4 py-2 text-sm font-semibold
                                        border border-slate-200 bg-slate-50 text-slate-800
                                        hover:bg-slate-100 active:scale-[0.99] transition
                                        dark:border-white/10 dark:bg-white/5 dark:text-neutral-100 dark:hover:bg-white/10">
                                        Mostrar detalle
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="props.logs.data.length === 0"
                        class="mt-3 rounded-2xl border border-slate-200/70 bg-white p-6 text-center text-slate-600
                        dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-300">
                            No hay registros con los filtros actuales.
                        </div>
                    </div>

                    <!-- Footer paginación -->
                    <div class="p-4 sm:p-5 border-t border-slate-200/70 dark:border-white/10">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="text-sm text-slate-600 dark:text-neutral-300">
                                Página
                                <span class="font-semibold text-slate-900 dark:text-neutral-100">{{ props.logs.current_page }}</span>
                                de
                                <span class="font-semibold text-slate-900 dark:text-neutral-100">{{ props.logs.last_page }}</span>
                            </div>

                            <nav class="flex flex-wrap gap-2">
                                <button v-for="(link, i) in props.logs.links"
                                :key="i" type="button" @click="goTo(link.url)"
                                :disabled="!link.url"
                                class="rounded-xl px-3 py-1.5 text-sm font-semibold border transition
                                        border-slate-200 bg-white text-slate-800 hover:bg-slate-50
                                        disabled:opacity-50 disabled:cursor-not-allowed
                                        dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 dark:hover:bg-white/5"
                                :class="link.active ? 'ring-2 ring-slate-300 dark:ring-white/10' : ''" >
                                    {{ formatLabel(link.label) }}
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
