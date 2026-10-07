<script setup>
/**
 * Profile.vue (Página)
 * ------------------------------------------------------------
 * Objetivo:
 * - Eliminar tabs/botones superiores (no aportan valor)
 * - Usar SOLO acordeón por sección (abrir/cerrar)
 * - Persistir estado abierto/cerrado por sección (localStorage)
 * - Modo oscuro neutro (sin azul, sin negro puro)
 */

import { Head } from '@inertiajs/vue3'
import { onMounted, ref, watch } from 'vue'

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'

import { ChevronDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  mustVerifyEmail: { type: Boolean },
  status: { type: String },
})

/**
 * Persistencia (por sección)
 */
const LS_OPEN_PROFILE = 'profile.open.profileInfo'
const LS_OPEN_PASSWORD = 'profile.open.password'

/**
 * Estados de acordeón
 * - default: ambas abiertas (puedes cambiarlo)
 */
const openProfile = ref(true)
const openPassword = ref(true)

/**
 * Card unificado (neutro)
 */
const cardClass =
  'rounded-2xl border p-5 sm:p-8 shadow-sm ' +
  'border-slate-200 bg-white ' +
  'dark:border-zinc-800/70 dark:bg-zinc-950/40 dark:shadow-none'

/**
 * Header clickeable (look enterprise)
 */
const headerBtnClass =
  'flex w-full items-start justify-between gap-3 text-left ' +
  'rounded-xl px-3 py-2 -mx-3 -my-2 transition ' +
  'hover:bg-slate-50 active:scale-[0.99] ' +
  'dark:hover:bg-zinc-900/30'

/**
 * Helpers
 */
function toggle(section) {
  if (section === 'profile') {
    openProfile.value = !openProfile.value

    // opcional: si abres perfil, cierra password (comportamiento “solo uno abierto”)
    // openPassword.value = false
  }

  if (section === 'password') {
    openPassword.value = !openPassword.value

    // opcional: si abres password, cierra perfil
    // openProfile.value = false
  }
}

function chevronClass(isOpen) {
  return [
    'h-5 w-5 shrink-0 transition-transform duration-200',
    isOpen ? 'rotate-180' : 'rotate-0',
    'text-slate-500 dark:text-zinc-400',
  ].join(' ')
}

/**
 * Cargar estado guardado
 */
onMounted(() => {
  const savedProfile = localStorage.getItem(LS_OPEN_PROFILE)
  const savedPassword = localStorage.getItem(LS_OPEN_PASSWORD)

  openProfile.value = savedProfile ? savedProfile === '1' : true
  openPassword.value = savedPassword ? savedPassword === '1' : true
})

/**
 * Guardar estado al cambiar
 */
watch(openProfile, (v) => localStorage.setItem(LS_OPEN_PROFILE, v ? '1' : '0'))
watch(openPassword, (v) => localStorage.setItem(LS_OPEN_PASSWORD, v ? '1' : '0'))
</script>

<template>
  <Head title="Perfil" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-slate-900 dark:text-zinc-100">
        Perfil
      </h2>
    </template>

    <div class="px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
      <div class="mx-auto w-full max-w-4xl space-y-6">

        <!-- PANEL 1: INFO PERFIL -->
        <section :class="cardClass">
          <button type="button" :class="headerBtnClass" @click="toggle('profile')">
            <div class="min-w-0">
              <h3 class="text-base font-semibold text-slate-900 dark:text-zinc-100">
                Información del perfil
              </h3>
              <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">
                Datos informativos (solo lectura).
              </p>
            </div>

            <ChevronDownIcon :class="chevronClass(openProfile)" />
          </button>

          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
          >
            <div v-show="openProfile" class="mt-6">
              <UpdateProfileInformationForm
                :must-verify-email="props.mustVerifyEmail"
                :status="props.status"
                class="max-w-none"
              />
            </div>
          </Transition>
        </section>

        <!-- PANEL 2: PASSWORD -->
        <section :class="cardClass">
          <button type="button" :class="headerBtnClass" @click="toggle('password')">
            <div class="min-w-0">
              <h3 class="text-base font-semibold text-slate-900 dark:text-zinc-100">
                Actualizar contraseña
              </h3>
              <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">
                Cambia tu contraseña con feedback en tiempo real.
              </p>
            </div>

            <ChevronDownIcon :class="chevronClass(openPassword)" />
          </button>

          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
          >
            <div v-show="openPassword" class="mt-6">
              <UpdatePasswordForm class="max-w-none" />
            </div>
          </Transition>
        </section>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
