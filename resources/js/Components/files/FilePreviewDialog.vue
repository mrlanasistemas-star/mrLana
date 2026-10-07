<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from 'vue'
import { ChevronLeft, ChevronRight, Download, ExternalLink, FileQuestion, X } from 'lucide-vue-next'
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'

/**
 * Visor de archivos (imágenes y PDF) con navegación entre elementos.
 * Teclado: ← / → para cambiar, Esc para cerrar.
 */
export type PreviewItem = {
    id: number | string
    kind: 'image' | 'pdf' | 'file' | 'none'
    preview_url: string | null
    download_url: string | null
    archivo_original: string | null
    title: string
    subtitle?: string
}

const props = defineProps<{ items: PreviewItem[] }>()
const index = defineModel<number | null>('index', { default: null })

const item = computed(() => (index.value === null ? null : props.items[index.value] ?? null))
const open = computed({ get: () => item.value !== null, set: (v) => { if (!v) index.value = null } })
const hasPrev = computed(() => index.value !== null && index.value > 0)
const hasNext = computed(() => index.value !== null && index.value < props.items.length - 1)
const prev = () => { if (hasPrev.value) index.value = (index.value as number) - 1 }
const next = () => { if (hasNext.value) index.value = (index.value as number) + 1 }

function onKey(e: KeyboardEvent) {
    if (!open.value) return
    if (e.key === 'ArrowLeft') prev()
    if (e.key === 'ArrowRight') next()
}
watch(open, (o) => (o ? window.addEventListener('keydown', onKey) : window.removeEventListener('keydown', onKey)))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

const navBtn =
    'inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 ' +
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 disabled:opacity-40 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-white/5'
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-[400] bg-black/70 backdrop-blur-sm data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none" />
            <DialogContent
                class="fixed inset-2 z-[401] flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl focus:outline-none
                       dark:border-white/10 dark:bg-zinc-950 sm:inset-6 lg:inset-x-[8vw] lg:inset-y-6
                       data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 motion-reduce:animate-none"
            >
                <template v-if="item">
                    <header class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-4 py-3 dark:border-white/[0.06]">
                        <div class="min-w-0 flex-1">
                            <DialogTitle class="truncate text-sm font-bold text-slate-900 dark:text-zinc-100">{{ item.title }}</DialogTitle>
                            <DialogDescription class="truncate text-xs text-slate-500 dark:text-zinc-400">
                                {{ item.subtitle ?? item.archivo_original }} · {{ (index ?? 0) + 1 }} de {{ items.length }}
                            </DialogDescription>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" :class="navBtn" :disabled="!hasPrev" aria-label="Anterior" @click="prev"><ChevronLeft class="h-4 w-4" /></button>
                            <button type="button" :class="navBtn" :disabled="!hasNext" aria-label="Siguiente" @click="next"><ChevronRight class="h-4 w-4" /></button>
                            <a v-if="item.preview_url" :href="item.preview_url" target="_blank" rel="noopener" :class="navBtn" aria-label="Abrir en otra pestaña" title="Abrir en otra pestaña"><ExternalLink class="h-4 w-4" /></a>
                            <a v-if="item.download_url" :href="item.download_url" class="ui-btn-primary min-h-[40px]"><Download class="h-4 w-4" aria-hidden="true" /> <span class="hidden sm:inline">Descargar</span></a>
                            <DialogClose :class="navBtn" aria-label="Cerrar"><X class="h-4 w-4" /></DialogClose>
                        </div>
                    </header>

                    <div class="relative min-h-0 flex-1 bg-slate-100 dark:bg-black/40">
                        <img
                            v-if="item.kind === 'image' && item.preview_url"
                            :key="String(item.id)"
                            :src="item.preview_url"
                            :alt="item.archivo_original ?? item.title"
                            class="absolute inset-0 h-full w-full object-contain p-2"
                        />
                        <iframe
                            v-else-if="item.kind === 'pdf' && item.preview_url"
                            :key="String(item.id)"
                            :src="item.preview_url + '#view=FitH'"
                            :title="item.archivo_original ?? item.title"
                            class="absolute inset-0 h-full w-full border-0 bg-white"
                        />
                        <div v-else class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center">
                            <FileQuestion class="h-12 w-12 text-slate-400" aria-hidden="true" />
                            <p class="text-sm font-semibold text-slate-700 dark:text-zinc-200">
                                {{ item.preview_url ? 'Este tipo de archivo no tiene vista previa.' : 'No hay archivo adjunto.' }}
                            </p>
                            <a v-if="item.download_url" :href="item.download_url" class="ui-btn-secondary"><Download class="h-4 w-4" aria-hidden="true" /> Descargar</a>
                        </div>

                        <button v-if="hasPrev" type="button" class="absolute left-3 top-1/2 hidden -translate-y-1/2 rounded-full bg-black/50 p-2.5 text-white transition hover:bg-black/70 sm:block" aria-label="Anterior" @click="prev">
                            <ChevronLeft class="h-5 w-5" />
                        </button>
                        <button v-if="hasNext" type="button" class="absolute right-3 top-1/2 hidden -translate-y-1/2 rounded-full bg-black/50 p-2.5 text-white transition hover:bg-black/70 sm:block" aria-label="Siguiente" @click="next">
                            <ChevronRight class="h-5 w-5" />
                        </button>
                    </div>
                </template>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
