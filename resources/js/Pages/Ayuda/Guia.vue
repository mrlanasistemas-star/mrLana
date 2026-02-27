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
            desc: 'Panel de consulta para monitorear indicadores, actividad y generar exportaciones como evidencia operativa.',
            audience: 'colaborador',
            quickLinks: [{ label: 'Ir a Dashboard', kind: 'inertia', href: safeRoute('dashboard') }],
            steps: [
            {
                id: 'dash-1',
                title: 'Propósito del Dashboard',
                goal: 'Comprender qué información ofrece el panel y cómo utilizarla para tomar decisiones operativas.',
                bullets: [
                'Identifique los indicadores principales (montos, conteos, tendencias) correspondientes al periodo seleccionado.',
                'Revise la actividad diaria para confirmar si existe movimiento en el sistema y detectar anomalías de forma temprana.',
                'Utilice el panel como punto de partida antes de consultar módulos específicos, para reducir tiempos de búsqueda.',
                ],
                proTips: [
                'Si observa valores inesperados, valide primero el periodo, los filtros y el estatus de los registros antes de solicitar soporte.',
                ],
                pitfalls: [
                'Interpretar datos sin validar filtros o rango de fechas puede llevar a conclusiones incorrectas.',
                ],
            },
            {
                id: 'dash-2',
                title: 'Filtros y periodos',
                goal: 'Asegurar que la información mostrada sea consistente con lo que se desea consultar o exportar.',
                bullets: [
                'Seleccione el periodo de consulta antes de revisar indicadores o descargar reportes.',
                'Si existen filtros adicionales (por sucursal, corporativo u otro), aplíquelos de forma intencional y verifique el resultado.',
                'Después de cambiar filtros, confirme que los indicadores se actualicen y correspondan al nuevo contexto.',
                ],
                proTips: [
                'Para auditoría o seguimiento, utilice periodos cerrados (por ejemplo, semanas o meses completos) y documente el rango consultado.',
                ],
                pitfalls: [
                'Exportar con filtros incompletos o erróneos genera evidencia poco confiable.',
                ],
            },
            {
                id: 'dash-3',
                title: 'Exportaciones (PDF y Excel)',
                goal: 'Generar evidencia formal para compartir y, cuando aplique, un archivo para análisis detallado.',
                bullets: [
                'Use PDF cuando requiera un documento estable para envío o evidencia (lectura ejecutiva).',
                'Use Excel cuando requiera análisis (tablas dinámicas, conciliaciones, validación de totales).',
                'Si un reporte se muestra vacío, confirme que el periodo tenga datos y que su rol tenga acceso a ese reporte.',
                ],
                proTips: [
                'Al compartir un PDF, incluya también el rango de fechas o folios relevantes para facilitar la verificación.',
                ],
                pitfalls: [
                'Modificar manualmente un export para “corregir” datos rompe la trazabilidad y la confiabilidad del reporte.',
                ],
            },
            {
                id: 'dash-4',
                title: 'Acciones recomendadas ante inconsistencias',
                goal: 'Resolver problemas comunes sin interrumpir la operación.',
                bullets: [
                'Verifique que el periodo seleccionado sea el correcto.',
                'Revise si existen registros en estatus que no se consideran en el cálculo (por ejemplo, borradores o cancelados).',
                'Si persiste la inconsistencia, genere evidencia (captura + rango/folio) y levante un ticket a soporte.',
                ],
                proTips: [
                'Para soporte, incluya siempre: fecha/hora, módulo, acción realizada y evidencia visual.',
                ],
            },
            ],
        },

        {
            id: 'proveedores',
            title: 'Proveedores',
            desc: 'Gestión del catálogo de proveedores personales para el flujo de compras.',
            audience: 'colaborador',
            quickLinks: [{ label: 'Ir a Proveedores', kind: 'inertia', href: safeRoute('proveedores.index') }],
            steps: [
            {
                id: 'prov-1',
                title: 'Búsqueda previa (evitar duplicados)',
                goal: 'Confirmar si el proveedor ya existe antes de crear un nuevo registro.',
                bullets: [
                'Busque por nombre comercial y, si aplica, por razón social.',
                'Si maneja RFC u otro identificador, utilícelo como criterio principal de búsqueda.',
                'Revise coincidencias similares para evitar crear variantes del mismo proveedor.',
                ],
                proTips: [
                'Defina un criterio de nombre consistente (por ejemplo, “Razón social (Nombre comercial)”) para mejorar la localización.',
                ],
                pitfalls: [
                'Crear duplicados genera confusión en reportes y dificulta el seguimiento operativo.',
                ],
            },
            {
                id: 'prov-2',
                title: 'Alta de proveedor',
                goal: 'Registrar proveedores con información mínima suficiente para operar sin retrabajo.',
                bullets: [
                'Capture los datos básicos: nombre/razón social, contacto, teléfono y correo.',
                'Si se utiliza dirección, complete la información con un formato estándar (calle, número, colonia, CP).',
                'Guarde y confirme que el proveedor aparece en el listado y es seleccionable en los formularios del sistema.',
                ],
                proTips: [
                'Complete la información desde el inicio cuando sea un proveedor recurrente; reduce incidencias posteriores.',
                ],
                pitfalls: [
                'Registrar proveedores con datos incompletos suele provocar fallas o correcciones urgentes en momentos críticos.',
                ],
            },
            {
                id: 'prov-3',
                title: 'Edición y actualización',
                goal: 'Mantener el catálogo actualizado sin perder consistencia histórica.',
                bullets: [
                'Actualice teléfono/correo cuando cambien, para mantener canales de contacto vigentes.',
                'Si cambia el nombre, valide que no exista otro registro equivalente.',
                'Cuando aplique, prefiera inactivar en lugar de eliminar registros con historial.',
                ],
                proTips: [
                'Documente internamente cambios relevantes (por ejemplo, cambio de razón social) para facilitar auditoría.',
                ],
                pitfalls: [
                'Cambiar nombres sin validar duplicados dificulta reporteo y trazabilidad.',
                ],
            },
            {
                id: 'prov-4',
                title: 'Buenas prácticas de calidad de datos',
                goal: 'Estandarizar el catálogo para búsquedas rápidas y reportes consistentes.',
                bullets: [
                'Evite abreviaturas inconsistentes y nombres genéricos.',
                'Mantenga correos en formato válido y teléfonos con estructura uniforme.',
                'Revise periódicamente el catálogo para detectar duplicados o registros incompletos.',
                ],
            },
            ],
        },

        {
            id: 'organizacion',
            title: 'Organización (Corporativos / Sucursales / Áreas)',
            desc: 'Estructura interna para asignación correcta de registros, segmentación operativa y consistencia de reportes.',
            audience: 'admin_contador',
            steps: [
            {
                id: 'org-1',
                title: 'Corporativos',
                goal: 'Centralizar la estructura por entidad para control, segmentación y reporteo.',
                bullets: [
                'Registre corporativos con nombres oficiales y consistentes.',
                'Valide que la relación con sucursales se mantenga correcta.',
                'Evite duplicados por variaciones de escritura (acentos, mayúsculas, abreviaturas).',
                ],
                pitfalls: [
                'Duplicar corporativos impacta selectores, reportes y asignación de registros.',
                ],
            },
            {
                id: 'org-2',
                title: 'Sucursales',
                goal: 'Organizar la operación por ubicación para mejorar trazabilidad y control.',
                bullets: [
                'Registre sucursales con nombre claro y, si aplica, dirección.',
                'Mantenga una convención de nombres (por ejemplo: “Matriz”, “Centro”, “Norte”).',
                'Cuando una sucursal deje de operar, inactívela para evitar uso accidental.',
                ],
                proTips: [
                'La inactivación reduce errores sin perder historial.',
                ],
            },
            {
                id: 'org-3',
                title: 'Áreas',
                goal: 'Clasificar registros por departamento para control interno y reportes consistentes.',
                bullets: [
                'Registre áreas reales y alineadas a la estructura organizacional.',
                'Evite duplicados por variaciones mínimas de nombre.',
                'Inactive áreas que ya no se utilicen para mantener listados limpios.',
                ],
            },
            {
                id: 'org-4',
                title: 'Validación de relaciones y consistencia',
                goal: 'Garantizar que la estructura funcione correctamente en formularios, filtros y reportes.',
                bullets: [
                'Confirme que cada sucursal pertenezca al corporativo correcto.',
                'Revise que las áreas disponibles correspondan a la operación actual.',
                'Valide que los selectores del sistema no muestren elementos inactivos (si esa es la regla definida).',
                ],
                proTips: [
                'Realice revisiones periódicas (mensuales o trimestrales) para mantener la estructura vigente.',
                ],
            },
            ],
        },

        {
            id: 'empleados',
            title: 'Empleados',
            desc: 'Directorio interno para asignaciones, segmentación y trazabilidad (con enfoque en control de datos).',
            audience: 'admin_contador',
            steps: [
            {
                id: 'emp-1',
                title: 'Alta y mantenimiento',
                goal: 'Mantener un directorio confiable para asignaciones y auditoría.',
                bullets: [
                'Registre datos completos y consistentes (nombre, área, sucursal y datos de contacto si aplica).',
                'Verifique duplicados antes de crear un nuevo empleado.',
                'Cuando un empleado deje de operar, inactívelo para mantener trazabilidad sin afectar historial.',
                ],
                proTips: [
                'Utilice criterios estandarizados para nombres y apellidos para evitar duplicidad y errores de búsqueda.',
                ],
                pitfalls: [
                'Eliminar registros con historial puede afectar integridad y trazabilidad.',
                ],
            },
            {
                id: 'emp-2',
                title: 'Asignación a estructura (área y sucursal)',
                goal: 'Asegurar que los registros se clasifiquen correctamente en filtros y reportes.',
                bullets: [
                'Asigne el empleado al área correcta para segmentación y control.',
                'Verifique la sucursal correspondiente para reportes por ubicación.',
                'Revise que la estructura organizacional esté actualizada antes de asignar masivamente.',
                ],
            },
            {
                id: 'emp-3',
                title: 'Revisión periódica del catálogo',
                goal: 'Evitar registros obsoletos y mantener consistencia operativa.',
                bullets: [
                'Revise empleados inactivos y confirme que no aparezcan en flujos donde no deban participar.',
                'Corrija datos incompletos o inconsistentes.',
                'Asegure que los empleados activos tengan estructura asignada.',
                ],
            },
            ],
        },

        {
            id: 'requisiciones',
            title: 'Requisiciones',
            desc: 'Flujo operativo para solicitudes con control de información, estatus y evidencia documental.',
            audience: 'admin_contador',
            quickLinks: [{ label: 'Ir a Requisiciones', kind: 'inertia', href: safeRoute('requisiciones.index') }],
            steps: [
            {
                id: 'req-1',
                title: 'Creación de requisición (captura correcta)',
                goal: 'Registrar una requisición con información completa para evitar rechazos y retrabajo.',
                bullets: [
                'Seleccione la información organizacional requerida (corporativo/sucursal/área) según el diseño del sistema.',
                'Seleccione proveedor y concepto conforme a la operación.',
                'Capture montos y valide coherencia (subtotal, total y condiciones aplicables).',
                'Guarde y confirme que el registro queda en el estatus inicial esperado.',
                ],
                proTips: [
                'Si el sistema solicita notas u observaciones, utilícelas para describir el propósito de la requisición de forma clara.',
                ],
                pitfalls: [
                'Registrar sin proveedor o con concepto incorrecto provoca inconsistencias en reportes y auditoría.',
                ],
            },
            {
                id: 'req-2',
                title: 'Validación de datos antes de avanzar',
                goal: 'Asegurar que la requisición sea verificable y consistente.',
                bullets: [
                'Confirme que el proveedor sea el correcto y que sus datos estén completos.',
                'Revise que el concepto corresponda al gasto o solicitud.',
                'Verifique que el monto no contenga errores de captura (por ejemplo, ceros adicionales).',
                ],
            },
            {
                id: 'req-3',
                title: 'Seguimiento por estatus',
                goal: 'Controlar la vida del registro para evitar solicitudes sin seguimiento.',
                bullets: [
                'Revise el estatus actual y la fecha de registro.',
                'Identifique si requiere acciones adicionales (documentos, validaciones, aprobaciones, etc.).',
                'Mantenga el seguimiento hasta el cierre operativo conforme a las reglas del sistema.',
                ],
                proTips: [
                'El estatus es el mecanismo de control. No lo omita ni lo utilice de forma informal.',
                ],
            },
            {
                id: 'req-4',
                title: 'Evidencia y exportación',
                goal: 'Contar con soporte documental para auditoría interna o validaciones.',
                bullets: [
                'Adjunte evidencia cuando aplique, conforme a los permisos del rol.',
                'Genere exportaciones (PDF/Excel) cuando se requiera documentación del proceso.',
                'Conserve el folio o identificador para trazabilidad.',
                ],
                pitfalls: [
                'Operar sin evidencia incrementa tiempos de verificación y eleva el riesgo de discrepancias.',
                ],
            },
            ],
        },

        {
            id: 'plantillas',
            title: 'Plantillas',
            desc: 'Estandarización de solicitudes recurrentes para reducir errores y acelerar la operación.',
            audience: 'admin_contador',
            quickLinks: [{ label: 'Ir a Plantillas', kind: 'inertia', href: safeRoute('plantillas.index') }],
            steps: [
            {
                id: 'pla-1',
                title: 'Cuándo utilizar plantillas',
                goal: 'Aprovechar plantillas cuando la solicitud se repite con estructura similar.',
                bullets: [
                'Utilice plantillas para gastos o solicitudes recurrentes.',
                'Evite capturar desde cero cuando la mayor parte de la información es constante.',
                'Defina plantillas por tipo de operación para mantener orden.',
                ],
            },
            {
                id: 'pla-2',
                title: 'Creación de una plantilla útil',
                goal: 'Registrar una plantilla clara, reutilizable y fácil de identificar.',
                bullets: [
                'Asigne un nombre descriptivo y específico.',
                'Incluya proveedor y concepto correctos (si aplica).',
                'Incluya valores base que sirvan como referencia, considerando que pueden cambiar.',
                ],
                proTips: [
                'Nombres recomendados: “Renta mensual”, “Servicio de internet - Sucursal X”, “Mantenimiento trimestral”.',
                ],
                pitfalls: [
                'Plantillas con nombres genéricos reducen la adopción y generan confusión.',
                ],
            },
            {
                id: 'pla-3',
                title: 'Uso operativo de plantillas',
                goal: 'Aplicar la plantilla y validar antes de guardar la solicitud generada.',
                bullets: [
                'Genere la solicitud a partir de la plantilla.',
                'Revise montos y condiciones antes de confirmar.',
                'Ajuste información puntual cuando corresponda (por ejemplo, fechas o cantidades).',
                ],
            },
            {
                id: 'pla-4',
                title: 'Mantenimiento y revisión',
                goal: 'Evitar que las plantillas se vuelvan obsoletas.',
                bullets: [
                'Revise plantillas periódicamente y actualice información base cuando cambien condiciones.',
                'Inhabilite o depure plantillas que ya no se utilicen (si el sistema lo permite).',
                'Mantenga un catálogo reducido y relevante para facilitar la selección.',
                ],
            },
            ],
        },

        {
            id: 'auditoria',
            title: 'Auditoría (Logs / System Log)',
            desc: 'Registro de acciones para trazabilidad: permite verificar cambios, identificar responsables y apoyar auditorías internas.',
            audience: 'admin_contador',
            quickLinks: [{ label: 'Ir a System Log', kind: 'inertia', href: safeRoute('systemlogs.index') }],
            steps: [
            {
                id: 'log-1',
                title: 'Qué revisar en los logs',
                goal: 'Identificar eventos relevantes para control y auditoría.',
                bullets: [
                'Cambios de estatus en registros operativos.',
                'Creación, edición o inactivación de catálogos críticos.',
                'Operaciones masivas o acciones con impacto amplio.',
                ],
                proTips: [
                'Al detectar un evento relevante, registre el identificador/folio y el usuario asociado para seguimiento.',
                ],
            },
            {
                id: 'log-2',
                title: 'Búsqueda y filtrado',
                goal: 'Reducir el tiempo de análisis y ubicar la información con precisión.',
                bullets: [
                'Filtre por rango de fechas para acotar el análisis.',
                'Filtre por módulo o tipo de acción cuando sea posible.',
                'Busque por folio o identificador del registro afectado si está disponible.',
                ],
                pitfalls: [
                'Analizar sin filtros incrementa el tiempo y aumenta la probabilidad de omitir información relevante.',
                ],
            },
            {
                id: 'log-3',
                title: 'Uso operativo y correctivo',
                goal: 'Utilizar los logs para mejorar el proceso, no únicamente para inspección.',
                bullets: [
                'Detecte patrones de errores recurrentes (por ejemplo, datos incompletos o duplicados).',
                'Identifique cuellos de botella por estatus o acciones repetitivas.',
                'Si se requiere soporte, adjunte evidencia y referencias exactas del evento.',
                ],
                proTips: [
                'Para soporte, indique: fecha/hora, usuario, acción, módulo y folio asociado.',
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

        <div class="mx-auto w-full max-w-7xl px-4 py-6 xl:max-w-screen-2xl 2xl:max-w-[1680px] xl:px-6 2xl:px-10">
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
                                Consejos
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
