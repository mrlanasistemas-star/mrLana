<script setup lang="ts">
/**
 * ======================================================
 * PasswordInput.vue
 * Input de contraseña reutilizable
 * - Soporta Light / Dark mode correctamente
 * - Toggle mostrar / ocultar
 * - Compatible con v-model
 * ======================================================
 */

import { computed, ref } from 'vue'

const props = defineProps<{
  modelValue: string
  placeholder?: string
  autocomplete?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const show = ref(false)

const type = computed(() => (show.value ? 'text' : 'password'))

function onInput(e: Event) {
  emit('update:modelValue', (e.target as HTMLInputElement).value)
}
</script>

<template>
  <div class="relative flex items-center">
    <!-- Input -->
    <input
      :type="type"
      :value="modelValue"
      @input="onInput"
      required
      :autocomplete="autocomplete ?? 'current-password'"
      :placeholder="placeholder ?? '••••••••'"
      class="
        block w-full min-h-[44px] px-3 py-2 pr-11 rounded-xl text-sm transition
        bg-white text-slate-900 border border-slate-200
        placeholder:text-slate-400
        focus:ring-[3px] focus:ring-brand-primary/20 focus:border-brand-primary

        dark:bg-zinc-950/60 dark:text-zinc-100 dark:border-white/10
        dark:placeholder:text-neutral-400
        dark:focus:ring-brand-primary/25 dark:focus:border-brand-primary
      "
    />

    <!-- Botón ojo -->
    <button
      type="button"
      class="
        absolute right-0 mr-3
        text-slate-400 hover:text-slate-600
        dark:text-neutral-400 dark:hover:text-neutral-200
        transition
      "
      aria-label="Mostrar u ocultar contraseña"
      :aria-pressed="show ? 'true' : 'false'"
      @click="show = !show"
    >
      <!-- Ojo abierto -->
      <svg
        v-if="!show"
        class="w-5 h-5"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
      >
        <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7
                 -1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        <circle cx="12" cy="12" r="3"/>
      </svg>

      <!-- Ojo cerrado -->
      <svg
        v-else
        class="w-5 h-5"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
      >
        <path d="M3 3l18 18"/>
        <path d="M10.58 10.58A3 3 0 0012 15a3 3 0 002.42-4.42"/>
        <path d="M9.88 5.09A10.94 10.94 0 0112 5
                 c4.48 0 8.27 2.94 9.54 7"/>
      </svg>
    </button>
  </div>
</template>
