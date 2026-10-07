<script setup lang="ts">
import { ref } from 'vue'
import { Eye, FileText, FileX, ImageOff } from 'lucide-vue-next'

/** Miniatura de archivo para tarjetas: imagen real o portada de PDF. Clic → vista previa. */
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
        <div v-else-if="kind === 'pdf'" class="flex h-full w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-rose-50 to-white dark:from-rose-500/10 dark:to-transparent">
            <span class="relative flex h-16 w-14 items-center justify-center rounded-lg bg-white shadow-md ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-white/10">
                <FileText class="h-7 w-7 text-rose-500" aria-hidden="true" />
                <span class="absolute -bottom-2 rounded bg-rose-500 px-1.5 py-px text-[10px] font-black text-white">PDF</span>
            </span>
            <span class="mt-2 line-clamp-2 max-w-[85%] break-words text-center text-[11px] text-slate-500 dark:text-zinc-400">{{ name }}</span>
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
                <Eye class="h-3.5 w-3.5" /> Vista previa
            </span>
        </span>
        <slot />
    </button>
</template>
