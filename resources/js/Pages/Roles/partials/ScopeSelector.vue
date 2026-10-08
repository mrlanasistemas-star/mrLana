<script setup lang="ts">
import { computed } from 'vue'
import { Ban, Building2, Globe2, MapPin, UserRound } from 'lucide-vue-next'
import type { ScopeKey, ScopeOption } from '../useRoleForm'

/**
 * Selector de alcance de lectura (control segmentado tipo radio).
 * Los niveles son excluyentes: elegir uno superior incluye los inferiores.
 */
const props = defineProps<{
    moduleKey: string
    moduleLabel: string
    options: ScopeOption[]
    modelValue: ScopeKey
    disabled?: boolean
    /** Nivel mínimo obligatorio (p. ej. al recibir notificaciones). */
    min?: ScopeKey
}>()
const emit = defineEmits<{ (e: 'update:modelValue', v: ScopeKey): void }>()

const LEVELS: ScopeKey[] = ['none', 'own', 'sucursal', 'corporativo', 'global']
const icons = { none: Ban, own: UserRound, sucursal: MapPin, corporativo: Building2, global: Globe2 } as const
const short = { none: 'Sin acceso', own: 'Propio', sucursal: 'Sucursal', corporativo: 'Corporativo', global: 'Global' } as const

const items = computed(() => {
    // Un módulo con un solo nivel (p. ej. Roles) se muestra como "Sin acceso / Con acceso".
    const single = props.options.length === 1
    return [
        { level: 'none' as ScopeKey, label: short.none, description: 'No ve registros de este módulo.' },
        ...props.options.map((o) => ({ level: o.level as ScopeKey, label: single ? 'Con acceso' : short[o.level], description: o.label + ' — ' + o.description })),
    ]
})

// Clases completas para que Tailwind las genere.
const gridCols: Record<number, string> = { 2: 'grid-cols-2', 3: 'grid-cols-3', 4: 'grid-cols-2 sm:grid-cols-4', 5: 'grid-cols-3 sm:grid-cols-5' }

const isBelowMin =(level: ScopeKey) => LEVELS.indexOf(level) < LEVELS.indexOf(props.min ?? 'none')
const current = computed(() => items.value.find((i) => i.level === props.modelValue))

function onKey(e: KeyboardEvent, index: number) {
    const dir = e.key === 'ArrowRight' || e.key === 'ArrowDown' ? 1 : e.key === 'ArrowLeft' || e.key === 'ArrowUp' ? -1 : 0
    if (!dir) return
    e.preventDefault()
    const list = items.value
    for (let i = index + dir; i >= 0 && i < list.length; i += dir) {
        if (!isBelowMin(list[i]!.level)) {
            emit('update:modelValue', list[i]!.level)
            ;((e.currentTarget as HTMLElement).parentElement?.children[i] as HTMLElement | undefined)?.focus()
            return
        }
    }
}
</script>

<template>
    <div class="min-w-0">
        <div
            role="radiogroup"
            :aria-label="`Alcance de lectura en ${moduleLabel}`"
            class="grid gap-1 rounded-2xl bg-slate-100/80 p-1 dark:bg-white/[0.04]"
            :class="gridCols[items.length] ?? 'grid-cols-3 sm:grid-cols-5'"
        >
            <button
                v-for="(it, i) in items"
                :key="it.level"
                type="button"
                role="radio"
                :aria-checked="modelValue === it.level"
                :tabindex="modelValue === it.level ? 0 : -1"
                :disabled="disabled || isBelowMin(it.level)"
                :title="it.description"
                class="group flex min-h-[40px] min-w-0 items-center justify-center gap-1.5 rounded-xl px-2 text-xs font-semibold transition-all duration-150
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 disabled:cursor-not-allowed disabled:opacity-50"
                :class="modelValue === it.level
                    ? (it.level === 'global' && options.length > 1
                        ? 'bg-amber-500 text-white shadow-sm'
                        : it.level === 'none' ? 'bg-white text-slate-700 shadow-sm dark:bg-zinc-800 dark:text-zinc-100' : 'bg-brand-accent text-white shadow-sm')
                    : 'text-slate-600 hover:bg-white hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-zinc-100'"
                @click="emit('update:modelValue', it.level)"
                @keydown="onKey($event, i)"
            >
                <component :is="icons[it.level]" class="h-3.5 w-3.5 shrink-0 transition-transform group-hover:scale-110 motion-reduce:transform-none" aria-hidden="true" />
                <span class="truncate">{{ it.label }}</span>
            </button>
        </div>
        <p class="mt-1.5 min-h-[1rem] break-words text-[11px] leading-snug text-slate-500 dark:text-zinc-400" aria-live="polite">
            {{ current?.description }}
        </p>
    </div>
</template>
