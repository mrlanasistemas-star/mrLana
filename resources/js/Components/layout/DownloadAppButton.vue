<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Smartphone } from 'lucide-vue-next'
import type { SharedProps } from '@/types/shared'

/**
 * Enlace "Descargar aplicación" (AppView/WebView del ERP).
 * La URL viene de Configuración (o ERP_MOBILE_APP_URL). Sin URL configurada
 * se muestra deshabilitado; nunca se inventa un enlace.
 *
 * Se abre en una ventana nueva con rel="noopener noreferrer": dentro del
 * AppView, el contenedor nativo debe abrir los enlaces externos en el
 * navegador del sistema (ver docs/despliegue.md).
 */
const props = withDefaults(defineProps<{
    /** 'full': icono + texto; 'compact': solo icono (móvil); 'menu': fila de menú. */
    variant?: 'full' | 'compact' | 'menu'
}>(), { variant: 'full' })

const page = usePage<SharedProps>()

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

const label = computed(() => (url.value ? 'Descargar aplicación' : 'Descarga no configurada'))

const base = computed(() => ({
    full: 'inline-flex h-10 items-center gap-2 rounded-full border px-3.5 text-sm font-semibold',
    compact: 'inline-flex h-10 w-10 items-center justify-center rounded-full border',
    menu: 'flex min-h-[42px] w-full items-center gap-3 rounded-xl px-3 text-[13.5px] font-medium',
}[props.variant]))
</script>

<template>
    <a
        v-if="url"
        :href="url"
        target="_blank"
        rel="noopener noreferrer external"
        :class="[
            base,
            variant === 'menu'
                ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-zinc-800/70 dark:hover:text-zinc-100'
                : 'border-slate-200/80 bg-white/90 text-slate-800 shadow-sm hover:-translate-y-px hover:shadow-md dark:border-zinc-700/70 dark:bg-zinc-900/40 dark:text-zinc-100',
            'transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60 motion-reduce:transform-none',
        ]"
        :aria-label="variant === 'compact' ? label : undefined"
        :title="label"
    >
        <Smartphone class="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
        <span v-if="variant !== 'compact'" class="whitespace-nowrap"><slot>{{ label }}</slot></span>
    </a>
    <button
        v-else
        type="button"
        disabled
        aria-disabled="true"
        :class="[
            base,
            variant === 'menu'
                ? 'text-slate-400 dark:text-zinc-600'
                : 'border-dashed border-slate-300 text-slate-400 dark:border-zinc-700 dark:text-zinc-500',
            'cursor-not-allowed',
        ]"
        :aria-label="variant === 'compact' ? label : undefined"
        :title="label"
    >
        <Smartphone class="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
        <span v-if="variant !== 'compact'" class="whitespace-nowrap"><slot>{{ label }}</slot></span>
    </button>
</template>
