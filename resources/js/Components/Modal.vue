<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  maxWidth: { type: String, default: '2xl' },
  closeable: { type: Boolean, default: true },
})

const emit = defineEmits(['close'])
const showSlot = ref(props.show)

watch(
  () => props.show,
  (v) => {
        if (v) {
  document.body.style.overflow = 'hidden' // <-- vuelve a hidden
  showSlot.value = true
} else {
  document.body.style.overflow = ''
  setTimeout(() => (showSlot.value = false), 180)
}
  }
)

const close = () => {
  if (props.closeable) emit('close')
}

const closeOnEscape = (e: KeyboardEvent) => {
  if (!props.show) return
  if (e.key === 'Escape') {
    e.preventDefault()
    close()
  }
}

onMounted(() => document.addEventListener('keydown', closeOnEscape))
onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape)
  document.body.style.overflow = ''
})

const maxWidthClass = computed(() => {
  return (
    {
      sm: 'sm:max-w-sm',
      md: 'sm:max-w-md',
      lg: 'sm:max-w-lg',
      xl: 'sm:max-w-xl',
      '2xl': 'sm:max-w-2xl',
      '3xl': 'sm:max-w-3xl',
      '4xl': 'sm:max-w-4xl',
    }[props.maxWidth] || 'sm:max-w-2xl'
  )
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="duration-160 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
  v-if="show"
  class="fixed inset-0 z-[9000] h-[100dvh] overflow-y-auto"
  aria-modal="true"
  role="dialog"
>
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-[#141120]/50 backdrop-blur-[2px]"
          @click="close"
        />

        <!-- Container -->
        <div class="relative z-[9010] px-4 py-6">
        <div class="flex min-h-full items-start justify-center">
          <Transition
            enter-active-class="duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-3 scale-[0.98]"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="duration-160 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-3 scale-[0.98]"
          >
            <div
              class="w-full"
              :class="maxWidthClass"
            >
              <!-- Panel -->
              <div
                class="overflow-hidden rounded-[1.25rem] border border-slate-200/70 shadow-2xl dark:border-white/10
                       bg-white text-slate-900
                       dark:bg-neutral-900 dark:text-neutral-100"
              >
                <!-- brillo suave estilo “premium” -->
                <div class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-100" />

                <div v-if="showSlot" class="relative">
                  <slot />
                </div>
              </div>
            </div>
          </Transition>
        </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
