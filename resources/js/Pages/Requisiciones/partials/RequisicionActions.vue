<script setup lang="ts">
import { computed } from 'vue'
import { DropdownMenuItem, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui'
import {
    Banknote,
    Eye,
    FileText,
    MoreHorizontal,
    Printer,
    Scale,
    Send,
    Trash2,
    Ban,
} from 'lucide-vue-next'
import { DropdownMenuContent } from '@/Components/ui/dropdown-menu'
import type { RequisicionRow } from '../Requisiciones.types'

/**
 * Acciones de una requisición. Solo muestra lo que el servidor reporta en
 * `row.can`; cada endpoint vuelve a autorizar. Todas las opciones llevan texto.
 */
const props = withDefaults(defineProps<{
    row: RequisicionRow
    size?: 'sm' | 'md'
    /** Muestra "Ver" como botón con texto fuera del menú. */
    showPrimary?: boolean
}>(), { size: 'sm', showPrimary: true })

const emit = defineEmits<{
    (e: 'show'): void
    (e: 'pay'): void
    (e: 'comprobar'): void
    (e: 'ajustes'): void
    (e: 'print'): void
    (e: 'capture'): void
    (e: 'request-delete'): void
    (e: 'delete'): void
}>()

const can = computed(() => props.row.can)
const isDeleted = computed(() => props.row.status === 'ELIMINADA')

const btn = computed(() =>
    props.size === 'md'
        ? 'min-h-[44px] px-3.5 text-sm'
        : 'min-h-[36px] px-3 text-xs',
)

const itemClass =
    'flex min-h-[40px] cursor-pointer select-none items-center gap-2.5 rounded-lg px-3 text-sm text-slate-700 outline-none ' +
    'data-[highlighted]:bg-slate-100 data-[highlighted]:text-slate-900 dark:text-zinc-200 dark:data-[highlighted]:bg-white/10'
</script>

<template>
    <div class="flex flex-wrap items-center justify-end gap-1.5">
        <button
            v-if="showPrimary"
            type="button"
            :class="[btn, 'inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60 dark:border-white/10 dark:bg-white/5 dark:text-zinc-200 dark:hover:bg-white/10']"
            @click="emit('show')"
        >
            <Eye class="h-4 w-4" aria-hidden="true" /> Ver
        </button>

        <button
            v-if="can?.capturar"
            type="button"
            :class="[btn, 'inline-flex items-center gap-1.5 rounded-xl bg-brand-button font-semibold text-brand-button-fg transition hover:bg-brand-button/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60']"
            @click="emit('capture')"
        >
            <Send class="h-4 w-4" aria-hidden="true" /> Enviar
        </button>

        <DropdownMenuRoot v-if="!isDeleted">
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    :class="[btn, 'inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60 dark:border-white/10 dark:bg-white/5 dark:text-zinc-200 dark:hover:bg-white/10']"
                    :aria-label="`Más acciones para ${row.folio}`"
                >
                    <MoreHorizontal class="h-4 w-4" aria-hidden="true" /> Acciones
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                :side-offset="6"
                :collision-padding="12"
                class="z-[300] w-60 rounded-2xl border-slate-200 bg-white p-1.5 shadow-2xl dark:border-white/10 dark:bg-zinc-950"
            >
                <DropdownMenuItem :class="itemClass" @select="emit('show')">
                    <Eye class="h-4 w-4" aria-hidden="true" /> Ver detalle
                </DropdownMenuItem>
                <DropdownMenuItem v-if="can?.ver_pagos" :class="itemClass" @select="emit('pay')">
                    <Banknote class="h-4 w-4" aria-hidden="true" /> {{ can?.registrar_pago || can?.autorizar_pago ? 'Pagos' : 'Ver pagos' }}
                </DropdownMenuItem>
                <DropdownMenuItem v-if="can?.ver_comprobaciones" :class="itemClass" @select="emit('comprobar')">
                    <FileText class="h-4 w-4" aria-hidden="true" /> Comprobaciones
                </DropdownMenuItem>
                <DropdownMenuItem v-if="can?.solicitar_ajuste" :class="itemClass" @select="emit('ajustes')">
                    <Scale class="h-4 w-4" aria-hidden="true" /> Solicitar ajuste
                </DropdownMenuItem>
                <DropdownMenuItem v-else-if="can?.ver_ajustes" :class="itemClass" @select="emit('ajustes')">
                    <Scale class="h-4 w-4" aria-hidden="true" /> Ver ajustes
                </DropdownMenuItem>
                <DropdownMenuItem :class="itemClass" @select="emit('print')">
                    <Printer class="h-4 w-4" aria-hidden="true" /> Imprimir PDF
                </DropdownMenuItem>

                <template v-if="can?.solicitar_eliminacion || can?.eliminar">
                    <DropdownMenuSeparator class="my-1 h-px bg-slate-100 dark:bg-white/10" />
                    <DropdownMenuItem
                        v-if="can?.solicitar_eliminacion"
                        :class="[itemClass, 'text-amber-700 data-[highlighted]:bg-amber-50 dark:text-amber-300 dark:data-[highlighted]:bg-amber-500/10']"
                        @select="emit('request-delete')"
                    >
                        <Ban class="h-4 w-4" aria-hidden="true" /> Solicitar eliminación
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-if="can?.eliminar"
                        :class="[itemClass, 'text-rose-600 data-[highlighted]:bg-rose-50 dark:text-rose-300 dark:data-[highlighted]:bg-rose-500/10']"
                        @select="emit('delete')"
                    >
                        <Trash2 class="h-4 w-4" aria-hidden="true" /> Eliminar
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenuRoot>
    </div>
</template>
