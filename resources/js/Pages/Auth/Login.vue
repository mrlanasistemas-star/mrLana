<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'
import PasswordInput from '@/Components/PasswordInput.vue'
import InputLabel from '@/Components/InputLabel.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { useLogin, type LoginData } from '@/Composables/useLogin'
import { ref, watch } from 'vue'
import { useLoginMotion } from '@/Composables/useLoginMotion'
import logoMr from '@/img/favicon-mr-lana-16.ico'
import ovejaImg from '@/img/oveja.png'
import logoLogin from '@/img/logo-mr-lana.png'

defineProps({
  canResetPassword: { type: Boolean, default: false },
  status: { type: String, default: '' },
})

const form = useForm<LoginData>({
  email: '',
  password: '',
  remember: false,
})

const { submit, errors, isSubmitting, canSubmit } = useLogin(form)

const cardRef = ref<HTMLElement | null>(null)
const { pulseOnError } = useLoginMotion()

watch(
  () => [errors.value.email, errors.value.password],
  ([e1, e2]) => {
    if ((e1 || e2) && cardRef.value) pulseOnError(cardRef.value)
  }
)
</script>

<template>
  <GuestLayout>
    <Head title="Iniciar sesión" />

    <div class="login-bg">
      <div class="login-stack">
        <img class="login-logo" :src="logoLogin" alt="ERP Mr. Lana" draggable="false" />
        <form
          @submit.prevent="submit()"
          class="relative w-full max-w-[22rem] sm:max-w-[24rem] md:max-w-md lg:max-w-md xl:max-w-lg 2xl:max-w-xl"
        >
          <div
            ref="cardRef"
            class="rounded-2xl p-8 shadow-2xl backdrop-blur bg-white/90 dark:bg-neutral-900/85"
          >
            <div class="flex flex-col items-center mb-6 text-center">
              <img :src="logoMr" class="h-9 w-9 drop-shadow" alt="" />
              <h1 class="mt-3 text-lg font-semibold text-slate-900 dark:text-neutral-100">ERP Mr. Lana</h1>
              <p class="text-sm text-slate-500 dark:text-neutral-400">Control de gastos y requisiciones</p>
            </div>

            <div class="mb-4">
              <InputLabel value="Correo electrónico" class="text-slate-700 dark:text-neutral-300" />
              <input
                id="email"
                name="email"
                v-model="form.email"
                type="email"
                placeholder="correo@empresa.com"
                autocomplete="username"
                inputmode="email"
                class="w-full mt-1 px-3 py-2 rounded-lg text-sm transition
                       bg-white text-slate-900 border border-slate-300
                       placeholder:text-slate-400
                       focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                       dark:bg-neutral-900 dark:text-slate-100
                       dark:border-neutral-700
                       dark:placeholder:text-neutral-400
                       dark:focus:ring-indigo-400 dark:focus:border-indigo-400"
              />
              <InputError :message="errors.email" />
            </div>

            <div class="mb-4">
              <InputLabel value="Contraseña" class="text-slate-700 dark:text-neutral-300" />
              <PasswordInput
                v-model="form.password"
                id="password"
                name="password"
                autocomplete="current-password"
              />
              <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between mt-2">
              <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-neutral-300 select-none">
                <input
                  v-model="form.remember"
                  type="checkbox"
                  class="rounded border-slate-300 dark:border-neutral-700"
                />
                Recordarme
              </label>

              <Link
                v-if="canResetPassword"
                :href="route('password.request')"
                class="text-sm text-indigo-700 hover:underline dark:text-indigo-300
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded"
              >
                ¿Olvidaste tu contraseña?
              </Link>
            </div>

            <button
              type="submit"
              :disabled="isSubmitting || !canSubmit"
              class="group w-full mt-6 py-2.5 rounded-lg font-medium transition
                     bg-indigo-600 text-white hover:bg-indigo-700
                     disabled:opacity-60 disabled:cursor-not-allowed
                     active:scale-[0.99]"
            >
              <span v-if="!isSubmitting" class="inline-flex items-center justify-center gap-2">
                Acceder
                <span class="opacity-0 group-hover:opacity-100 transition">→</span>
              </span>

              <span v-else class="inline-flex items-center justify-center gap-2">
                <span class="loader"></span>
                Accediendo...
              </span>
            </button>
          </div>
        </form>

        <!-- Solo móvil -->
        <img class="login-oveja" :src="ovejaImg" alt="" draggable="false" />
      </div>
    </div>
  </GuestLayout>
</template>

<style scoped>
:global(html, body, #app) {
  width: 100%;
  height: 100%;
  margin: 0;
  padding: 0;
}

/* Fondo full-screen */
.login-bg {
  position: fixed;
  inset: 0;
  width: 100vw;
  height: 100dvh; /* alto real en móvil */
  overflow: hidden;
  background-repeat: no-repeat;
  background-position: center;
  /* MÓVIL: debe llenar TODO el alto (sin bordes) */
  background-image: url('@/img/BgMovil.jpg');
  background-size: cover; /* llena height/width */
  background-position: center;
}

/* solo móvil: logo arriba de la card */
.login-logo{
  width: min(220px, 70vw);
  height: auto;
  display: block;
  margin-bottom: 15px;
  user-select: none;
  pointer-events: none;
}

@media (min-width: 530px) {
  .login-logo { display: none; }
}

/* Bajar la card en móvil */
.login-stack {
  width: 100%;
  height: 100%;
  display: grid;
  justify-items: center;
  align-content: start;  /* en vez de centrar */
  padding-top: 5vh;     /* baja la card */
  gap: 10px;
}

/* oveja más pequeña */
.login-oveja {
  width: min(220px, 62vw);
  height: auto;
  display: block;
  user-select: none;
  pointer-events: none;
}

/* >=530px: cover con recorte lateral permitido; foco a la izquierda para no cortar oveja del fondo */
@media (min-width: 530px) {
  .login-bg {
    background-image: url('@/img/login_background.jpeg');
    background-size: cover;
    background-position: left center;
  }

  .login-oveja {
    display: none;
  }

  .login-stack {
    padding-top: 0;
    align-content: center; /* vuelve a centrar */
    gap: 0;
  }
}

/* loader */
.loader {
  width: 16px;
  height: 16px;
  border-radius: 999px;
  border: 2px solid rgba(255, 255, 255, 0.35);
  border-top-color: rgba(255, 255, 255, 0.95);
  display: inline-block;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
