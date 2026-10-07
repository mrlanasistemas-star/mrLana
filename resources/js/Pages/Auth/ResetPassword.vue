<script setup lang="ts">
/**
 * ResetPassword.vue
 * ------------------------------------------------------------
 * - Email oculto (param, NO editable)
 * - form.processing (Breeze/Inertia nativo)
 * - Validaciones UX (usePasswordForm mode: 'reset')
 * - Barra fuerza + checklist
 * - Ojitos
 * - SweetAlert2 confirm + éxito
 * - Full width + responsive (1 col -> 2 col)
 */

import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Swal from 'sweetalert2'

import {
  EyeIcon,
  EyeSlashIcon,
  CheckCircleIcon,
  XCircleIcon,
  ShieldCheckIcon,
  KeyIcon,
  LockClosedIcon,
} from '@heroicons/vue/24/outline'

import { usePasswordForm } from '@/Composables/usePasswordForm'

const props = defineProps<{
  email: string
  token: string
}>()

const passwordInput = ref<HTMLInputElement | null>(null)
const confirmPasswordInput = ref<HTMLInputElement | null>(null)

const showNew = ref(false)
const showConfirm = ref(false)

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

const { clientErrors, checks, progressCopy, strength, validateClient } =
  usePasswordForm(form, { mode: 'reset' })

const baseInput =
  'block w-full pr-11 rounded-xl border text-sm transition duration-200 ' +
  'bg-white text-slate-900 placeholder:text-slate-400 ' +
  'border-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-200 focus:border-slate-300 ' +
  'dark:bg-zinc-900/35 dark:text-zinc-100 dark:placeholder:text-zinc-500 ' +
  'dark:border-zinc-800/70 dark:focus:ring-zinc-800/70 dark:focus:border-zinc-700'

const errorInput =
  'border-red-400 ring-2 ring-red-200 ' +
  'dark:border-red-400/70 dark:ring-red-500/20'

const newInputClass = computed(() => {
  const hasClient = clientErrors.value.some(e => e.toLowerCase().includes('contraseña'))
  const hasServer = !!form.errors.password
  return [baseInput, (hasClient || hasServer) ? errorInput : ''].join(' ')
})

const confirmInputClass = computed(() => {
  const hasClient = clientErrors.value.some(e => e.toLowerCase().includes('confirm'))
  const hasServer = !!form.errors.password_confirmation
  return [baseInput, (hasClient || hasServer) ? errorInput : ''].join(' ')
})

function tapAnim(e: MouseEvent) {
  const el = e.currentTarget as HTMLElement | null
  if (!el) return
  el.classList.add('scale-110')
  setTimeout(() => el.classList.remove('scale-110'), 120)
}

const submit = async () => {
  if (form.processing) return

  const ok = validateClient({
    current: null,
    password: passwordInput.value,
    confirm: confirmPasswordInput.value,
  })
  if (!ok) return

  const isDark = document.documentElement.classList.contains('dark')

  const result = await Swal.fire({
    title: '¿Guardar nueva contraseña?',
    text: 'Este cambio se aplicará inmediatamente.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, guardar',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
    background: isDark ? '#18181b' : '#ffffff',
    color: isDark ? '#e4e4e7' : '#18181b',
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#52525b',
  })

  if (!result.isConfirmed) return

  form.post(route('password.store'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
    onSuccess: () => {
      Swal.fire({
        icon: 'success',
        title: 'Contraseña actualizada',
        text: 'Listo. Ya puedes iniciar sesión con tu nueva contraseña.',
        timer: 2300,
        showConfirmButton: false,
        background: isDark ? '#18181b' : '#ffffff',
        color: isDark ? '#e4e4e7' : '#18181b',
        iconColor: '#22c55e',
      })
    },
    onError: () => {
      if (form.errors.password) {
        form.reset('password', 'password_confirmation')
        passwordInput.value?.focus()
      } else if (form.errors.password_confirmation) {
        form.reset('password_confirmation')
        confirmPasswordInput.value?.focus()
      }
    },
  })
}
</script>

<template>
  <GuestLayout variant="wide">
    <Head title="Restablecer contraseña" />

    <main class="w-full px-4 sm:px-6 lg:px-10 py-8">
      <!-- Headline -->
      <header
        class="w-full rounded-3xl border px-5 py-5 shadow-sm
               border-slate-200 bg-white
               dark:border-zinc-800/70 dark:bg-zinc-900/30"
      >
        <p class="flex items-center gap-3">
          <span
            class="flex h-11 w-11 items-center justify-center rounded-2xl
                   bg-blue-50 text-blue-700
                   dark:bg-blue-500/10 dark:text-blue-200"
            aria-hidden="true"
          >
            <ShieldCheckIcon class="h-6 w-6" />
          </span>

          <span class="min-w-0">
            <span class="block text-lg font-semibold text-slate-900 dark:text-zinc-100">
              Restablecer contraseña
            </span>
            <span class="mt-0.5 block text-sm text-slate-600 dark:text-zinc-300">
              Crea una contraseña fuerte y recupera tu acceso a MR-Lana ERP.
            </span>
          </span>
        </p>

        <p
          class="mt-4 inline-flex w-full flex-wrap items-center gap-2 rounded-2xl border px-4 py-3 text-sm
                 border-slate-200 bg-slate-50 text-slate-700
                 dark:border-zinc-800/70 dark:bg-zinc-900/30 dark:text-zinc-200"
        >
          <KeyIcon class="h-5 w-5" aria-hidden="true" />
          <span class="truncate">
            Cuenta:
            <strong class="font-semibold">{{ props.email }}</strong>
          </span>
          <span
            class="ml-auto inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold
                   bg-slate-900 text-white
                   dark:bg-zinc-300 dark:text-zinc-900"
          >
            Verificada
          </span>
        </p>
      </header>

      <!-- Client errors -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <aside
          v-if="clientErrors.length"
          class="mt-6 w-full rounded-3xl border px-5 py-4 text-sm
                 border-red-200 bg-red-50 text-red-700
                 dark:border-red-400/25 dark:bg-red-500/10 dark:text-red-200"
          role="alert"
        >
          <p class="font-semibold">Antes de guardar, revisa esto:</p>
          <ul class="mt-2 space-y-1">
            <li v-for="(e, i) in clientErrors" :key="i">• {{ e }}</li>
          </ul>
        </aside>
      </Transition>

      <!-- Grid -->
      <section class="mt-6 grid w-full gap-6 lg:grid-cols-12">
        <!-- Form -->
        <article
          class="lg:col-span-7 w-full rounded-3xl border bg-white shadow-sm
                 border-slate-200 dark:border-zinc-800/70 dark:bg-zinc-900/30"
        >
          <form @submit.prevent="submit" class="w-full px-5 py-6 sm:px-6 sm:py-7 space-y-6">
            <!-- Hidden params -->
            <input type="hidden" v-model="form.email" />
            <input type="hidden" v-model="form.token" />

            <!-- Nueva contraseña -->
            <section class="space-y-2">
              <p class="flex items-end justify-between gap-3">
                <InputLabel for="password" value="Nueva contraseña" />
                <span class="text-xs text-slate-500 dark:text-zinc-400">
                  {{ progressCopy.title }}
                </span>
              </p>

              <p class="relative">
                <TextInput
                  id="password"
                  ref="passwordInput"
                  v-model="form.password"
                  :type="showNew ? 'text' : 'password'"
                  :class="newInputClass"
                  :disabled="form.processing"
                  required
                  autocomplete="new-password"
                />

                <button
                  type="button"
                  class="absolute inset-y-0 right-0 flex items-center px-3
                         text-slate-500 transition hover:text-slate-800
                         dark:text-zinc-400 dark:hover:text-zinc-200"
                  :disabled="form.processing"
                  @click="showNew = !showNew"
                  @mousedown="tapAnim"
                  aria-label="Mostrar u ocultar contraseña"
                >
                  <component :is="showNew ? EyeSlashIcon : EyeIcon" class="h-5 w-5" />
                </button>
              </p>

              <p>
                <span class="block h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-zinc-800/60">
                  <span
                    class="block h-2 rounded-full transition-all duration-300 ease-out"
                    :class="strength.barClass"
                    :style="{ width: strength.width }"
                  />
                </span>
              </p>

              <p class="flex items-start justify-between gap-3">
                <span :class="strength.textClass" class="text-xs dark:text-zinc-300">
                  {{ strength.text }}
                </span>
                <span class="text-xs text-slate-500 dark:text-zinc-400">
                  {{ progressCopy.desc }}
                </span>
              </p>

              <InputError :message="form.errors.password" class="mt-2" />
            </section>

            <!-- Confirmar -->
            <section class="space-y-2">
              <InputLabel for="password_confirmation" value="Confirmar contraseña" />

              <p class="relative">
                <TextInput
                  id="password_confirmation"
                  ref="confirmPasswordInput"
                  v-model="form.password_confirmation"
                  :type="showConfirm ? 'text' : 'password'"
                  :class="confirmInputClass"
                  :disabled="form.processing"
                  required
                  autocomplete="new-password"
                />

                <button
                  type="button"
                  class="absolute inset-y-0 right-0 flex items-center px-3
                         text-slate-500 transition hover:text-slate-800
                         dark:text-zinc-400 dark:hover:text-zinc-200"
                  :disabled="form.processing"
                  @click="showConfirm = !showConfirm"
                  @mousedown="tapAnim"
                  aria-label="Mostrar u ocultar confirmación"
                >
                  <component :is="showConfirm ? EyeSlashIcon : EyeIcon" class="h-5 w-5" />
                </button>
              </p>

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
                  class="text-xs"
                  :class="form.password_confirmation === form.password
                    ? 'text-emerald-700 dark:text-emerald-300'
                    : 'text-amber-700 dark:text-amber-300'"
                >
                  {{ form.password_confirmation === form.password ? 'Coinciden correctamente.' : 'Aún no coinciden.' }}
                </p>
              </Transition>

              <InputError :message="form.errors.password_confirmation" class="mt-2" />
            </section>

            <!-- Actions -->
            <footer class="pt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <p class="text-xs text-slate-500 dark:text-zinc-400">
                Consejo: usa una frase + símbolos. Tu yo del futuro no quiere tickets de “olvidé mi contraseña”.
              </p>

              <PrimaryButton
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
                class="w-full sm:w-auto justify-center transition active:scale-[0.98]"
                :aria-busy="form.processing ? 'true' : 'false'"
              >
                {{ form.processing ? 'Guardando…' : 'Guardar nueva contraseña' }}
              </PrimaryButton>
            </footer>

            <!-- After success message (Breeze style) -->
            <Transition
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="opacity-0 translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 translate-y-1"
            >
              <p
                v-if="form.recentlySuccessful"
                class="text-sm text-emerald-700 dark:text-emerald-300"
              >
                Contraseña actualizada correctamente. Ya puedes iniciar sesión.
                <Link :href="route('login')" class="ml-1 underline font-medium">
                  Ir al inicio de sesión
                </Link>
              </p>
            </Transition>
          </form>
        </article>

        <!-- Rules -->
        <aside
          class="lg:col-span-5 w-full rounded-3xl border bg-white shadow-sm
                 border-slate-200 dark:border-zinc-800/70 dark:bg-zinc-900/30"
        >
          <header class="px-5 py-5 sm:px-6 border-b border-slate-200 dark:border-zinc-800/70">
            <p class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-zinc-100">
              <LockClosedIcon class="h-5 w-5" />
              Requisitos de seguridad
            </p>
            <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">
              Completa esta lista y listo: acceso blindado.
            </p>
          </header>

          <ul class="px-5 py-5 sm:px-6 grid gap-2">
            <li
              v-for="(c, idx) in checks"
              :key="idx"
              class="flex items-center gap-2 rounded-2xl border px-4 py-3 text-sm transition
                     border-slate-200 bg-slate-50 text-slate-700
                     dark:border-zinc-800/70 dark:bg-zinc-900/30 dark:text-zinc-200"
              :class="c.ok
                ? 'border-emerald-200 bg-emerald-50/60 text-emerald-800 dark:border-emerald-400/25 dark:bg-emerald-500/10 dark:text-emerald-200'
                : ''"
            >
              <component :is="c.ok ? CheckCircleIcon : XCircleIcon" class="h-5 w-5" />
              <span>{{ c.label }}</span>
            </li>
          </ul>

          <footer class="px-5 pb-6 sm:px-6">
            <p class="text-xs text-slate-500 dark:text-zinc-400">
              Si tú no solicitaste este cambio, contacta a Sistemas de inmediato.
            </p>
          </footer>
        </aside>
      </section>
    </main>
  </GuestLayout>
</template>
