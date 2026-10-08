<script setup lang="ts">
import { ref } from 'vue'
import { AlertTriangle, CheckSquare, ChevronDown, Info, Lock, ShieldAlert, Square } from 'lucide-vue-next'
import ScopeSelector from './ScopeSelector.vue'
import type { ModuleUi, PermissionItem, ScopeKey } from '../useRoleForm'

/**
 * Permisos de un módulo: alcance de lectura (selector excluyente) y acciones
 * agrupadas (operación, captura, administración, exportación). En móvil es un
 * acordeón; en escritorio siempre está abierto.
 */
const props = defineProps<{
    module: ModuleUi
    groups: ModuleUi['groups']
    showScope: boolean
    scope: ScopeKey
    minScope: ScopeKey
    count: number
    total: number
    warning: boolean
    notice?: string
    locked: boolean
    isSelected: (name: string) => boolean
    defaultOpen?: boolean
}>()

const emit = defineEmits<{
    (e: 'scope', level: ScopeKey): void
    (e: 'toggle', name: string): void
    (e: 'all', on: boolean): void
}>()

const open = ref(props.defaultOpen ?? false)
const contentId = `mod-${props.module.key}`

const requiresLabel = (p: PermissionItem) => p.requires.length ? 'Incluye automáticamente sus requisitos.' : ''
</script>

<template>
    <section
        class="min-w-0 rounded-2xl border bg-white transition-shadow duration-200 hover:shadow-md dark:bg-neutral-900/80"
        :class="warning ? 'border-amber-200/80 dark:border-amber-500/25' : 'border-slate-200/80 dark:border-white/10'"
        :aria-labelledby="`${contentId}-title`"
        :data-tour="`rol-modulo-${module.key}`"
    >
        <!-- Encabezado: en móvil abre/cierra el acordeón -->
        <div class="flex items-start gap-2 p-3 sm:p-4">
            <button
                type="button"
                class="flex min-h-[44px] min-w-0 flex-1 items-start gap-2 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/40 rounded-xl lg:pointer-events-none"
                :aria-expanded="open"
                :aria-controls="contentId"
                @click="open = !open"
            >
                <ChevronDown class="mt-1 h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200 lg:hidden" :class="open ? 'rotate-180' : ''" aria-hidden="true" />
                <span class="min-w-0">
                    <span :id="`${contentId}-title`" class="flex flex-wrap items-center gap-2 text-sm font-bold text-slate-900 dark:text-zinc-100">
                        <span class="break-words">{{ module.label }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums"
                            :class="count > 0 ? 'bg-brand-accent/10 text-brand-accent' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-zinc-400'"
                        >{{ count }}/{{ total }}</span>
                        <ShieldAlert v-if="warning" class="h-4 w-4 text-amber-500" aria-label="Incluye acceso global o acciones sensibles" />
                    </span>
                    <span class="mt-0.5 block break-words text-xs text-slate-500 dark:text-zinc-400">{{ module.description }}</span>
                </span>
            </button>
            <div v-if="!locked" class="flex shrink-0 gap-1">
                <button type="button" class="ui-btn-sm min-h-[36px] px-2" :title="`Alcance global y todas las acciones de ${module.label}`" @click="emit('all', true)">
                    <CheckSquare class="h-3.5 w-3.5" aria-hidden="true" /> <span class="hidden sm:inline">Todos</span><span class="sr-only"> en {{ module.label }}</span>
                </button>
                <button type="button" class="ui-btn-sm min-h-[36px] px-2" :title="`Quitar todo en ${module.label}`" @click="emit('all', false)">
                    <Square class="h-3.5 w-3.5" aria-hidden="true" /> <span class="hidden sm:inline">Ninguno</span><span class="sr-only"> en {{ module.label }}</span>
                </button>
            </div>
        </div>

        <div :id="contentId" class="space-y-4 border-t border-slate-100 px-3 pb-4 pt-3 dark:border-white/[0.06] sm:px-4" :class="open ? 'block' : 'hidden lg:block'">
            <p v-if="notice" class="flex items-start gap-2 rounded-xl bg-sky-50 px-3 py-2 text-xs text-sky-800 dark:bg-sky-500/10 dark:text-sky-200" role="status">
                <Info class="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" /> <span class="min-w-0 break-words">{{ notice }}</span>
            </p>

            <div v-if="showScope && module.scope.length">
                <p class="mb-1.5 text-[11px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Alcance de lectura</p>
                <ScopeSelector
                    :module-key="module.key"
                    :module-label="module.label"
                    :options="module.scope"
                    :model-value="scope"
                    :min="minScope"
                    :disabled="locked"
                    @update:model-value="(v) => emit('scope', v)"
                />
            </div>

            <fieldset v-for="g in groups" :key="g.key" class="min-w-0" :disabled="locked">
                <legend class="mb-1.5 text-[11px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">{{ g.label }}</legend>
                <ul class="grid grid-cols-1 gap-1.5 xl:grid-cols-2">
                    <li v-for="p in g.permissions" :key="p.name" class="min-w-0">
                        <label
                            class="group flex min-h-[44px] items-start gap-3 rounded-xl border px-3 py-2 text-sm transition-all duration-150"
                            :class="[
                                isSelected(p.name)
                                    ? 'border-brand-accent/30 bg-brand-accent/[0.05] dark:border-brand-accent/40 dark:bg-brand-accent/10'
                                    : 'border-transparent hover:border-slate-200 hover:bg-slate-50 dark:hover:border-white/10 dark:hover:bg-white/5',
                                locked ? 'cursor-not-allowed' : 'cursor-pointer',
                            ]"
                        >
                            <input
                                type="checkbox"
                                class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-300 text-brand-accent focus:ring-brand-accent/40 disabled:opacity-60"
                                :checked="isSelected(p.name)"
                                :disabled="locked"
                                @change="emit('toggle', p.name)"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-1.5 font-medium text-slate-800 dark:text-zinc-100">
                                    <span class="break-words">{{ p.label }}</span>
                                    <span
                                        v-if="p.sensitive"
                                        class="inline-flex items-center gap-0.5 rounded-md bg-amber-50 px-1.5 py-px text-[10px] font-bold text-amber-700 ring-1 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20"
                                        title="Permiso sensible: asígnalo solo a personas de confianza"
                                    >
                                        <AlertTriangle class="h-2.5 w-2.5" aria-hidden="true" /> Sensible
                                    </span>
                                </span>
                                <span class="mt-0.5 block break-words text-xs leading-snug text-slate-500 dark:text-zinc-400">
                                    {{ p.description }} <span v-if="requiresLabel(p)" class="text-slate-400">{{ requiresLabel(p) }}</span>
                                </span>
                            </span>
                            <Lock v-if="locked" class="mt-1 h-3.5 w-3.5 shrink-0 text-slate-300" aria-hidden="true" />
                        </label>
                    </li>
                </ul>
            </fieldset>
        </div>
    </section>
</template>
