<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { usePermissions, useVisibility } from '@/Composables/usePermissions'
import { useTour } from '@/Composables/useTour'
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    BookOpen,
    CheckCircle2,
    ChevronDown,
    CircleAlert,
    Download,
    Filter,
    KeyRound,
    LayoutGrid,
    Lightbulb,
    PlayCircle,
    Route,
    Search,
    Target,
    Users,
    X,
} from 'lucide-vue-next'
import { GRUPOS, MODULOS, type GuiaModulo, type Item, type Tono } from './guiaContenido'

const props = defineProps<{
    /** Módulo del catálogo → { permiso: etiqueta humana }. */
    catalogo: Record<string, Record<string, string>>
}>()

const PDF_URL = '/ayuda/mr-lana-ayuda.pdf'

const { can, roles } = usePermissions()
const visible = useVisibility()
const tour = useTour()

/* ---------------------------------------------------------- Visibilidad */
/** Visible si cumple permisos (anyOf) o alcance de módulo (views); sin reglas, para todos. */
const visibleTo = (rule?: { anyOf?: string[]; views?: string[] }) => !rule || visible(rule)
const itemText = (i: Item) => (typeof i === 'string' ? i : i.texto)
const visibleItems = (list?: Item[]) => (list ?? []).filter((i) => typeof i === 'string' || visibleTo(i))

const modulos = computed(() => MODULOS.filter((m) => visibleTo(m)))

/* ---------------------------------------------------------- Recorridos */
const tourDisponible = (m: GuiaModulo) => !!m.tour && (m.tour === 'general' || tour.available.value.some((t) => t.id === m.tour))
const visto = (m: GuiaModulo) => !!m.tour && tour.seen.has(m.tour)
function iniciarRecorrido(m: GuiaModulo) {
    if (m.tour === 'general') tour.startFull()
    else if (m.tour) tour.start(m.tour)
}
const vistos = computed(() => modulos.value.filter(visto).length)
const grupos = computed(() =>
    GRUPOS.map((g) => ({ titulo: g, modulos: modulos.value.filter((m) => m.grupo === g) })).filter((g) => g.modulos.length > 0),
)
const ordenados = computed(() => grupos.value.flatMap((g) => g.modulos))

const safeRoute = (name?: string): string | null => {
    if (!name) return null
    try {
        return route(name)
    } catch {
        return null
    }
}

/** Permisos del usuario dentro de los módulos del catálogo, en lenguaje humano. */
const tuAcceso = (m: GuiaModulo) =>
    (m.catalogo ?? []).flatMap((key) => Object.entries(props.catalogo[key] ?? {}).filter(([p]) => can(p)).map(([, label]) => label))

const rolLabel = computed(() => roles.value.join(', ') || 'Sin rol')

/* ---------------------------------------------------------- Selección */
const INICIO = 'inicio'
const seleccion = ref<string>(INICIO)
const actual = computed(() => ordenados.value.find((m) => m.id === seleccion.value) ?? null)
const indice = computed(() => ordenados.value.findIndex((m) => m.id === seleccion.value))
const anterior = computed(() => (indice.value > 0 ? ordenados.value[indice.value - 1] : null))
const siguiente = computed(() => (indice.value >= 0 && indice.value < ordenados.value.length - 1 ? ordenados.value[indice.value + 1] : null))

const indiceAbierto = ref(false)
const tituloDetalle = ref<HTMLElement | null>(null)

function seleccionar(id: string, enfocar = true) {
    seleccion.value = id
    busqueda.value = ''
    indiceAbierto.value = false
    try {
        history.replaceState(history.state, '', id === INICIO ? location.pathname : `#${id}`)
    } catch {
        /* sin historial: no es crítico */
    }
    if (enfocar) {
        nextTick(() => {
            tituloDetalle.value?.focus({ preventScroll: true })
            tituloDetalle.value?.scrollIntoView({ behavior: reducedMotion.value ? 'auto' : 'smooth', block: 'start' })
        })
    }
}

/* ---------------------------------------------------------- Búsqueda */
const busqueda = ref('')
const normaliza = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

const textoBuscable = (m: GuiaModulo) =>
    normaliza(
        [
            m.nombre, m.resumen, m.paraQue, m.quien, m.claves ?? '',
            ...m.pasos.filter((p) => visibleTo(p)).flatMap((p) => [p.titulo, p.texto]),
            ...[m.acciones, m.filtros, m.errores, m.advertencias, m.consejos].flatMap((l) => visibleItems(l).map(itemText)),
            ...(m.estados ?? []).flatMap((e) => [e.nombre, e.texto]),
        ].join(' '),
    )

const resultados = computed(() => {
    const q = normaliza(busqueda.value.trim())
    if (!q) return []
    const terminos = q.split(/\s+/)
    return modulos.value
        .map((m) => {
            const texto = textoBuscable(m)
            if (!terminos.every((t) => texto.includes(t))) return null
            const enNombre = terminos.every((t) => normaliza(m.nombre + ' ' + m.resumen).includes(t))
            return { modulo: m, peso: enNombre ? 0 : 1, fragmento: fragmento(m, terminos) }
        })
        .filter((r): r is NonNullable<typeof r> => r !== null)
        .sort((a, b) => a.peso - b.peso)
})

/** Primera frase del módulo que contiene el término buscado. */
function fragmento(m: GuiaModulo, terminos: string[]): string {
    const frases = [
        m.paraQue,
        ...m.pasos.filter((p) => visibleTo(p)).map((p) => `${p.titulo}: ${p.texto}`),
        ...[m.acciones, m.filtros, m.errores, m.advertencias, m.consejos].flatMap((l) => visibleItems(l).map(itemText)),
        ...(m.estados ?? []).map((e) => `${e.nombre}: ${e.texto}`),
    ]
    return frases.find((f) => terminos.some((t) => normaliza(f).includes(t))) ?? m.resumen
}

/* ---------------------------------------------------------- Teclado */
const navRefs = ref<HTMLElement[]>([])
function onNavKey(e: KeyboardEvent) {
    const items = navRefs.value.filter(Boolean)
    const pos = items.indexOf(document.activeElement as HTMLElement)
    if (pos < 0) return
    const destino = { ArrowDown: pos + 1, ArrowUp: pos - 1, Home: 0, End: items.length - 1 }[e.key]
    if (destino === undefined) return
    e.preventDefault()
    items[Math.max(0, Math.min(items.length - 1, destino))]?.focus()
}

/* ---------------------------------------------------------- Movimiento */
const reducedMotion = ref(false)
/** Abre el tema indicado en la URL (#pagos); también al cambiar el hash con enlaces. */
function desdeHash() {
    const hash = decodeURIComponent(location.hash.replace('#', ''))
    if (hash && ordenados.value.some((m) => m.id === hash)) seleccionar(hash, false)
}
onMounted(() => {
    reducedMotion.value = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false
    desdeHash()
    window.addEventListener('hashchange', desdeHash)
})
onBeforeUnmount(() => window.removeEventListener('hashchange', desdeHash))
watch(busqueda, (v) => {
    if (v) indiceAbierto.value = false
})

/* ---------------------------------------------------------- Estilos */
const TONO: Record<Tono, string> = {
    gris: 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-white/10',
    azul: 'bg-sky-50 text-sky-800 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/20',
    ambar: 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20',
    verde: 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20',
    rojo: 'bg-rose-50 text-rose-800 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/20',
    violeta: 'bg-violet-50 text-violet-800 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-400/20',
}
const TONO_GRUPO: Record<string, string> = {
    General: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
    Operación: 'bg-brand-accent/10 text-brand-accent',
    Organización: 'bg-violet-500/10 text-violet-700 dark:text-violet-300',
    'Personas y accesos': 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    Catálogos: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    Sistema: 'bg-slate-500/10 text-slate-700 dark:text-zinc-300',
    'Tu cuenta y la app': 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
}
const tonoGrupo = (g: string) => TONO_GRUPO[g] ?? TONO_GRUPO.General
const anim = 'motion-safe:transition motion-safe:duration-200 motion-safe:ease-out'
</script>

<template>
    <Head title="Ayuda" />

    <AuthenticatedLayout>
        <template #header>Ayuda</template>

        <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <!-- Encabezado -->
            <section class="ui-card relative overflow-hidden p-5 sm:p-7" data-tour="ayuda-encabezado">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-brand-accent/10 via-transparent to-transparent" aria-hidden="true" />
                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-accent/10 text-brand-accent">
                            <BookOpen class="h-6 w-6" aria-hidden="true" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl dark:text-zinc-100">Centro de ayuda</h2>
                            <p class="mt-1 max-w-2xl text-pretty text-sm text-slate-600 dark:text-zinc-400">
                                Aprende a usar cada módulo paso a paso. Solo ves lo que tu acceso permite:
                                <strong class="font-semibold text-slate-800 dark:text-zinc-200">{{ modulos.length }} temas</strong>
                                para tu rol <strong class="font-semibold text-slate-800 dark:text-zinc-200">{{ rolLabel }}</strong>.
                                <span v-if="vistos" class="whitespace-nowrap">Has visto {{ vistos }} recorrido(s).</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <button type="button" class="ui-btn-primary justify-center transition-transform hover:-translate-y-px motion-reduce:transform-none" @click="tour.startFull()">
                            <Route class="h-4 w-4" aria-hidden="true" /> Iniciar recorrido completo
                        </button>
                        <a :href="PDF_URL" download class="ui-btn-secondary justify-center">
                            <Download class="h-4 w-4" aria-hidden="true" /> Descargar en PDF
                        </a>
                    </div>
                </div>

                <div class="relative mt-5" data-tour="ayuda-buscador">
                    <label for="guia-buscar" class="sr-only">Buscar en la guía</label>
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input
                        id="guia-buscar"
                        v-model="busqueda"
                        type="search"
                        class="ui-input pl-9 pr-10"
                        placeholder="Buscar: «proveedor», «comprobante», «PDF», «instalar»…"
                        autocomplete="off"
                    />
                    <button
                        v-if="busqueda"
                        type="button"
                        class="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 dark:hover:bg-white/5"
                        aria-label="Limpiar búsqueda"
                        @click="busqueda = ''"
                    >
                        <X class="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>
            </section>

            <div class="grid min-w-0 gap-5 lg:grid-cols-[17rem_minmax(0,1fr)]">
                <!-- Índice -->
                <aside class="min-w-0 lg:sticky lg:top-20 lg:self-start">
                    <button
                        type="button"
                        class="ui-card flex w-full items-center gap-3 p-3 text-left lg:hidden"
                        :aria-expanded="indiceAbierto"
                        aria-controls="guia-indice"
                        @click="indiceAbierto = !indiceAbierto"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" :class="actual ? tonoGrupo(actual.grupo) : 'bg-brand-accent/10 text-brand-accent'">
                            <component :is="actual?.icono ?? LayoutGrid" class="h-4 w-4" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">Índice</span>
                            <span class="block truncate text-sm font-bold text-slate-900 dark:text-zinc-100">{{ actual?.nombre ?? 'Todos los temas' }}</span>
                        </span>
                        <ChevronDown class="h-4 w-4 shrink-0 text-slate-500" :class="[anim, indiceAbierto ? 'rotate-180' : '']" aria-hidden="true" />
                    </button>

                    <nav
                        id="guia-indice"
                        aria-label="Índice de la guía"
                        class="ui-card mt-2 max-h-[70vh] overflow-y-auto p-2 lg:mt-0 lg:block lg:max-h-[calc(100vh-7rem)]"
                        :class="indiceAbierto ? 'block' : 'hidden'"
                        @keydown="onNavKey"
                    >
                        <button
                            :ref="(el) => (navRefs[0] = el as HTMLElement)"
                            type="button"
                            class="guia-nav-item"
                            :class="seleccion === INICIO && !busqueda ? 'guia-nav-item--active' : ''"
                            :aria-current="seleccion === INICIO ? 'page' : undefined"
                            @click="seleccionar(INICIO)"
                        >
                            <LayoutGrid class="h-4 w-4 shrink-0" aria-hidden="true" /> Todos los temas
                        </button>
                        <div v-for="g in grupos" :key="g.titulo" class="mt-3">
                            <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-zinc-500">{{ g.titulo }}</p>
                            <button
                                v-for="m in g.modulos"
                                :key="m.id"
                                :ref="(el) => (navRefs[ordenados.indexOf(m) + 1] = el as HTMLElement)"
                                type="button"
                                class="guia-nav-item"
                                :class="seleccion === m.id && !busqueda ? 'guia-nav-item--active' : ''"
                                :aria-current="seleccion === m.id ? 'page' : undefined"
                                @click="seleccionar(m.id)"
                            >
                                <component :is="m.icono" class="h-4 w-4 shrink-0" aria-hidden="true" />
                                <span class="min-w-0 truncate">{{ m.nombre }}</span>
                            </button>
                        </div>
                    </nav>
                </aside>

                <!-- Contenido -->
                <main class="min-w-0" aria-live="polite">
                    <Transition
                        mode="out-in"
                        enter-active-class="motion-safe:transition motion-safe:duration-200 motion-safe:ease-out"
                        enter-from-class="motion-safe:opacity-0 motion-safe:translate-y-1"
                        leave-active-class="motion-safe:transition motion-safe:duration-100"
                        leave-to-class="motion-safe:opacity-0"
                    >
                        <!-- Resultados de búsqueda -->
                        <section v-if="busqueda" key="buscar" aria-label="Resultados de búsqueda" class="space-y-3">
                            <p class="text-sm text-slate-600 dark:text-zinc-400">
                                {{ resultados.length }} {{ resultados.length === 1 ? 'tema coincide' : 'temas coinciden' }} con «{{ busqueda }}».
                            </p>
                            <button
                                v-for="r in resultados"
                                :key="r.modulo.id"
                                type="button"
                                class="ui-card flex w-full items-start gap-3 p-4 text-left hover:border-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 dark:hover:border-white/15"
                                :class="anim"
                                @click="seleccionar(r.modulo.id)"
                            >
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="tonoGrupo(r.modulo.grupo)">
                                    <component :is="r.modulo.icono" class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-bold text-slate-900 dark:text-zinc-100">{{ r.modulo.nombre }}</span>
                                    <span class="mt-0.5 block text-pretty break-words text-sm text-slate-600 dark:text-zinc-400">{{ r.fragmento }}</span>
                                </span>
                            </button>
                            <div v-if="resultados.length === 0" class="ui-card p-6 text-center text-sm text-slate-600 dark:text-zinc-400">
                                Ningún tema de tu guía coincide. Prueba con otra palabra, por ejemplo «pago» o «requisición».
                            </div>
                        </section>

                        <!-- Todos los temas -->
                        <section v-else-if="!actual" key="inicio" class="space-y-6" aria-labelledby="guia-inicio-titulo" data-tour="ayuda-modulos">
                            <h3 id="guia-inicio-titulo" ref="tituloDetalle" tabindex="-1" class="sr-only">Todos los temas</h3>
                            <div v-for="g in grupos" :key="g.titulo" class="space-y-3">
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ g.titulo }}</h4>
                                <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                                    <article
                                        v-for="m in g.modulos"
                                        :key="m.id"
                                        class="ui-card group flex min-w-0 flex-col gap-3 p-4 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:hover:border-white/15 motion-reduce:transform-none"
                                        :class="anim"
                                    >
                                        <span class="flex items-start gap-3">
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="tonoGrupo(m.grupo)">
                                                <component :is="m.icono" class="h-5 w-5" aria-hidden="true" />
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-center gap-2">
                                                    <span class="block min-w-0 break-words font-bold text-slate-900 dark:text-zinc-100">{{ m.nombre }}</span>
                                                    <span v-if="visto(m)" class="inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300" title="Ya viste el recorrido de este módulo">
                                                        <CheckCircle2 class="h-3 w-3" aria-hidden="true" /> Visto
                                                    </span>
                                                </span>
                                                <span class="mt-0.5 block text-pretty break-words text-sm text-slate-600 dark:text-zinc-400">{{ m.resumen }}</span>
                                            </span>
                                        </span>
                                        <div class="mt-auto flex flex-wrap gap-1.5">
                                            <button type="button" class="guia-card-btn text-brand-accent" @click="seleccionar(m.id)">
                                                <BookOpen class="h-3.5 w-3.5" aria-hidden="true" /> Leer la guía
                                            </button>
                                            <button v-if="tourDisponible(m)" type="button" class="guia-card-btn" @click="iniciarRecorrido(m)">
                                                <PlayCircle class="h-3.5 w-3.5" aria-hidden="true" /> Iniciar recorrido
                                            </button>
                                            <Link v-if="safeRoute(m.ruta) && m.ruta !== 'ayuda.guia'" :href="safeRoute(m.ruta)!" class="guia-card-btn">
                                                Ir al módulo <ArrowUpRight class="h-3.5 w-3.5" aria-hidden="true" />
                                            </Link>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </section>

                        <!-- Detalle del módulo -->
                        <article v-else :key="actual.id" class="space-y-4" :aria-labelledby="`guia-${actual.id}`">
                            <header class="ui-card p-5 sm:p-6">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex min-w-0 items-start gap-4">
                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl" :class="tonoGrupo(actual.grupo)">
                                            <component :is="actual.icono" class="h-6 w-6" aria-hidden="true" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ actual.grupo }}</p>
                                            <h3 :id="`guia-${actual.id}`" ref="tituloDetalle" tabindex="-1" class="scroll-mt-24 text-xl font-black tracking-tight text-slate-900 focus:outline-none dark:text-zinc-100">
                                                {{ actual.nombre }}
                                            </h3>
                                            <p class="mt-1 text-pretty text-sm text-slate-600 dark:text-zinc-400">{{ actual.resumen }}</p>
                                        </div>
                                    </div>
                                    <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto">
                                        <button v-if="tourDisponible(actual)" type="button" class="ui-btn-secondary justify-center" @click="iniciarRecorrido(actual)">
                                            <PlayCircle class="h-4 w-4" aria-hidden="true" /> Iniciar recorrido
                                        </button>
                                        <Link v-if="safeRoute(actual.ruta) && actual.ruta !== 'ayuda.guia'" :href="safeRoute(actual.ruta)!" class="ui-btn-primary justify-center">
                                            Ir al módulo <ArrowUpRight class="h-4 w-4" aria-hidden="true" />
                                        </Link>
                                    </div>
                                </div>
                            </header>

                            <div class="grid gap-4 md:grid-cols-2">
                                <section class="ui-card p-5">
                                    <h4 class="guia-h"><Target class="h-4 w-4" aria-hidden="true" /> Qué es</h4>
                                    <p class="text-pretty text-sm text-slate-700 dark:text-zinc-300">{{ actual.paraQue }}</p>
                                </section>
                                <section class="ui-card p-5">
                                    <h4 class="guia-h"><Users class="h-4 w-4" aria-hidden="true" /> Quién tiene permiso</h4>
                                    <p class="text-pretty text-sm text-slate-700 dark:text-zinc-300">{{ actual.quien }}</p>
                                    <div v-if="tuAcceso(actual).length" class="mt-3">
                                        <p class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-500 dark:text-zinc-400"><KeyRound class="h-3.5 w-3.5" aria-hidden="true" /> Tu acceso</p>
                                        <ul class="flex flex-wrap gap-1.5">
                                            <li v-for="p in tuAcceso(actual)" :key="p" class="rounded-full bg-brand-accent/10 px-2.5 py-1 text-xs font-medium text-brand-accent">{{ p }}</li>
                                        </ul>
                                    </div>
                                </section>
                            </div>

                            <!-- Pasos -->
                            <section class="ui-card p-5 sm:p-6">
                                <h4 class="guia-h">Flujo principal</h4>
                                <ol class="relative space-y-4">
                                    <li v-for="(p, i) in actual.pasos.filter((x) => visibleTo(x))" :key="p.titulo" class="relative flex gap-4">
                                        <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-accent text-sm font-black text-brand-accent-fg" aria-hidden="true">{{ i + 1 }}</span>
                                        <div class="min-w-0 pt-1">
                                            <p class="font-semibold text-slate-900 dark:text-zinc-100"><span class="sr-only">Paso {{ i + 1 }}: </span>{{ p.titulo }}</p>
                                            <p class="text-pretty break-words text-sm text-slate-600 dark:text-zinc-400">{{ p.texto }}</p>
                                        </div>
                                    </li>
                                </ol>
                            </section>

                            <div class="grid gap-4" :class="actual.filtros?.length ? 'md:grid-cols-2' : ''">
                                <section class="ui-card p-5">
                                    <h4 class="guia-h"><CheckCircle2 class="h-4 w-4" aria-hidden="true" /> Qué puedes hacer</h4>
                                    <ul class="space-y-2">
                                        <li v-for="a in visibleItems(actual.acciones)" :key="itemText(a)" class="flex gap-2 text-sm text-slate-700 dark:text-zinc-300">
                                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                                            <span class="min-w-0 break-words">{{ itemText(a) }}</span>
                                        </li>
                                    </ul>
                                </section>
                                <section v-if="actual.filtros?.length" class="ui-card p-5">
                                    <h4 class="guia-h"><Filter class="h-4 w-4" aria-hidden="true" /> Filtros</h4>
                                    <ul class="space-y-2">
                                        <li v-for="f in visibleItems(actual.filtros)" :key="itemText(f)" class="flex gap-2 text-sm text-slate-700 dark:text-zinc-300">
                                            <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400" aria-hidden="true" />
                                            <span class="min-w-0 break-words">{{ itemText(f) }}</span>
                                        </li>
                                    </ul>
                                </section>
                            </div>

                            <section v-if="actual.estados?.length" class="ui-card p-5">
                                <h4 class="guia-h">Estados</h4>
                                <dl class="grid gap-3 sm:grid-cols-2">
                                    <div v-for="e in actual.estados" :key="e.nombre" class="min-w-0">
                                        <dt><span class="inline-flex max-w-full rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset" :class="TONO[e.tono]">{{ e.nombre }}</span></dt>
                                        <dd class="mt-1 text-pretty text-sm text-slate-600 dark:text-zinc-400">{{ e.texto }}</dd>
                                    </div>
                                </dl>
                            </section>

                            <div class="grid gap-4 lg:grid-cols-3">
                                <section class="rounded-2xl border border-rose-200 bg-rose-50/70 p-5 dark:border-rose-400/20 dark:bg-rose-500/5">
                                    <h4 class="guia-h !text-rose-800 dark:!text-rose-300"><CircleAlert class="h-4 w-4" aria-hidden="true" /> Errores frecuentes</h4>
                                    <ul class="space-y-2 text-sm text-rose-900/90 dark:text-rose-200/90">
                                        <li v-for="x in visibleItems(actual.errores)" :key="itemText(x)" class="text-pretty break-words">{{ itemText(x) }}</li>
                                    </ul>
                                </section>
                                <section class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5 dark:border-amber-400/20 dark:bg-amber-500/5">
                                    <h4 class="guia-h !text-amber-800 dark:!text-amber-300"><AlertTriangle class="h-4 w-4" aria-hidden="true" /> Advertencias</h4>
                                    <ul class="space-y-2 text-sm text-amber-900/90 dark:text-amber-200/90">
                                        <li v-for="x in visibleItems(actual.advertencias)" :key="itemText(x)" class="text-pretty break-words">{{ itemText(x) }}</li>
                                    </ul>
                                </section>
                                <section class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 dark:border-emerald-400/20 dark:bg-emerald-500/5">
                                    <h4 class="guia-h !text-emerald-800 dark:!text-emerald-300"><Lightbulb class="h-4 w-4" aria-hidden="true" /> Consejos</h4>
                                    <ul class="space-y-2 text-sm text-emerald-900/90 dark:text-emerald-200/90">
                                        <li v-for="x in visibleItems(actual.consejos)" :key="itemText(x)" class="text-pretty break-words">{{ itemText(x) }}</li>
                                    </ul>
                                </section>
                            </div>

                            <nav class="flex flex-col gap-2 sm:flex-row sm:justify-between" aria-label="Temas anterior y siguiente">
                                <button v-if="anterior" type="button" class="ui-btn-secondary justify-center" @click="seleccionar(anterior.id)">
                                    <ArrowLeft class="h-4 w-4" aria-hidden="true" /> {{ anterior.nombre }}
                                </button>
                                <span v-else />
                                <button v-if="siguiente" type="button" class="ui-btn-secondary justify-center" @click="seleccionar(siguiente.id)">
                                    {{ siguiente.nombre }} <ArrowRight class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </nav>
                        </article>
                    </Transition>
                </main>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.guia-nav-item {
    @apply relative flex min-h-[40px] w-full items-center gap-2.5 rounded-xl px-3 text-left text-[13.5px] font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-zinc-100;
}
.guia-nav-item--active {
    @apply bg-brand-accent/10 font-semibold text-brand-accent hover:bg-brand-accent/10 hover:text-brand-accent;
}
.guia-nav-item--active::before {
    content: '';
    @apply absolute left-0 top-2 bottom-2 w-1 rounded-full bg-brand-accent;
}
.guia-card-btn {
    @apply inline-flex min-h-[40px] items-center gap-1.5 rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition duration-150 hover:-translate-y-px hover:bg-slate-50 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 motion-reduce:transform-none dark:border-white/10 dark:text-zinc-200 dark:hover:bg-white/5;
}
.guia-h {
    @apply mb-3 flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-zinc-100;
}
</style>
