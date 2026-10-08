<script setup lang="ts">
import { DialogRoot, DialogTitle, DialogDescription } from 'reka-ui'
import { AlertTriangle, Globe2, Layers, Loader2, MinusCircle, PlusCircle, Save, Users } from 'lucide-vue-next'
import DialogContent from '@/Components/ui/dialog/DialogContent.vue'

/**
 * Resumen previo al guardado: módulos visibles, alcances globales, acciones
 * sensibles, usuarios afectados y —si el rol tiene usuarios— exactamente qué
 * perderán y qué ganarán.
 */
defineProps<{
    open: boolean
    loading: boolean
    isEdit: boolean
    roleName: string
    usersCount: number
    summary: {
        modules: { label: string; scope: string | null; actions: number }[]
        globals: { module: string; label: string }[]
        sensitive: { module: string; label: string }[]
    }
    removed: { name: string; label: string; module: string }[]
    added: { name: string; label: string; module: string }[]
}>()
const emit = defineEmits<{ (e: 'update:open', v: boolean): void; (e: 'confirm'): void }>()
</script>

<template>
    <DialogRoot :open="open" @update:open="(v) => !loading && emit('update:open', v)">
        <DialogContent class="max-w-2xl gap-0 p-0 sm:p-0">
            <div class="border-b border-slate-100 p-5 pr-12 dark:border-white/10">
                <DialogTitle class="text-base font-bold text-slate-900 dark:text-zinc-100">Resumen antes de guardar</DialogTitle>
                <DialogDescription class="mt-1 break-words text-sm text-slate-600 dark:text-zinc-400">
                    Revisa el alcance de «{{ roleName || 'nuevo rol' }}». El cambio aplica de inmediato a sus usuarios.
                </DialogDescription>
            </div>

            <div class="max-h-[60dvh] space-y-5 overflow-y-auto overscroll-contain p-5">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 p-3 dark:border-white/10">
                        <p class="flex items-center gap-1.5 text-xs text-slate-500"><Layers class="h-3.5 w-3.5" aria-hidden="true" /> Módulos visibles</p>
                        <p class="mt-1 text-2xl font-black tabular-nums">{{ summary.modules.length }}</p>
                    </div>
                    <div class="rounded-2xl border p-3" :class="summary.globals.length ? 'border-amber-200 bg-amber-50/60 dark:border-amber-500/25 dark:bg-amber-500/5' : 'border-slate-200 dark:border-white/10'">
                        <p class="flex items-center gap-1.5 text-xs text-slate-500"><Globe2 class="h-3.5 w-3.5" aria-hidden="true" /> Alcances globales</p>
                        <p class="mt-1 text-2xl font-black tabular-nums">{{ summary.globals.length }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-3 dark:border-white/10">
                        <p class="flex items-center gap-1.5 text-xs text-slate-500"><Users class="h-3.5 w-3.5" aria-hidden="true" /> Usuarios afectados</p>
                        <p class="mt-1 text-2xl font-black tabular-nums">{{ usersCount }}</p>
                    </div>
                </div>

                <section v-if="summary.modules.length">
                    <h4 class="mb-2 text-xs font-black uppercase tracking-widest text-slate-400">Módulos</h4>
                    <ul class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                        <li v-for="m in summary.modules" :key="m.label" class="min-w-0 rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-white/5">
                            <span class="block break-words font-semibold">{{ m.label }}</span>
                            <span class="block break-words text-xs text-slate-500 dark:text-zinc-400">
                                {{ m.scope ?? 'Sin lectura' }}<template v-if="m.actions"> · {{ m.actions }} acción(es)</template>
                            </span>
                        </li>
                    </ul>
                </section>
                <p v-else class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-white/5 dark:text-zinc-300">El rol no tendrá acceso a ningún módulo.</p>

                <section v-if="summary.globals.length">
                    <h4 class="mb-2 flex items-center gap-1.5 text-xs font-black uppercase tracking-widest text-amber-600"><Globe2 class="h-3.5 w-3.5" aria-hidden="true" /> Acceso global</h4>
                    <ul class="flex flex-wrap gap-1.5">
                        <li v-for="g in summary.globals" :key="g.label" class="max-w-full break-words rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">{{ g.label }}</li>
                    </ul>
                </section>

                <section v-if="summary.sensitive.length">
                    <h4 class="mb-2 flex items-center gap-1.5 text-xs font-black uppercase tracking-widest text-amber-600"><AlertTriangle class="h-3.5 w-3.5" aria-hidden="true" /> Acciones sensibles</h4>
                    <ul class="flex flex-wrap gap-1.5">
                        <li v-for="s in summary.sensitive" :key="s.module + s.label" class="max-w-full break-words rounded-full border border-amber-200 px-2.5 py-1 text-xs text-amber-800 dark:border-amber-500/30 dark:text-amber-200">
                            {{ s.label }}
                        </li>
                    </ul>
                </section>

                <section v-if="isEdit && removed.length" class="rounded-2xl border border-rose-200 bg-rose-50/60 p-3 dark:border-rose-500/25 dark:bg-rose-500/5">
                    <h4 class="mb-2 flex items-center gap-1.5 text-sm font-bold text-rose-700 dark:text-rose-300">
                        <MinusCircle class="h-4 w-4" aria-hidden="true" />
                        {{ usersCount }} usuario(s) perderán {{ removed.length }} permiso(s)
                    </h4>
                    <ul class="space-y-1 text-sm">
                        <li v-for="r in removed" :key="r.name" class="break-words text-rose-800 dark:text-rose-200"><span class="text-rose-500">{{ r.module }} ·</span> {{ r.label }}</li>
                    </ul>
                </section>

                <section v-if="isEdit && added.length" class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-3 dark:border-emerald-500/25 dark:bg-emerald-500/5">
                    <h4 class="mb-2 flex items-center gap-1.5 text-sm font-bold text-emerald-700 dark:text-emerald-300">
                        <PlusCircle class="h-4 w-4" aria-hidden="true" /> Permisos nuevos ({{ added.length }})
                    </h4>
                    <ul class="space-y-1 text-sm">
                        <li v-for="a in added" :key="a.name" class="break-words text-emerald-800 dark:text-emerald-200"><span class="text-emerald-500">{{ a.module }} ·</span> {{ a.label }}</li>
                    </ul>
                </section>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 p-4 dark:border-white/10 sm:flex-row sm:justify-end">
                <button type="button" class="ui-btn-secondary" :disabled="loading" @click="emit('update:open', false)">Seguir editando</button>
                <button type="button" class="ui-btn-primary" :class="isEdit && removed.length ? '!bg-brand-danger !text-brand-danger-fg' : ''" :disabled="loading" @click="emit('confirm')">
                    <Loader2 v-if="loading" class="h-4 w-4 animate-spin" aria-hidden="true" />
                    <Save v-else class="h-4 w-4" aria-hidden="true" />
                    {{ isEdit ? 'Confirmar y guardar' : 'Registrar rol' }}
                </button>
            </div>
        </DialogContent>
    </DialogRoot>
</template>
