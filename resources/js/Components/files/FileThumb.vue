<script setup lang="ts">
import { ref } from 'vue'
import { Eye, FileText, FileX, ImageOff } from 'lucide-vue-next'

/**
 * Miniatura de archivo para tarjetas: la imagen o el PDF se ven directamente
 * (sin tener que abrirlos). Clic → vista previa grande en la misma pantalla.
 */
const props = defineProps<{
    kind: 'image' | 'pdf' | 'file' | 'none'
    url: string | null
    name: string | null
    ext?: string
}>()
defineEmits<{ (e: 'open'): void }>()

const broken = ref(false)
</script>

<template>
    <button
        type="button"
        class="group/thumb relative block aspect-[4/3] w-full overflow-hidden bg-slate-100 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-accent/60 dark:bg-white/[0.04]"
        :aria-label="`Vista previa de ${name ?? 'archivo'}`"
        :disabled="kind === 'none'"
        @click="$emit('open')"
    >
        <img
            v-if="kind === 'image' && url && !broken"
            :src="url"
            :alt="name ?? ''"
            loading="lazy"
            class="h-full w-full object-contain p-1.5 transition duration-300 group-hover/thumb:scale-[1.03] motion-reduce:transform-none"
            @error="broken = true"
        />
        <!-- PDF: primera página visible en la tarjeta. El iframe no recibe clics: el clic abre el visor. -->
        <div v-else-if="kind === 'pdf' && url" class="relative h-full w-full bg-white">
            <iframe
                :src="`${url}#page=1&toolbar=0&navpanes=0&scrollbar=0&view=FitH`"
                :title="`Vista previa de ${name ?? 'PDF'}`"
                loading="lazy"
                tabindex="-1"
                class="pointer-events-none absolute inset-0 h-full w-full border-0"
            />
            <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-md bg-rose-500 px-1.5 py-0.5 text-[10px] font-black text-white shadow">
                <FileText class="h-3 w-3" aria-hidden="true" /> PDF
            </span>
        </div>
        <div v-else class="flex h-full w-full flex-col items-center justify-center gap-2 text-slate-400 dark:text-zinc-500">
            <component :is="kind === 'none' ? FileX : ImageOff" class="h-8 w-8" aria-hidden="true" />
            <span class="text-xs">{{ kind === 'none' ? 'Sin archivo' : (ext ? ext.toUpperCase() : 'Archivo') }}</span>
        </div>

        <span
            v-if="kind !== 'none'"
            class="absolute inset-0 flex items-center justify-center bg-slate-900/0 opacity-0 transition group-hover/thumb:bg-slate-900/35 group-hover/thumb:opacity-100 group-focus-visible/thumb:opacity-100"
            aria-hidden="true"
        >
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-xs font-semibold text-slate-900 shadow">
                <Eye class="h-3.5 w-3.5" /> Abrir
            </span>
        </span>
        <slot />
    </button>
</template>
