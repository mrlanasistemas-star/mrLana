<script setup lang="ts">
    import { computed, onMounted, ref } from 'vue'
    import { Head, Link, usePage } from '@inertiajs/vue3'
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import {
        BookOpen,
        FileText,
        LifeBuoy,
        ShieldCheck,
        Workflow,
        Search,
        ChevronDown,
        Sparkles,
        CheckCircle2,
        AlertTriangle,
        ListChecks,
        ArrowUpRight,
    } from 'lucide-vue-next'

    declare const route: any

    const SUPPORT_URL = 'https://soporte.mr-lana.com/'
    const PDF_URL = '/ayuda/mr-lana-ayuda.pdf'
    const page = usePage<any>()
    const role = computed(() => {
        const u = page.props?.auth?.user ?? {}
        const raw = String(u.rol ?? u.role ?? '').trim()
        return (raw || 'COLABORADOR').toUpperCase()
    })

    const isAdmin = computed(() => role.value === 'ADMIN')
    const isContador = computed(() => role.value === 'CONTADOR')
    const isColaborador = computed(() => role.value === 'COLABORADOR')

    const reducedMotion = ref(false)
    onMounted(() => {
        reducedMotion.value = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false
    })

    /** routes seguras (como tu sidebar) */
    const safeRoute = (name: string): string | null => {
    try {
        // @ts-ignore
        return route(name) as string
    } catch {
        return null
    }
    }

    type Step = {
        id: string
        title: string
        goal?: string
        bullets: string[]
        proTips?: string[]
        pitfalls?: string[]
    }

    type QuickLink = {
        label: string
        kind: 'inertia' | 'external'
        href: string | null
    }

    type Section = {
        id: string
        title: string
        desc: string
        audience: 'admin_contador' | 'colaborador' // tu regla de negocio
        tags?: string[]
        quickLinks?: QuickLink[]
        steps: Step[]
    }

    const roleLabel = computed(() => {
        if (isAdmin.value) return 'ADMIN'
        if (isContador.value) return 'CONTADOR'
        if (isColaborador.value) return 'COLABORADOR'
        return role.value || 'COLABORADOR'
    })

    const roleBadge = computed(() => {
        // solo estilos; neutro premium
        if (isAdmin.value) return 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
        if (isContador.value) return 'bg-neutral-800 text-white dark:bg-neutral-100 dark:text-neutral-900'
        return 'bg-neutral-700 text-white dark:bg-neutral-200 dark:text-neutral-900'
    })

    const sectionsAll: Section[] = [
    {
        id: 'dashboard',
        title: 'Dashboard',
        desc: 'Lectura ejecutiva del sistema: indicadores, actividad y exportaciones para evidencias.',
        audience: 'colaborador',
        tags: ['KPIs', 'Export', 'Monitoreo'],
        quickLinks: [
        { label: 'Ir a Dashboard', kind: 'inertia', href: safeRoute('dashboard') },
        ],
        steps: [
        {
            id: 'dash-1',
            title: 'Qué es y para qué te sirve',
            goal: 'Tener visibilidad inmediata de lo que está pasando sin abrir 10 pantallas.',
            bullets: [
            'Revisa KPIs clave del periodo (montos, conteos, tendencias).',
            'Valida actividad diaria: si hoy “no se movió nada”, aquí lo detectas primero.',
            'Usa exportaciones como evidencia: PDF para compartir, Excel para análisis.',
            ],
            proTips: [
            'Antes de exportar, ajusta filtros/periodo para que la evidencia salga limpia.',
            'Si ves valores “raros”, revisa primero fechas y estatus (no te vayas directo al pánico).',
            ],
            pitfalls: [
            'Exportar sin filtrar y después “arreglar” a mano: eso mata trazabilidad.',
            ],
        },
        {
            id: 'dash-2',
            title: 'Exportaciones (PDF / Excel)',
            goal: 'Generar evidencia formal y reutilizable para auditoría interna o seguimiento.',
            bullets: [
            'PDF: entrega rápida, lectura ejecutiva, evidencia “congelada”.',
            'Excel: revisión, pivots, conciliación y análisis detallado.',
            'Si el reporte se ve vacío: confirma que el periodo tenga datos y que tu rol tenga acceso.',
            ],
            proTips: [
            'Si compartes PDF, acompaña con folio(s) o rango de fechas para que sea verificable.',
            ],
        },
        ],
    },
    {
        id: 'proveedores',
        title: 'Proveedores',
        desc: 'Alta, edición y limpieza de datos para que compras/cotizaciones no se rompan por información incompleta.',
        audience: 'colaborador',
        tags: ['Catálogo', 'Datos', 'Operación'],
        quickLinks: [
        { label: 'Ir a Proveedores', kind: 'inertia', href: safeRoute('proveedores.index') },
        ],
        steps: [
        {
            id: 'prov-1',
            title: 'Alta de proveedor (bien hecha)',
            goal: 'Crear proveedores sin duplicados y con datos listos para operar.',
            bullets: [
            'Busca antes de crear: razón social / nombre comercial / RFC (si aplica).',
            'Captura datos mínimos consistentes: nombre, contacto, teléfono y correo.',
            'Si manejas direcciones: usa un formato estándar (calle, número, colonia, CP).',
            'Guarda y valida: que aparezca en listados y sea seleccionable en flujos.',
            ],
            proTips: [
            'Define una convención: “RAZÓN SOCIAL (COMERCIAL)” o similar para no duplicar.',
            'Si el proveedor es recurrente, completa datos desde el inicio: te ahorra retrabajo.',
            ],
            pitfalls: [
            'Crear “Proveedor 1 / Proveedor nuevo”: después nadie lo encuentra.',
            ],
        },
        {
            id: 'prov-2',
            title: 'Edición / depuración (sin romper operación)',
            goal: 'Actualizar datos sin perder consistencia histórica.',
            bullets: [
            'Si solo cambió el teléfono/correo: edita y listo.',
            'Si cambió nombre/razón social: valida que no dupliques otro registro.',
            'Evita borrar si ya existe historial vinculado: prefiere inactivar (si tu sistema lo permite).',
            ],
            proTips: [
            'Cuando ajustes datos, hazlo con intención: “para que sea localizable”.',
            ],
        },
        ],
    },
    {
        id: 'organizacion',
        title: 'Organización (Corporativos / Sucursales / Áreas)',
        desc: 'Estructura interna para asignación correcta de operación.',
        audience: 'admin_contador',
        tags: ['Estructura'],
        steps: [
        {
            id: 'org-1',
            title: 'Corporativos',
            goal: 'Centralizar estructura por entidad (control y reporteo).',
            bullets: [
            'Crea corporativos con nombres oficiales.',
            'Valida que las sucursales queden ligadas correctamente.',
            'Evita duplicados con nombres casi iguales.',
            ],
        },
        {
            id: 'org-2',
            title: 'Sucursales',
            goal: 'Operación por ubicación sin confusión.',
            bullets: [
            'Alta con nombre y dirección (si aplica).',
            'Mantén consistencia: “Centro”, “Matriz”, “Norte”… con convención.',
            'Inactiva sucursales cerradas para no aparecer en selects.',
            ],
        },
        {
            id: 'org-3',
            title: 'Áreas',
            goal: 'Asignación por departamento para orden interno.',
            bullets: [
            'Crea áreas reales (no inventadas).',
            'Evita duplicar áreas por mayúsculas/acentos.',
            'Inactiva áreas que ya no operan.',
            ],
        },
        ],
    },
    {
        id: 'empleados',
        title: 'Empleados',
        desc: 'Gestión de personas internas con control de acceso y trazabilidad.',
        audience: 'admin_contador',
        tags: ['Accesos', 'Estructura'],
        steps: [
        {
            id: 'emp-1',
            title: 'Alta y mantenimiento',
            goal: 'Tener el directorio interno correcto para asignaciones y auditoría.',
            bullets: [
            'Alta con datos completos y consistentes (nombre, área, sucursal, etc.).',
            'Evita duplicados: busca antes de crear.',
            'Si el empleado ya no está: inactiva (mejor que borrar).',
            ],
        },
        ],
    },
    {
        id: 'requisiciones',
        title: 'Requisiciones',
        desc: 'Flujo operativo para solicitudes con evidencia.',
        audience: 'admin_contador',
        tags: ['Operación', 'Evidencia'],
        quickLinks: [
        { label: 'Ir a Requisiciones', kind: 'inertia', href: safeRoute('requisiciones.index') },
        ],
        steps: [
        {
            id: 'req-1',
            title: 'Crear requisición (base sólida)',
            goal: 'Capturar correctamente para que el flujo sea aprobable/auditable.',
            bullets: [
            'Selecciona solicitante/sucursal/corporativo (según tu diseño).',
            'Selecciona concepto y proveedor.',
            'Captura montos y valida coherencia.',
            'Guarda y confirma estatus inicial.',
            ],
            pitfalls: [
            'Crear sin proveedor/concepto correcto: después no cuadra ni el reporte.',
            ],
        },
        {
            id: 'req-2',
            title: 'Seguimiento y evidencia',
            goal: 'Que no sea “solicitud fantasma”.',
            bullets: [
            'Revisa estatus y cambios.',
            'Adjunta evidencia cuando aplique (pagos/comprobantes).',
            'Exporta PDF cuando necesites respaldo formal.',
            ],
        },
        ],
    },
    {
        id: 'plantillas',
        title: 'Plantillas',
        desc: 'Estandariza requisiciones recurrentes para velocidad y menos errores.',
        audience: 'admin_contador',
        tags: ['Estandarización'],
        quickLinks: [
        { label: 'Ir a Plantillas', kind: 'inertia', href: safeRoute('plantillas.index') },
        ],
        steps: [
        {
            id: 'pla-1',
            title: 'Cuándo usar plantillas',
            goal: 'Reducir retrabajo en solicitudes repetidas.',
            bullets: [
            'Gastos recurrentes: mismo proveedor, mismo concepto, estructura similar.',
            'Evita capturar “desde cero” si el 80% es igual.',
            ],
        },
        {
            id: 'pla-2',
            title: 'Buenas prácticas',
            goal: 'Plantillas útiles, no basura.',
            bullets: [
            'Nombres claros: “Renta mensual”, “Servicio internet sucursal X”…',
            'Revisa montos antes de reutilizar (precios cambian).',
            ],
        },
        ],
    },
    {
        id: 'auditoria',
        title: 'Auditoría (Logs / System Log)',
        desc: 'Trazabilidad: quién hizo qué y cuándo.',
        audience: 'admin_contador',
        tags: ['Auditoría', 'Riesgo'],
        quickLinks: [
        { label: 'Ir a System Log', kind: 'inertia', href: safeRoute('systemlogs.index') },
        ],
        steps: [
        {
            id: 'log-1',
            title: 'Qué revisar',
            goal: 'Detectar cambios críticos y anomalías rápido.',
            bullets: [
            'Cambios de estatus.',
            'Operaciones masivas.',
            'Creaciones/ediciones en catálogos críticos.',
            ],
        },
        {
            id: 'log-2',
            title: 'Uso ejecutivo',
            goal: 'Mejorar operación, no solo “vigilar”.',
            bullets: [
            'Detecta cuellos de botella.',
            'Detecta patrones de errores (datos incompletos, duplicados).',
            ],
        },
        ],
    },
    ]

    /** Aplica tu regla exacta */
    const sections = computed<Section[]>(() => {
        if (isAdmin.value || isContador.value) {
            // Admin/Contador: ven TODO (incluyendo las 3 del colaborador)
            return sectionsAll
        }
        // Colaborador: solo 3 módulos
        return sectionsAll.filter(s => s.audience === 'colaborador')
    })

    /** Search */
    const query = ref('')
    const filtered = computed(() => {
        const q = query.value.trim().toLowerCase()
        if (!q) return sections.value
        return sections.value.filter(s => {
            const hay =
            s.title +
            ' ' +
            s.desc +
            ' ' +
            (s.tags?.join(' ') ?? '') +
            ' ' +
            s.steps
                .map(st => [st.title, st.goal ?? '', st.bullets.join(' '), (st.proTips ?? []).join(' '), (st.pitfalls ?? []).join(' ')].join(' '))
                .join(' ')
            return hay.toLowerCase().includes(q)
        })
    })

    /** TOC helpers */
    const scrollToSection = (id: string) => {
    const el = document.getElementById(`sec-${id}`)
    if (!el) return
    el.scrollIntoView({ behavior: reducedMotion.value ? 'auto' : 'smooth', block: 'start' })
    }

    /** Accordion state */
    const openSteps = ref<Record<string, boolean>>({})
    const stepKey = (sectionId: string, stepId: string) => `${sectionId}::${stepId}`

    const isOpen = (sectionId: string, stepId: string) => !!openSteps.value[stepKey(sectionId, stepId)]
    const toggleStep = (sectionId: string, stepId: string) => {
    const k = stepKey(sectionId, stepId)
    openSteps.value[k] = !openSteps.value[k]
    }

    const expandAll = () => {
    const next: Record<string, boolean> = {}
    filtered.value.forEach(sec => sec.steps.forEach(st => (next[stepKey(sec.id, st.id)] = true)))
    openSteps.value = next
    }
    const collapseAll = () => {
    openSteps.value = {}
    }

    /** Motion classes */
    const tgEnterActive = computed(() => (reducedMotion.value ? '' : 'transition duration-300 ease-out'))
    const tgLeaveActive = computed(() => (reducedMotion.value ? '' : 'transition duration-200 ease-in'))
</script>

<template>
    <Head title="Guía del sistema" />

    <AuthenticatedLayout>
        <template #header>
        <h2 class="text-xl font-semibold leading-tight text-slate-900 dark:text-zinc-100">
            Guía de uso del sistema
        </h2>
        </template>

        <div class="mx-auto w-full max-w-7xl px-4 py-6">
        <!-- HERO -->
        <div
            class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm
                dark:border-neutral-800 dark:bg-neutral-900"
        >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                <div class="grid h-10 w-10 place-items-center rounded-2xl bg-neutral-900 text-white dark:bg-white dark:text-neutral-900">
                    <BookOpen class="h-5 w-5" />
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-extrabold tracking-tight text-neutral-900 dark:text-neutral-100">
                        Guía de uso del sistema
                    </h1>
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-extrabold tracking-wide"
                        :class="roleBadge"
                    >
                        Rol: {{ roleLabel }}
                    </span>
                    </div>
                </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                <a
                    :href="SUPPORT_URL"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-2xl bg-neutral-900 px-4 py-2 text-sm font-semibold text-white
                        hover:opacity-90 active:scale-[0.99]
                        dark:bg-white dark:text-neutral-900"
                >
                    <LifeBuoy class="h-4 w-4" />
                    Soporte (tickets)
                    <ArrowUpRight class="h-4 w-4 opacity-80" />
                </a>

                <a
                    :href="PDF_URL"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200 bg-white px-4 py-2 text-sm font-semibold text-neutral-900
                        hover:bg-neutral-50 active:scale-[0.99]
                        dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
                >
                    <FileText class="h-4 w-4" />
                    Abrir PDF de ayuda
                    <ArrowUpRight class="h-4 w-4 opacity-70" />
                </a>

                <Link
                    v-if="safeRoute('dashboard')"
                    :href="route('dashboard')"
                    class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200 bg-white px-4 py-2 text-sm font-semibold text-neutral-900
                        hover:bg-neutral-50 active:scale-[0.99]
                        dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
                >
                    <Workflow class="h-4 w-4" />
                    Volver al dashboard
                </Link>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    @click="expandAll"
                    class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200 bg-white px-4 py-2 text-sm font-semibold text-neutral-900
                        hover:bg-neutral-50 active:scale-[0.99]
                        dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
                >
                    <ListChecks class="h-4 w-4" />
                    Expandir todo
                </button>

                <button
                    type="button"
                    @click="collapseAll"
                    class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200 bg-white px-4 py-2 text-sm font-semibold text-neutral-900
                        hover:bg-neutral-50 active:scale-[0.99]
                        dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
                >
                    <ChevronDown class="h-4 w-4 rotate-180" />
                    Colapsar todo
                </button>
                </div>
            </div>

            <div class="w-full lg:max-w-sm">
                <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-500" />
                <input
                    v-model="query"
                    type="text"
                    placeholder="Buscar en la guía…"
                    class="w-full rounded-2xl border border-neutral-200 bg-white py-2 pl-10 pr-3 text-sm text-neutral-900 outline-none ring-0
                        focus:border-neutral-300
                        dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100 dark:focus:border-neutral-700"
                />
                </div>

                <div
                class="mt-3 rounded-2xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-700
                        dark:border-neutral-800 dark:bg-neutral-950/30 dark:text-neutral-300"
                >
                <div class="flex items-center gap-2 font-semibold text-neutral-900 dark:text-neutral-100">
                    <ShieldCheck class="h-4 w-4" />
                    Recomendación operativa
                </div>
                <p class="mt-1">
                    Si algo no cuadra (monto, estatus, pdf, etc.), levanta ticket con evidencia (captura de pantalla).
                </p>
                <div class="mt-3 flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-400">
                    <Sparkles class="h-4 w-4" />
                    Resultados: <span class="font-bold text-neutral-900 dark:text-neutral-100">{{ filtered.length }}</span>
                </div>
                </div>
            </div>
            </div>
        </div>

        <!-- LAYOUT: TOC + CONTENIDO -->
        <div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-12">
            <!-- TOC -->
            <aside class="hidden xl:block xl:col-span-3">
            <div class="sticky top-20 rounded-3xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="text-xs font-extrabold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                Módulos
                </div>

                <div class="mt-3 space-y-1">
                <button
                    v-for="s in filtered"
                    :key="s.id"
                    type="button"
                    @click="scrollToSection(s.id)"
                    class="w-full rounded-2xl px-3 py-2 text-left text-sm font-semibold
                        transition hover:bg-neutral-50 active:scale-[0.99]
                        text-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
                >
                    <div class="flex items-center justify-between gap-2">
                    <span class="truncate">{{ s.title }}</span>
                    <span class="text-xs font-bold text-neutral-500 dark:text-neutral-400">{{ s.steps.length }}</span>
                    </div>
                    <div class="mt-0.5 truncate text-xs font-medium text-neutral-500 dark:text-neutral-400">
                    {{ s.desc }}
                    </div>
                </button>
                </div>
            </div>
            </aside>

            <!-- CONTENIDO -->
            <main class="xl:col-span-9">
            <TransitionGroup
                tag="div"
                class="grid grid-cols-1 gap-4"
                :enter-active-class="tgEnterActive"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                :leave-active-class="tgLeaveActive"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <section
                v-for="s in filtered"
                :key="s.id"
                :id="`sec-${s.id}`"
                class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm
                        dark:border-neutral-800 dark:bg-neutral-900"
                >
                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                    <h2 class="text-lg font-extrabold text-neutral-900 dark:text-neutral-100">
                        {{ s.title }}
                    </h2>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ s.desc }}
                    </p>

                    <div v-if="s.tags?.length" class="mt-3 flex flex-wrap gap-2">
                        <span
                        v-for="t in s.tags"
                        :key="t"
                        class="inline-flex items-center rounded-full border border-neutral-200 bg-neutral-50 px-2.5 py-1 text-xs font-semibold text-neutral-700
                                dark:border-neutral-800 dark:bg-neutral-950/30 dark:text-neutral-300"
                        >
                        {{ t }}
                        </span>
                    </div>
                    </div>

                    <div v-if="s.quickLinks?.length" class="flex flex-wrap gap-2">
                    <template v-for="(ql, idx) in s.quickLinks" :key="idx">
                        <Link
                        v-if="ql.kind === 'inertia' && ql.href"
                        :href="ql.href"
                        class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200 bg-white px-4 py-2 text-sm font-semibold text-neutral-900
                                hover:bg-neutral-50 active:scale-[0.99]
                                dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
                        >
                        Ir
                        <ArrowUpRight class="h-4 w-4 opacity-70" />
                        </Link>

                        <a
                        v-else-if="ql.kind === 'external' && ql.href"
                        :href="ql.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200 bg-white px-4 py-2 text-sm font-semibold text-neutral-900
                                hover:bg-neutral-50 active:scale-[0.99]
                                dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
                        >
                        Abrir
                        <ArrowUpRight class="h-4 w-4 opacity-70" />
                        </a>
                    </template>
                    </div>
                </div>

                <!-- Steps (accordion) -->
                <div class="mt-5 space-y-3">
                    <div
                    v-for="st in s.steps"
                    :key="st.id"
                    class="rounded-2xl border border-neutral-200 bg-neutral-50
                            dark:border-neutral-800 dark:bg-neutral-950/30"
                    >
                    <button
                        type="button"
                        class="flex w-full items-start justify-between gap-4 rounded-2xl px-4 py-3 text-left
                            transition hover:bg-neutral-100/60 active:scale-[0.999]
                            dark:hover:bg-neutral-900/40"
                        @click="toggleStep(s.id, st.id)"
                    >
                        <div class="min-w-0">
                        <div class="text-sm font-extrabold text-neutral-900 dark:text-neutral-100">
                            {{ st.title }}
                        </div>
                        <div v-if="st.goal" class="mt-1 text-xs font-medium text-neutral-600 dark:text-neutral-400">
                            Objetivo: <span class="font-semibold text-neutral-900 dark:text-neutral-200">{{ st.goal }}</span>
                        </div>
                        </div>

                        <ChevronDown
                        class="mt-0.5 h-5 w-5 shrink-0 text-neutral-600 transition-transform duration-200 dark:text-neutral-300"
                        :class="isOpen(s.id, st.id) ? 'rotate-180' : 'rotate-0'"
                        />
                    </button>

                    <Transition
                        :enter-active-class="reducedMotion ? '' : 'transition duration-200 ease-out'"
                        enter-from-class="opacity-0 -translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        :leave-active-class="reducedMotion ? '' : 'transition duration-150 ease-in'"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-1"
                    >
                        <div v-show="isOpen(s.id, st.id)" class="px-4 pb-4">
                        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                            <!-- bullets -->
                            <div class="rounded-2xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                            <div class="flex items-center gap-2 text-sm font-extrabold text-neutral-900 dark:text-neutral-100">
                                <CheckCircle2 class="h-4 w-4" />
                                Pasos
                            </div>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                                <li v-for="(b, i) in st.bullets" :key="i">{{ b }}</li>
                            </ul>
                            </div>

                            <!-- tips / pitfalls -->
                            <div class="space-y-3">
                            <div
                                v-if="st.proTips?.length"
                                class="rounded-2xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
                            >
                                <div class="flex items-center gap-2 text-sm font-extrabold text-neutral-900 dark:text-neutral-100">
                                <Sparkles class="h-4 w-4" />
                                Pro tips
                                </div>
                                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                                <li v-for="(b, i) in st.proTips" :key="i">{{ b }}</li>
                                </ul>
                            </div>

                            <div
                                v-if="st.pitfalls?.length"
                                class="rounded-2xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
                            >
                                <div class="flex items-center gap-2 text-sm font-extrabold text-neutral-900 dark:text-neutral-100">
                                <AlertTriangle class="h-4 w-4" />
                                Errores comunes
                                </div>
                                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                                <li v-for="(b, i) in st.pitfalls" :key="i">{{ b }}</li>
                                </ul>
                            </div>
                            </div>
                        </div>
                        </div>
                    </Transition>
                    </div>
                </div>
                </section>
            </TransitionGroup>
            </main>
        </div>
        </div>
    </AuthenticatedLayout>
</template>
