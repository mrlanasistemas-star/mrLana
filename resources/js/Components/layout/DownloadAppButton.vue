<script setup lang="ts">
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { MonitorDown, Smartphone } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/Components/ui/dialog'
import { usePwaInstall } from '@/Composables/usePwaInstall'
import type { SharedProps } from '@/types/shared'

/**
 * Botón para tener el ERP como aplicación.
 * - Si Configuración define una URL de descarga (p. ej. un APK), la abre.
 * - Si no, instala el ERP como aplicación (PWA) en escritorio (Chrome/Edge)
 *   y Android con el diálogo nativo del navegador. Si el navegador no ofrece
 *   el diálogo, muestra los pasos para instalarla manualmente.
 * - Si ya se abre como aplicación instalada, no se muestra.
 */
defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
    /** 'full': icono + texto; 'compact': solo icono (móvil); 'menu': fila de menú. */
    variant?: 'full' | 'compact' | 'menu'
}>(), { variant: 'full' })

const page = usePage<SharedProps>()
const pwa = usePwaInstall()

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

const visible = computed(() => !pwa.installed.value)
const label = computed(() => (url.value ? 'Descargar app' : 'Instalar app'))
const hint = computed(() => (url.value ? 'Descargar la aplicación' : 'Instalar MR-Lana como aplicación en este equipo'))

const helpOpen = ref(false)
async function onInstall() {
    if (!(await pwa.install())) helpOpen.value = true
}

const steps = computed(() => {
    if (!pwa.secure) {
        return { title: 'Se necesita una conexión segura', items: ['Abre el ERP con una dirección que empiece con https:// y vuelve a presionar «Instalar app».'] }
    }
    if (pwa.isIOS) {
        return { title: 'Instalar en iPhone o iPad', items: ['Abre el ERP en Safari.', 'Toca el botón Compartir (cuadro con flecha hacia arriba).', 'Elige «Agregar a pantalla de inicio» y confirma con «Agregar».'] }
    }
    if (pwa.isAndroid) {
        return { title: 'Instalar en Android', items: ['Abre el ERP en Chrome.', 'Toca el menú ⋮ (arriba a la derecha).', 'Elige «Instalar app» o «Agregar a pantalla principal» y confirma.'] }
    }
    if (pwa.isFirefox || pwa.isSafari) {
        return { title: 'Este navegador no instala aplicaciones', items: ['Abre el ERP en Google Chrome o Microsoft Edge.', 'Presiona de nuevo «Instalar app».'] }
    }
    return {
        title: 'Instalar en esta computadora',
        items: [
            'En la barra de direcciones, haz clic en el ícono de instalar (monitor con flecha) que aparece a la derecha.',
            'O abre el menú ⋮ del navegador → «Transmitir, guardar y compartir» → «Instalar página como app» (Chrome) o «Aplicaciones» → «Instalar este sitio como una aplicación» (Edge).',
            'Si ya la instalaste, búscala como «MR-Lana» en el menú Inicio o en tus aplicaciones.',
        ],
    }
})

const base = computed(() => ({
    full: 'inline-flex h-10 items-center gap-2 rounded-full border px-3.5 text-sm font-semibold',
    compact: 'inline-flex h-10 w-10 items-center justify-center rounded-full border',
    menu: 'flex min-h-[42px] w-full items-center gap-3 rounded-xl px-3 text-[13.5px] font-medium',
}[props.variant]))

const cls = computed(() => [
    base.value,
    props.variant === 'menu'
        ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-white/[0.06] dark:hover:text-zinc-100'
        : 'border-slate-200/80 bg-white text-slate-800 shadow-sm hover:border-slate-300 hover:shadow dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:border-zinc-700',
    'transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50',
])
const icon = computed(() => (url.value ? Smartphone : MonitorDown))
</script>

<template>
    <template v-if="visible">
        <a
            v-if="url"
            :href="url"
            target="_blank"
            rel="noopener noreferrer external"
            v-bind="$attrs"
            :class="cls"
            :aria-label="variant === 'compact' ? label : undefined"
            :title="hint"
        >
            <component :is="icon" class="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
            <span v-if="variant !== 'compact'" class="whitespace-nowrap"><slot :label="label">{{ label }}</slot></span>
        </a>
        <button
            v-else
            type="button"
            v-bind="$attrs"
            :class="cls"
            :aria-label="variant === 'compact' ? label : undefined"
            :title="hint"
            @click="onInstall"
        >
            <component :is="icon" class="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
            <span v-if="variant !== 'compact'" class="whitespace-nowrap"><slot :label="label">{{ label }}</slot></span>
        </button>

        <Dialog v-model:open="helpOpen">
            <DialogContent class="max-w-md">
                <div class="flex items-start gap-3 pr-8">
                    <img src="/icons/icon-192.png" alt="" class="h-12 w-12 shrink-0 rounded-xl ring-1 ring-slate-200 dark:ring-white/10" />
                    <div class="min-w-0">
                        <DialogTitle class="text-base font-bold text-slate-900 dark:text-zinc-100">{{ steps.title }}</DialogTitle>
                        <DialogDescription class="mt-0.5 text-sm text-slate-500 dark:text-zinc-400">
                            MR-Lana se abrirá en su propia ventana, como cualquier otra aplicación.
                        </DialogDescription>
                    </div>
                </div>
                <ol class="space-y-2.5">
                    <li v-for="(s, i) in steps.items" :key="i" class="flex gap-3 text-sm text-slate-700 dark:text-zinc-200">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-primary text-xs font-bold text-brand-primary-fg">{{ i + 1 }}</span>
                        <span class="min-w-0 break-words pt-0.5">{{ s }}</span>
                    </li>
                </ol>
                <div class="flex justify-end">
                    <button type="button" class="ui-btn-primary" @click="helpOpen = false">
                        Entendido
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </template>
</template>
