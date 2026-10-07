<script setup lang="ts">
import { Inbox } from 'lucide-vue-next'
import type { Component } from 'vue'

/** Tarjeta de gráfica con encabezado, estado vacío y acción opcional. */
defineProps<{
    title: string
    description?: string
    icon?: Component
    empty?: boolean
    emptyText?: string
}>()
</script>

<template>
    <section class="ui-card flex min-w-0 flex-col overflow-hidden">
        <header class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
            <div class="flex min-w-0 items-start gap-3">
                <span v-if="icon" class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-accent/10 text-brand-accent">
                    <component :is="icon" class="h-4 w-4" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-zinc-100">{{ title }}</h3>
                    <p v-if="description" class="mt-0.5 text-xs text-slate-500 dark:text-zinc-400">{{ description }}</p>
                </div>
            </div>
            <slot name="action" />
        </header>
        <div class="relative min-h-[260px] flex-1 px-3 pb-3 pt-2">
            <div v-if="empty" class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-6 text-center">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-zinc-500">
                    <Inbox class="h-5 w-5" aria-hidden="true" />
                </span>
                <p class="text-sm font-semibold text-slate-600 dark:text-zinc-300">Sin datos en este periodo</p>
                <p class="max-w-xs text-xs text-slate-500 dark:text-zinc-400">{{ emptyText ?? 'Prueba con otro periodo o quita algún filtro.' }}</p>
            </div>
            <slot v-else />
        </div>
    </section>
</template>
