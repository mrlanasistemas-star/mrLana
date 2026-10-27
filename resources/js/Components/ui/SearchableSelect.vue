<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { FocusScope } from 'reka-ui'

type OptionLike = Record<string, any>

const props = defineProps<{
  modelValue: string | number | null
  options: OptionLike[]

  id?: string
  label?: string

  placeholder?: string
  searchPlaceholder?: string
  error?: string | null

  labelKey?: string
  secondaryKey?: string
  valueKey?: string

  allowNull?: boolean
  nullable?: boolean
  nullLabel?: string

  buttonClass?: string
  panelClass?: string
  rounded?: 'xl' | '2xl' | '3xl'
  zIndexClass?: string

  /** NEW */
  compact?: boolean
  maxHeightClass?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string | number | null): void
  (e: 'change', v: string | number | null): void
}>()

const open = ref(false)
const query = ref('')

const rootRef = ref<HTMLElement | null>(null)
const searchRef = ref<HTMLInputElement | null>(null)
const buttonRef = ref<HTMLButtonElement | null>(null)
const panelRef = ref<HTMLElement | null>(null)

const panelStyle = ref<Record<string, string>>({})
const preferUp = ref(false)

const uid = `ss-${Math.random().toString(36).slice(2, 10)}`
const buttonId = computed(() => props.id ?? uid)

const labelKey = computed(() => props.labelKey ?? 'nombre')
const secondaryKey = computed(() => props.secondaryKey ?? 'codigo')
const valueKey = computed(() => props.valueKey ?? 'id')

const allowNullEff = computed(() => {
  if (typeof props.allowNull === 'boolean') return props.allowNull
  if (typeof props.nullable === 'boolean') return props.nullable
  return false
})

const selected = computed<OptionLike | null>(() => {
  const v = props.modelValue
  if (v === null || v === '' || v === undefined) return null
  const vv = String(v)
  return props.options.find((o) => String(o?.[valueKey.value]) === vv) ?? null
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return props.options

  return props.options.filter((o) => {
    const a = String(o?.[labelKey.value] ?? '').toLowerCase()
    const b = String(o?.[secondaryKey.value] ?? '').toLowerCase()
    return a.includes(q) || b.includes(q)
  })
})

function pick(v: string | number | null) {
  emit('update:modelValue', v)
  emit('change', v)
  setOpen(false)
}

function parseMaxHeightPx(cls?: string): number {
  const s = String(cls ?? '').trim()
  // max-h-[420px]
  let m = s.match(/max-h-\[(\d+)px\]/)
  if (m?.[1]) return Number(m[1]) || 320
  // max-h-72 -> 72 * 4px = 288px (escala tailwind típica)
  m = s.match(/max-h-(\d+)/)
  if (m?.[1]) return (Number(m[1]) || 72) * 4
  return 320
}

function updatePosition() {
  if (!open.value) return
  const btn = buttonRef.value
  const panel = panelRef.value
  if (!btn || !panel) return

  const r = btn.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight

  const margin = 10
  const gap = 8

  const width = Math.min(r.width, vw - margin * 2)
  const left = Math.max(margin, Math.min(r.left, vw - width - margin))

  const desiredMaxH = parseMaxHeightPx(props.maxHeightClass)
  const availBelow = Math.max(0, vh - r.bottom - gap - margin)
  const availAbove = Math.max(0, r.top - gap - margin)

  // Decide dirección: abajo por default; arriba si abajo queda muy justo y arriba es mejor.
  const threshold = 220
  const shouldUp = (availBelow < threshold && availAbove > availBelow) || preferUp.value

  const maxH = Math.max(140, Math.min(desiredMaxH, shouldUp ? availAbove : availBelow))
  const topBase = shouldUp ? (r.top - gap) : (r.bottom + gap)

  panelStyle.value = {
    position: 'fixed',
    left: `${left}px`,
    width: `${width}px`,
    top: `${topBase}px`,
    maxHeight: `${maxH}px`,
  }

  // Ajuste fino con altura real (para que no “se vaya” ni se corte)
  requestAnimationFrame(() => {
    if (!open.value || !panelRef.value) return
    const p = panelRef.value.getBoundingClientRect()
    let top = shouldUp ? (r.top - gap - p.height) : (r.bottom + gap)

    // clamp final
    top = Math.max(margin, Math.min(top, vh - p.height - margin))

    panelStyle.value = {
      ...panelStyle.value,
      top: `${top}px`,
    }
  })
}

function setOpen(v: boolean) {
  open.value = v
  if (v) {
    preferUp.value = false
    nextTick(() => {
      updatePosition()
      searchRef.value?.focus()
    })
  } else {
    query.value = ''
  }
}

function toggle() {
  setOpen(!open.value)
}

function openFromLabel() {
  setOpen(true)
  nextTick(() => buttonRef.value?.focus())
}

function onKeydown(e: KeyboardEvent) {
  if (!open.value) return
  if (e.key === 'Escape') {
    // Solo cierra el selector, no el diálogo que lo contiene.
    e.stopPropagation()
    setOpen(false)
  }
}

function onReflow() {
  if (!open.value) return
  updatePosition()
}

onMounted(() => {
  // Captura: el Escape se atiende antes que el del diálogo/modal contenedor.
  document.addEventListener('keydown', onKeydown, true)
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown, true)
  window.removeEventListener('resize', onReflow)
  window.removeEventListener('scroll', onReflow, true)
})

watch(open, (v) => {
  if (v) {
    window.addEventListener('resize', onReflow)
    // capture=true para agarrar scroll de contenedores, no solo window
    window.addEventListener('scroll', onReflow, true)
    nextTick(() => updatePosition())
  } else {
    window.removeEventListener('resize', onReflow)
    window.removeEventListener('scroll', onReflow, true)
  }
})

watch(
  () => props.options,
  () => {
    if (open.value) nextTick(() => updatePosition())
  },
)

const roundedCls = computed(() => {
  if (props.rounded === 'xl') return 'rounded-xl'
  if (props.rounded === '3xl') return 'rounded-3xl'
  return 'rounded-2xl'
})

const compact = computed(() => !!props.compact)

const zCls = computed(() => props.zIndexClass ?? 'z-[9999]')

const baseButton = computed(() => {
  const py = compact.value ? 'py-2.5' : 'py-3'
  return (
    'w-full min-w-0 px-4 ' +
    py +
    ' text-sm text-left border bg-white text-slate-900 ' +
    'hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500/30 ' +
    'dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 dark:hover:bg-white/5 dark:focus:ring-white/10 ' +
    'transition'
  )
})

const panelShell = computed(() => {
  return (
    'pointer-events-auto overflow-hidden border bg-white shadow-2xl ring-1 ring-black/5 ' +
    'dark:border-white/10 dark:bg-neutral-950 dark:ring-white/5 ' +
    'animate-in fade-in-0 zoom-in-95 duration-150'
  )
})

const searchInputCls = computed(() => {
  const py = compact.value ? 'py-2.5' : 'py-3'
  return (
    'w-full rounded-2xl px-4 ' +
    py +
    ' text-sm border border-slate-200 bg-white text-slate-900 ' +
    'placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500/30 ' +
    'dark:border-white/10 dark:bg-neutral-900/60 dark:text-neutral-100 dark:placeholder:text-neutral-500 dark:focus:ring-white/10'
  )
})
</script>

<template>
  <div ref="rootRef" class="min-w-0">
    <label
      v-if="label"
      class="mb-1 block text-xs font-black text-slate-600 dark:text-neutral-300 select-none"
      :for="buttonId"
      @click.prevent="openFromLabel"
    >
      {{ label }}
    </label>

    <button
      ref="buttonRef"
      :id="buttonId"
      type="button"
      @click="toggle"
      class="w-full"
      :class="[roundedCls, baseButton, buttonClass]"
      :aria-expanded="open ? 'true' : 'false'"
      :aria-controls="`${buttonId}-panel`"
    >
      <span class="flex items-center justify-between gap-3 min-w-0">
        <span class="truncate font-semibold min-w-0">
          <template v-if="selected">
            {{ selected[labelKey] }}
            <span v-if="selected[secondaryKey]" class="opacity-70"> ({{ selected[secondaryKey] }})</span>
          </template>
          <template v-else>
            <span class="text-slate-500 dark:text-neutral-400">
              {{ placeholder ?? 'Selecciona...' }}
            </span>
          </template>
        </span>

        <svg
          class="h-4 w-4 opacity-70 shrink-0 transition-transform duration-200"
          :class="open ? 'rotate-180' : ''"
          viewBox="0 0 20 20"
          fill="currentColor"
          aria-hidden="true"
        >
          <path
            fill-rule="evenodd"
            d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
            clip-rule="evenodd"
          />
        </svg>
      </span>
    </button>

    <!--
      Panel flotante (no empuja contenido). Se teletransporta al <body>, así que dentro de un
      diálogo de reka-ui quedaría "fuera" de él y lo cerraría:
      - data-dismissable-layer: el diálogo lo trata como capa anidada (no se cierra al tocarlo).
      - FocusScope: pausa la trampa de foco del diálogo para que el buscador pueda enfocarse.
      - pointer-events-auto: sigue siendo interactivo aunque el diálogo desactive el resto.
    -->
    <Teleport to="body">
      <div v-if="open" class="pointer-events-auto fixed inset-0" :class="zCls" data-dismissable-layer>
        <!-- click-catcher (cierra al tocar fuera) -->
        <div class="absolute inset-0" @mousedown.prevent="setOpen(false)"></div>

        <FocusScope as-child>
        <div
          :id="`${buttonId}-panel`"
          ref="panelRef"
          :style="panelStyle"
          :class="[roundedCls, panelShell, panelClass]"
          @mousedown.stop
          @wheel.passive
        >
          <div class="p-3 border-b border-slate-200/70 dark:border-white/10 bg-white/80 dark:bg-neutral-950/60 backdrop-blur">
            <input
              ref="searchRef"
              v-model="query"
              type="text"
              :placeholder="searchPlaceholder ?? 'Escribe para filtrar...'"
              :class="searchInputCls"
              @keydown.up.prevent="preferUp = true"
              @keydown.down.prevent="preferUp = false"
            />
          </div>

          <div class="p-2 overflow-auto" style="max-height: inherit;">
            <button
              v-if="allowNullEff"
              type="button"
              @click="pick(null)"
              class="w-full text-left px-3 py-2.5 rounded-2xl text-sm font-semibold
                     hover:bg-slate-50 dark:hover:bg-white/5 transition"
            >
              {{ nullLabel ?? 'Sin selección' }}
            </button>

            <button
              v-for="o in filtered"
              :key="o[valueKey] ?? o.id"
              type="button"
              @click="pick(o[valueKey])"
              class="w-full text-left px-3 py-2.5 rounded-2xl text-sm transition
                     hover:bg-slate-50 dark:hover:bg-white/5
                     flex items-center justify-between gap-3"
              :class="String(modelValue) === String(o[valueKey]) ? 'bg-indigo-50 dark:bg-indigo-500/10 font-semibold' : ''"
            >
              <span class="truncate min-w-0">
                {{ o[labelKey] }}
                <span v-if="o[secondaryKey]" class="opacity-70"> ({{ o[secondaryKey] }})</span>
              </span>
              <span
                v-if="String(modelValue) === String(o[valueKey])"
                class="text-[12px] font-black text-indigo-700 dark:text-indigo-200"
              >
                ✓
              </span>
            </button>

            <div v-if="filtered.length === 0" class="px-3 py-3 text-sm text-slate-500 dark:text-neutral-400">
              Sin resultados.
            </div>
          </div>
        </div>
        </FocusScope>
      </div>
    </Teleport>

    <p v-if="error" class="mt-1 text-xs font-bold text-rose-600">{{ error }}</p>
  </div>
</template>
