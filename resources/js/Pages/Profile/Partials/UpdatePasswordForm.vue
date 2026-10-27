<script setup>
/**
 * UpdatePasswordForm.vue
 * ------------------------------------------------------------
 * - SweetAlert2 
 * - Dark mode neutro (zinc/slate)
 */

import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Swal from 'sweetalert2'

import {
  EyeIcon,
  EyeSlashIcon,
  CheckCircleIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'

import { usePasswordForm } from '@/Composables/usePasswordForm'

/* ------------------------------------------------------------ */
/* Refs */
const currentPasswordInput = ref(null)
const passwordInput = ref(null)
const confirmPasswordInput = ref(null)

/* Ojitos */
const showCurrent = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)

/* Form */
const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

/* Lógica encapsulada */
const { clientErrors, checks, progressCopy, strength, validateClient } =
  usePasswordForm(form)

/* ------------------------------------------------------------ */
/* Inputs */
const baseInput =
  'block w-full pr-11 rounded-lg border text-sm transition duration-200 ' +
  'bg-white text-slate-900 placeholder:text-slate-400 ' +
  'border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary/60 ' +
  'dark:bg-zinc-900/35 dark:text-zinc-100 dark:placeholder:text-zinc-500 ' +
  'dark:border-zinc-800/70 dark:focus:ring-zinc-800/70 dark:focus:border-zinc-700'

const errorInput =
  'border-red-400 ring-2 ring-red-200 ' +
  'dark:border-red-400/70 dark:ring-red-500/20'

const currentInputClass = computed(() => {
  const hasClient = clientErrors.value.includes('La contraseña actual es obligatoria.')
  const hasServer = !!form.errors.current_password
  return [baseInput, (hasClient || hasServer) ? errorInput : ''].join(' ')
})

const newInputClass = computed(() => {
  const hasClient = clientErrors.value.some(e => e.includes('nueva contraseña'))
  const hasServer = !!form.errors.password
  return [baseInput, (hasClient || hasServer) ? errorInput : ''].join(' ')
})

const confirmInputClass = computed(() => {
  const hasClient = clientErrors.value.some(e => e.includes('confirmación'))
  const hasServer = !!form.errors.password_confirmation
  return [baseInput, (hasClient || hasServer) ? errorInput : ''].join(' ')
})

/* ------------------------------------------------------------ */
/* Submit + SweetAlert */
const updatePassword = async () => {
  const ok = validateClient({
    current: currentPasswordInput.value,
    password: passwordInput.value,
    confirm: confirmPasswordInput.value,
  })
  if (!ok) return

  const isDark = document.documentElement.classList.contains('dark')

  const result = await Swal.fire({
    title: '¿Cambiar contraseña?',
    text: 'Asegúrate de recordar tu nueva contraseña.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, cambiar',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
    background: isDark ? '#1C1830' : '#ffffff',
    color: isDark ? '#F1EFF8' : '#1C1830',
    confirmButtonColor: '#22c55e', // verde sobrio
    cancelButtonColor: '#52525b',  // zinc neutro
  })

  if (!result.isConfirmed) return

  form.put(route('password.update'), {
    preserveScroll: true,

    onSuccess: () => {
      form.reset()
      showCurrent.value = false
      showNew.value = false
      showConfirm.value = false

      Swal.fire({
        icon: 'success',
        title: 'Contraseña actualizada',
        text: 'Tu contraseña se guardó correctamente.',
        timer: 2200,
        showConfirmButton: false,
        background: isDark ? '#1C1830' : '#ffffff',
        color: isDark ? '#F1EFF8' : '#1C1830',
        iconColor: '#22c55e',
      })
    },

    onError: () => {
      if (form.errors.password) {
        form.reset('password', 'password_confirmation')
        passwordInput.value?.focus()
      }
      if (form.errors.current_password) {
        form.reset('current_password')
        currentPasswordInput.value?.focus()
      }
    },
  })
}

/* Feedback ojito */
function tapAnim(e) {
  const el = e.currentTarget
  el.classList.add('scale-110')
  setTimeout(() => el.classList.remove('scale-110'), 120)
}
</script>

<template>
  <form @submit.prevent="updatePassword" class="space-y-6">
    <!-- Badge + copy (SIN header duplicado) -->
    <div class="flex items-start justify-between gap-4">
      <p class="text-sm text-slate-600 dark:text-zinc-300">
        Mantén tu cuenta segura. Te damos feedback en tiempo real para que tu contraseña quede sólida.
      </p>

      <span
        class="shrink-0 inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium
               border-slate-200 bg-white text-slate-700
               dark:border-zinc-800/70 dark:bg-zinc-900/30 dark:text-zinc-200"
        :class="progressCopy.badgeClass"
      >
        {{ progressCopy.badge }}
      </span>
    </div>

    <!-- Errores cliente -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div
        v-if="clientErrors.length"
        class="rounded-xl border p-4 text-sm
               border-red-200 bg-red-50 text-red-700
               dark:border-red-400/25 dark:bg-red-500/10 dark:text-red-200"
      >
        <p class="font-medium">Revisa lo siguiente:</p>
        <div class="mt-2 space-y-1">
          <p v-for="(e, i) in clientErrors" :key="i">• {{ e }}</p>
        </div>
      </div>
    </Transition>

    <!-- Contraseña actual -->
    <div>
      <InputLabel for="current_password" value="Contraseña actual" />

      <div class="relative mt-2">
        <TextInput
          id="current_password"
          ref="currentPasswordInput"
          v-model="form.current_password"
          :type="showCurrent ? 'text' : 'password'"
          :class="currentInputClass"
          autocomplete="current-password"
        />

        <button
          type="button"
          class="absolute inset-y-0 right-0 flex items-center px-3
                 text-slate-500 transition hover:text-slate-800
                 dark:text-zinc-400 dark:hover:text-zinc-200"
          @click="showCurrent = !showCurrent"
          @mousedown="tapAnim"
          aria-label="Mostrar/Ocultar contraseña actual"
        >
          <component :is="showCurrent ? EyeSlashIcon : EyeIcon" class="h-5 w-5" />
        </button>
      </div>

      <InputError :message="form.errors.current_password" class="mt-2" />
    </div>

    <!-- Nueva contraseña -->
    <div>
      <div class="flex items-end justify-between gap-3">
        <InputLabel for="password" value="Nueva contraseña" />
        <p class="text-xs text-slate-500 dark:text-zinc-400">
          {{ progressCopy.title }}
        </p>
      </div>

      <div class="relative mt-2">
        <TextInput
          id="password"
          ref="passwordInput"
          v-model="form.password"
          :type="showNew ? 'text' : 'password'"
          :class="newInputClass"
          autocomplete="new-password"
        />

        <button
          type="button"
          class="absolute inset-y-0 right-0 flex items-center px-3
                 text-slate-500 transition hover:text-slate-800
                 dark:text-zinc-400 dark:hover:text-zinc-200"
          @click="showNew = !showNew"
          @mousedown="tapAnim"
          aria-label="Mostrar/Ocultar nueva contraseña"
        >
          <component :is="showNew ? EyeSlashIcon : EyeIcon" class="h-5 w-5" />
        </button>
      </div>

      <!-- Barra fuerza -->
      <div class="mt-3">
        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-zinc-800/60">
          <div
            class="h-2 rounded-full transition-all duration-300 ease-out"
            :class="strength.barClass"
            :style="{ width: strength.width }"
          />
        </div>

        <div class="mt-2 flex items-start justify-between gap-3">
          <p :class="strength.textClass" class="text-xs dark:text-zinc-300">
            {{ strength.text }}
          </p>
          <p class="text-xs text-slate-500 dark:text-zinc-400">
            {{ progressCopy.desc }}
          </p>
        </div>
      </div>

      <!-- Checklist -->
      <div class="mt-3 grid gap-2 sm:grid-cols-2">
        <div
          v-for="(c, idx) in checks"
          :key="idx"
          class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition
                 border-slate-200 bg-slate-50 text-slate-700
                 dark:border-zinc-800/70 dark:bg-zinc-900/30 dark:text-zinc-200"
          :class="c.ok
            ? 'border-emerald-200 bg-emerald-50/60 text-emerald-800 dark:border-emerald-400/25 dark:bg-emerald-500/10 dark:text-emerald-200'
            : ''"
        >
          <component :is="c.ok ? CheckCircleIcon : XCircleIcon" class="h-5 w-5" />
          <span>{{ c.label }}</span>
        </div>
      </div>

      <InputError :message="form.errors.password" class="mt-2" />
    </div>

    <!-- Confirmar contraseña -->
    <div>
      <InputLabel for="password_confirmation" value="Confirmar contraseña" />

      <div class="relative mt-2">
        <TextInput
          id="password_confirmation"
          ref="confirmPasswordInput"
          v-model="form.password_confirmation"
          :type="showConfirm ? 'text' : 'password'"
          :class="confirmInputClass"
          autocomplete="new-password"
        />

        <button
          type="button"
          class="absolute inset-y-0 right-0 flex items-center px-3
                 text-slate-500 transition hover:text-slate-800
                 dark:text-zinc-400 dark:hover:text-zinc-200"
          @click="showConfirm = !showConfirm"
          @mousedown="tapAnim"
          aria-label="Mostrar/Ocultar confirmación de contraseña"
        >
          <component :is="showConfirm ? EyeSlashIcon : EyeIcon" class="h-5 w-5" />
        </button>
      </div>

      <!-- Feedback match -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <p
          v-if="form.password_confirmation"
          class="mt-2 text-xs"
          :class="form.password_confirmation === form.password
            ? 'text-emerald-700 dark:text-emerald-300'
            : 'text-amber-700 dark:text-amber-300'"
        >
          {{ form.password_confirmation === form.password ? 'Coinciden correctamente.' : 'Aún no coinciden.' }}
        </p>
      </Transition>

      <InputError :message="form.errors.password_confirmation" class="mt-2" />
    </div>

    <!-- Acciones -->
    <div class="flex flex-wrap items-center gap-4">
      <PrimaryButton :disabled="form.processing" class="transition active:scale-[0.98]">
        Guardar
      </PrimaryButton>

      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-1"
      >
        <p v-if="form.recentlySuccessful" class="text-sm text-emerald-700 dark:text-emerald-300">
          Contraseña actualizada correctamente.
        </p>
      </Transition>
    </div>
  </form>
</template>
