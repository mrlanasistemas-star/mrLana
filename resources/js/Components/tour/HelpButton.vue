<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui'
import { BookOpen, CheckCircle2, Compass, PlayCircle, Route } from 'lucide-vue-next'
import { useTour } from '@/Composables/useTour'

/**
 * Botón flotante de ayuda (todas las pantallas). Muestra los recorridos de la
 * pantalla actual, el recorrido completo y el enlace a la guía escrita. Pulsa
 * discretamente si hay recorridos sin ver en esta pantalla.
 */
const page = usePage()
const tour = useTour()
const open = ref(false)

const here = computed(() => {
    void page.url // recalcula al navegar
    return tour.forCurrentScreen()
})
const pulse = computed(() => {
    void page.url
    return tour.hasUnseenHere.value
})

function startTour(id: string) {
    open.value = false
    tour.start(id)
}
function startFull() {
    open.value = false
    tour.startFull()
}
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger as-child>
            <button
                v-show="tour.state.phase === 'idle'"
                type="button"
                data-tour="ayuda-boton"
                class="group fixed right-4 z-[190] inline-flex h-12 w-12 items-center justify-center rounded-full bg-brand-primary text-brand-primary-fg shadow-lg shadow-black/20
                       ring-4 ring-white/70 transition duration-200 hover:scale-105 hover:shadow-xl active:scale-95 focus:outline-none focus-visible:ring-brand-accent/60
                       dark:ring-zinc-950/70 motion-reduce:transform-none
                       bottom-[calc(5.75rem+env(safe-area-inset-bottom))] lg:bottom-6 lg:right-6"
                :aria-label="pulse ? 'Ayuda: hay recorridos nuevos en esta pantalla' : 'Ayuda y recorridos'"
                title="Ayuda y recorridos"
            >
                <span v-if="pulse" class="absolute inset-0 rounded-full bg-brand-accent/40 animate-ping motion-reduce:hidden" aria-hidden="true" />
                <span v-if="pulse" class="absolute right-0.5 top-0.5 h-3 w-3 rounded-full border-2 border-white bg-brand-accent dark:border-zinc-950" aria-hidden="true" />
                <Compass class="relative h-6 w-6 transition-transform duration-300 group-hover:rotate-45 motion-reduce:transform-none" aria-hidden="true" />
            </button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                side="top"
                align="end"
                :side-offset="10"
                :collision-padding="12"
                class="z-[300] w-[min(22rem,calc(100vw-1.5rem))] rounded-2xl border border-slate-200 bg-white p-2 text-slate-800 shadow-2xl outline-none
                       data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0 data-[state=open]:zoom-in-95
                       dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100 motion-reduce:animate-none"
            >
                <p class="px-3 pb-1 pt-2 text-[11px] font-black uppercase tracking-widest text-slate-400">En esta pantalla</p>
                <ul v-if="here.length" class="space-y-0.5">
                    <li v-for="t in here" :key="t.id">
                        <button type="button" class="help-item" @click="startTour(t.id)">
                            <component :is="t.icon" class="h-4 w-4 shrink-0 text-brand-accent" aria-hidden="true" />
                            <span class="min-w-0 flex-1 text-left">
                                <span class="block truncate font-semibold">{{ t.title }}</span>
                                <span class="block truncate text-xs text-slate-500 dark:text-zinc-400">{{ t.description }}</span>
                            </span>
                            <CheckCircle2 v-if="tour.seen.has(t.id)" class="h-4 w-4 shrink-0 text-emerald-500" aria-label="Ya visto" />
                            <PlayCircle v-else class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
                <p v-else class="px-3 py-2 text-sm text-slate-500 dark:text-zinc-400">Esta pantalla no tiene un recorrido propio.</p>

                <div class="my-1.5 h-px bg-slate-100 dark:bg-white/10" />
                <button type="button" class="help-item" @click="startFull">
                    <Route class="h-4 w-4 shrink-0 text-brand-accent" aria-hidden="true" />
                    <span class="flex-1 text-left font-semibold">Recorrido completo</span>
                </button>
                <Link :href="route('ayuda.guia')" class="help-item" @click="open = false">
                    <BookOpen class="h-4 w-4 shrink-0 text-brand-accent" aria-hidden="true" />
                    <span class="flex-1 text-left font-semibold">Guía escrita</span>
                </Link>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>

<style scoped>
.help-item {
    @apply flex min-h-[44px] w-full items-center gap-3 rounded-xl px-3 py-2 text-sm transition duration-150
        hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/40 dark:hover:bg-white/10;
}
</style>
