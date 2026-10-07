<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Settings2, Smartphone } from 'lucide-vue-next'
import { usePermissions } from '@/Composables/usePermissions'
import type { SharedProps } from '@/types/shared'

/**
 * Enlace "Descargar aplicación" (AppView/WebView del ERP).
 * La URL viene de Configuración (o ERP_MOBILE_APP_URL). Nunca se inventa un enlace:
 * - Con URL: abre la descarga en una ventana nueva.
 * - Sin URL y con permiso de administrar Configuración: lleva a configurarla.
 * - Sin URL y sin permiso: se muestra deshabilitado con una explicación.
 *
 * Dentro del AppView, el contenedor nativo debe abrir los enlaces externos en
 * el navegador del sistema (ver docs/despliegue.md).
 */
const props = withDefaults(defineProps<{
    /** 'full': icono + texto; 'compact': solo icono (móvil); 'menu': fila de menú. */
    variant?: 'full' | 'compact' | 'menu'
}>(), { variant: 'full' })

const page = usePage<SharedProps>()
const { can } = usePermissions()

const url = computed(() => {
    const raw = page.props.appSettings?.mobile_app_url
    if (!raw) return null
    try {
        const parsed = new URL(raw)
        return ['https:', 'http:'].includes(parsed.protocol) ? parsed.toString() : null
    } catch {
        return null
    }
})

const canConfigure = computed(() => !url.value && can('configuracion.administrar'))
const state = computed<'ready' | 'configure' | 'unavailable'>(() => (url.value ? 'ready' : canConfigure.value ? 'configure' : 'unavailable'))

const label = computed(() => ({
    ready: 'Descargar app',
    configure: 'Configurar descarga',
    unavailable: 'Descargar app',
}[state.value]))

const hint = computed(() => ({
    ready: 'Descargar la aplicación móvil',
    configure: 'Aún no hay enlace de descarga. Agrégalo en Configuración.',
    unavailable: 'La descarga de la aplicación estará disponible pronto.',
}[state.value]))

const base = computed(() => ({
    full: 'inline-flex h-10 items-center gap-2 rounded-full border px-3.5 text-sm font-semibold',
    compact: 'inline-flex h-10 w-10 items-center justify-center rounded-full border',
    menu: 'flex min-h-[42px] w-full items-center gap-3 rounded-xl px-3 text-[13.5px] font-medium',
}[props.variant]))

const tone = computed(() => {
    if (state.value === 'unavailable') {
        return props.variant === 'menu'
            ? 'cursor-not-allowed text-slate-400 dark:text-zinc-600'
            : 'cursor-not-allowed border-slate-200/80 text-slate-400 dark:border-zinc-800 dark:text-zinc-600'
    }
    return props.variant === 'menu'
        ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-white/[0.06] dark:hover:text-zinc-100'
        : 'border-slate-200/80 bg-white text-slate-800 shadow-sm hover:border-slate-300 hover:shadow dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:border-zinc-700'
})

const icon = computed(() => (state.value === 'configure' ? Settings2 : Smartphone))
const cls = computed(() => [base.value, tone.value, 'transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50'])
</script>

<template>
    <a
        v-if="state === 'ready'"
        :href="url!"
        target="_blank"
        rel="noopener noreferrer external"
        :class="cls"
        :aria-label="variant === 'compact' ? label : undefined"
        :title="hint"
    >
        <component :is="icon" class="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
        <span v-if="variant !== 'compact'" class="whitespace-nowrap"><slot :label="label">{{ label }}</slot></span>
    </a>
    <Link
        v-else-if="state === 'configure'"
        :href="route('configuracion.edit') + '#app-url'"
        :class="cls"
        :aria-label="variant === 'compact' ? label : undefined"
        :title="hint"
    >
        <component :is="icon" class="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
        <span v-if="variant !== 'compact'" class="whitespace-nowrap"><slot :label="label">{{ label }}</slot></span>
    </Link>
    <button
        v-else
        type="button"
        disabled
        aria-disabled="true"
        :class="cls"
        :aria-label="variant === 'compact' ? `${label} (no disponible)` : undefined"
        :title="hint"
    >
        <component :is="icon" class="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
        <span v-if="variant !== 'compact'" class="whitespace-nowrap"><slot :label="label">{{ label }}</slot></span>
    </button>
</template>
