<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Ban, Banknote, Eye, FileText, Printer, Scale, Send, Trash2 } from 'lucide-vue-next'
import type { RequisicionRow } from '../Requisiciones.types'

/**
 * Acciones de una requisición, siempre visibles como botones.
 *
 * La navegación usa enlaces reales (<Link>/<a>): clic derecho → «Abrir en otra
 * pestaña», Ctrl/Cmd + clic o clic central abren varias requisiciones a la vez.
 * Solo muestra lo que el servidor reporta en `row.can`; cada endpoint vuelve a
 * autorizar.
 */
const props = withDefaults(defineProps<{
    row: RequisicionRow
    size?: 'sm' | 'md'
}>(), { size: 'sm' })

const emit = defineEmits<{
    (e: 'capture'): void
    (e: 'request-delete'): void
    (e: 'delete'): void
}>()

const can = computed(() => props.row.can ?? ({} as NonNullable<RequisicionRow['can']>))
const isDeleted = computed(() => props.row.status === 'ELIMINADA')

const base = computed(() => [
    'group/btn inline-flex shrink-0 items-center gap-1.5 rounded-xl border font-semibold whitespace-nowrap',
    'transition-all duration-150 ease-out hover:-translate-y-px hover:shadow-md active:translate-y-0 active:scale-[0.98]',
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 focus-visible:ring-slate-400/70 dark:focus-visible:ring-offset-neutral-900',
    'motion-reduce:transform-none motion-reduce:transition-none',
    props.size === 'md' ? 'min-h-[44px] px-3.5 text-sm' : 'min-h-[38px] px-3 text-xs',
].join(' '))

const tone = {
    neutral: 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 dark:border-white/10 dark:bg-white/5 dark:text-zinc-200 dark:hover:bg-white/10',
    primary: 'border-transparent bg-brand-button text-brand-button-fg hover:bg-brand-button/90 shadow-sm',
    sky: 'border-sky-200/80 bg-sky-50 text-sky-800 hover:border-sky-300 hover:bg-sky-100 dark:border-sky-500/25 dark:bg-sky-500/10 dark:text-sky-200 dark:hover:bg-sky-500/20',
    violet: 'border-violet-200/80 bg-violet-50 text-violet-800 hover:border-violet-300 hover:bg-violet-100 dark:border-violet-500/25 dark:bg-violet-500/10 dark:text-violet-200 dark:hover:bg-violet-500/20',
    teal: 'border-teal-200/80 bg-teal-50 text-teal-800 hover:border-teal-300 hover:bg-teal-100 dark:border-teal-500/25 dark:bg-teal-500/10 dark:text-teal-200 dark:hover:bg-teal-500/20',
    amber: 'border-amber-200/80 bg-amber-50 text-amber-800 hover:border-amber-300 hover:bg-amber-100 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-200 dark:hover:bg-amber-500/20',
    rose: 'border-rose-200/80 bg-white text-rose-700 hover:border-rose-300 hover:bg-rose-50 dark:border-rose-500/25 dark:bg-rose-500/5 dark:text-rose-300 dark:hover:bg-rose-500/15',
}

const icon = 'h-4 w-4 shrink-0 transition-transform duration-150 group-hover/btn:scale-110 motion-reduce:transform-none'
</script>

<template>
    <div data-tour="requisiciones-acciones" class="flex flex-wrap items-center gap-1.5" :aria-label="`Acciones de ${row.folio}`" role="group">
        <Link :href="route('requisiciones.show', row.id)" :class="[base, tone.neutral]" title="Ver detalle (clic derecho para abrir en otra pestaña)">
            <Eye :class="icon" aria-hidden="true" /> Ver
        </Link>

        <button v-if="can.capturar" type="button" :class="[base, tone.primary]" @click="emit('capture')">
            <Send :class="icon" aria-hidden="true" /> Enviar
        </button>

        <template v-if="!isDeleted">
            <Link v-if="can.ver_pagos" :href="route('requisiciones.pagar', row.id)" :class="[base, tone.sky]">
                <Banknote :class="icon" aria-hidden="true" /> {{ can.registrar_pago || can.autorizar_pago ? 'Pagos' : 'Ver pagos' }}
            </Link>
            <Link v-if="can.ver_comprobaciones" :href="route('requisiciones.comprobar', row.id)" :class="[base, tone.teal]">
                <FileText :class="icon" aria-hidden="true" /> Comprobaciones
            </Link>
            <Link v-if="can.solicitar_ajuste || can.ver_ajustes" :href="route('requisiciones.ajustes', row.id)" :class="[base, tone.violet]">
                <Scale :class="icon" aria-hidden="true" /> {{ can.solicitar_ajuste ? 'Ajustes' : 'Ver ajustes' }}
            </Link>
        </template>

        <a
            v-if="can.imprimir"
            :href="route('requisiciones.print', row.id)"
            target="_blank"
            rel="noopener"
            :class="[base, tone.neutral]"
            title="Abre el PDF en otra pestaña"
        >
            <Printer :class="icon" aria-hidden="true" /> PDF
        </a>

        <button v-if="!isDeleted && can.solicitar_eliminacion" type="button" :class="[base, tone.amber]" @click="emit('request-delete')">
            <Ban :class="icon" aria-hidden="true" /> Solicitar eliminación
        </button>
        <button v-if="!isDeleted && can.eliminar" type="button" :class="[base, tone.rose]" @click="emit('delete')">
            <Trash2 :class="icon" aria-hidden="true" /> Eliminar
        </button>
    </div>
</template>
