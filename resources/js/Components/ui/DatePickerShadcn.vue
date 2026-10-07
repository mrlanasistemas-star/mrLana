<script setup lang="ts">
import { computed, ref, shallowRef, watch } from 'vue'
import { format } from 'date-fns'
import { es } from 'date-fns/locale'
import { Calendar as CalendarIcon } from 'lucide-vue-next'

import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import { Calendar } from '@/Components/ui/calendar'

import { parseDate, type DateValue } from '@internationalized/date'

type Model = string | null | undefined // guardamos YYYY-MM-DD (cero bugs de timezone)

const props = defineProps<{
  modelValue: Model
  label?: string
  placeholder?: string
  disabled?: boolean
  id?: string
  /** Fecha mínima seleccionable (YYYY-MM-DD). Los días anteriores se muestran bloqueados. */
  minValue?: string | null
  /** Fecha máxima seleccionable (YYYY-MM-DD). */
  maxValue?: string | null
  /** Muestra un botón para limpiar la fecha (campos opcionales). */
  clearable?: boolean
  invalid?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: Model): void
}>()

const open = ref(false)
const selected = shallowRef<DateValue | undefined>(undefined)

const toDateValue = (v: Model): DateValue | undefined => {
  if (!v) return undefined
  try {
    return parseDate(v)
  } catch {
    return undefined
  }
}

const min = computed(() => toDateValue(props.minValue))
const max = computed(() => toDateValue(props.maxValue))

watch(
  () => props.modelValue,
  (v) => (selected.value = toDateValue(v)),
  { immediate: true }
)

// display dd/MM/yyyy sin desfase (nunca uses new Date('YYYY-MM-DD') aquí)
const display = computed(() => {
  const d = selected.value
  if (!d) return ''
  const js = new Date(d.year, d.month - 1, d.day) // local date, sin shift
  return format(js, 'dd/MM/yyyy', { locale: es })
})

const isOutOfRange = (v: DateValue) =>
  (min.value !== undefined && v.compare(min.value) < 0) || (max.value !== undefined && v.compare(max.value) > 0)

const onPick = (v: DateValue | DateValue[] | undefined) => {
  if (!v || Array.isArray(v) || isOutOfRange(v)) return
  selected.value = v
  emit('update:modelValue', v.toString()) // 'YYYY-MM-DD'
  open.value = false // cierra al seleccionar
}

const clear = () => {
  selected.value = undefined
  emit('update:modelValue', null)
}
</script>

<template>
  <div class="w-full">
    <label v-if="label" :for="id" class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">
      {{ label }}
    </label>

    <Popover v-model:open="open">
      <div class="relative mt-1">
        <PopoverTrigger as-child>
          <button
            :id="id"
            type="button"
            :disabled="disabled"
            :aria-invalid="invalid ? 'true' : undefined"
            class="min-h-[42px] w-full rounded-2xl border bg-white px-3 py-2 text-left text-sm
                   text-slate-900 shadow-sm transition
                   hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30
                   dark:bg-neutral-950/40 dark:text-neutral-100 dark:hover:bg-white/5
                   disabled:opacity-60 disabled:cursor-not-allowed"
            :class="invalid ? 'border-rose-400 dark:border-rose-500/60' : 'border-slate-200 dark:border-white/10'"
          >
            <span class="flex items-center justify-between gap-2" :class="clearable && display ? 'pr-7' : ''">
              <span :class="display ? 'font-semibold' : 'text-slate-400 dark:text-neutral-500'">
                {{ display || (placeholder ?? 'Selecciona fecha') }}
              </span>
              <CalendarIcon class="h-4 w-4 shrink-0 opacity-70" aria-hidden="true" />
            </span>
          </button>
        </PopoverTrigger>

        <button
          v-if="clearable && display && !disabled"
          type="button"
          class="absolute right-9 top-1/2 -translate-y-1/2 rounded-full px-1.5 text-xs font-bold text-slate-400
                 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30
                 dark:hover:text-neutral-200"
          aria-label="Quitar fecha"
          @click="clear"
        >
          ×
        </button>
      </div>

      <PopoverContent
        align="start"
        class="w-auto p-0 overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-2xl
               dark:border-white/10 dark:bg-neutral-950
               data-[state=open]:animate-in data-[state=closed]:animate-out
               data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0
               data-[state=open]:zoom-in-95 data-[state=closed]:zoom-out-95
               duration-200 motion-reduce:animate-none"
      >
        <Calendar
          :model-value="selected"
          :min-value="min"
          :max-value="max"
          :placeholder="selected ?? min"
          locale="es"
          class="p-3"
          @update:model-value="onPick"
        />
      </PopoverContent>
    </Popover>
  </div>
</template>
