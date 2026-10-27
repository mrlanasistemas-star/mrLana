<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'

const root = ref<HTMLElement | null>(null)

const onClickOutside = (e: PointerEvent) => {
  if (!open.value) return
  const el = root.value
  if (!el) return
  if (!el.contains(e.target as Node)) open.value = false
}

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape)
  document.addEventListener('pointerdown', onClickOutside, true)
})

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape)
  document.removeEventListener('pointerdown', onClickOutside, true)
})


const props = defineProps<{
  align?: 'left' | 'right'
  width?: string | number
}>()

const open = ref(false)

const closeOnEscape = (e: KeyboardEvent) => {
  if (open.value && e.key === 'Escape') open.value = false
}

onMounted(() => document.addEventListener('keydown', closeOnEscape))
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape))

const alignmentClasses = computed(() => {
  if (props.align === 'left') return 'origin-top-left left-0'
  return 'origin-top-right right-0'
})

/**
 * Tailwind NO compila clases dinámicas tipo `w-${w}`.
 * Por eso:
 * - Si width coincide con un set conocido => usamos clase Tailwind
 * - Si no => usamos style inline para no romper.
 */
const knownWidths = new Set(['48', '56', '64', '72', '80', '96'])

const widthClass = computed(() => {
  const w = String(props.width ?? '56').trim()

  const map: Record<string, string> = {
    '48': 'w-48',
    '56': 'w-56',
    '64': 'w-64',
    '72': 'w-72',
    '80': 'w-80',
    '96': 'w-96',
  }

  return map[w] ?? ''
})

const widthStyle = computed(() => {
  const w = String(props.width ?? '56').trim()

  // Si es un width Tailwind conocido, no uses inline
  if (knownWidths.has(w)) return undefined

  // Si es número (ej "56"), lo tratamos como rem por compatibilidad: 56 => 14rem (56 * 0.25)
  if (/^\d+$/.test(w)) {
    const rem = Number(w) * 0.25
    return { width: `${rem}rem` }
  }

  // Si ya viene como "20rem", "320px", "fit-content", etc.
  return { width: w }
})
</script>

<template>
  <div ref="root" class="relative">
    <!-- Trigger -->
    <div @click="open = !open">
      <slot name="trigger" />
    </div>

    <!-- Panel -->
    <Transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0 translate-y-1 scale-[0.98]"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition ease-in duration-120"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-1 scale-[0.98]"
    >
      <div
        v-show="open"
        class="absolute z-[20000] mt-2 rounded-2xl border shadow-xl backdrop-blur
               border-slate-200/80 bg-white/95
               dark:border-white/10 dark:bg-zinc-900/95"
        :class="[alignmentClasses, widthClass]"
        :style="widthStyle"
      >
        <div class="py-1">
          <slot name="content" />
        </div>
      </div>
    </Transition>
  </div>
</template>
