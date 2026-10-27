<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'
import PasswordInput from '@/Components/PasswordInput.vue'
import InputLabel from '@/Components/InputLabel.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { useLogin, type LoginData } from '@/Composables/useLogin'
import { ref, watch } from 'vue'
import { useLoginMotion } from '@/Composables/useLoginMotion'

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

const highlights = [
  'Requisiciones de gasto con flujo de autorización',
  'Pagos y comprobaciones en un solo lugar',
  'Catálogos, proveedores y reportes al día',
]
</script>

<template>
  <GuestLayout>
    <Head title="Iniciar sesión" />

    <template #brand>
      <h1 class="text-3xl font-semibold leading-[1.1] tracking-tight sm:text-4xl lg:text-5xl">ERP Mr. Lana</h1>
      <p class="mt-3 text-base text-white/85 sm:text-lg">Control de gastos y requisiciones</p>
      <ul class="mt-8 hidden space-y-3 text-[15px] text-white/85 lg:block">
        <li v-for="h in highlights" :key="h" class="flex items-start gap-3">
          <span class="mt-1 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/15 ring-1 ring-inset ring-white/25" aria-hidden="true">
            <svg viewBox="0 0 20 20" fill="none" class="h-3 w-3"><path d="M5 10.5l3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
          </span>
          {{ h }}
        </li>
      </ul>
    </template>

    <div ref="cardRef" class="login-card">
      <div class="mb-8">
        <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-zinc-50">Iniciar sesión</h2>
        <p class="mt-1.5 text-sm text-slate-500 dark:text-zinc-400">Ingresa con tu correo y contraseña.</p>
      </div>

      <p
        v-if="status"
        class="mb-5 rounded-xl border border-brand-success/20 bg-brand-success/10 px-3 py-2.5 text-sm font-medium text-brand-success"
        role="status"
      >
        {{ status }}
      </p>

      <form class="space-y-5" @submit.prevent="submit()">
        <div>
          <InputLabel for="email" value="Correo electrónico" />
          <input
            id="email"
            name="email"
            v-model="form.email"
            type="email"
            placeholder="correo@empresa.com"
            autocomplete="username"
            inputmode="email"
            :aria-invalid="errors.email ? 'true' : undefined"
            class="mt-1.5 block min-h-[44px] w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 transition
                   placeholder:text-slate-400 hover:border-slate-300
                   focus:border-brand-primary focus:ring-[3px] focus:ring-brand-primary/20
                   dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500
                   dark:focus:border-brand-primary dark:focus:ring-brand-primary/25"
          />
          <InputError :message="errors.email" />
        </div>

        <div>
          <div class="flex items-baseline justify-between gap-3">
            <InputLabel for="password" value="Contraseña" />
            <Link
              v-if="canResetPassword"
              :href="route('password.request')"
              class="rounded text-[13px] font-medium text-brand-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40"
            >
              ¿Olvidaste tu contraseña?
            </Link>
          </div>
          <div class="mt-1.5">
            <PasswordInput
              v-model="form.password"
              id="password"
              name="password"
              autocomplete="current-password"
            />
          </div>
          <InputError :message="errors.password" />
        </div>

        <label class="inline-flex select-none items-center gap-2.5 text-sm text-slate-700 dark:text-zinc-300">
          <input
            v-model="form.remember"
            type="checkbox"
            class="h-4 w-4 rounded border-slate-300 text-brand-primary focus:ring-brand-primary/40 dark:border-white/20 dark:bg-zinc-900"
          />
          Recordarme
        </label>

        <button
          type="submit"
          :disabled="isSubmitting || !canSubmit"
          class="inline-flex min-h-[46px] w-full items-center justify-center gap-2 rounded-xl bg-brand-button px-4 text-sm font-semibold text-brand-button-fg
                 shadow-md shadow-brand-button/25 transition hover:bg-brand-button/90 active:scale-[0.99]
                 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-950
                 disabled:cursor-not-allowed disabled:opacity-60 motion-reduce:transform-none"
        >
          <template v-if="!isSubmitting">Acceder</template>
          <template v-else>
            <span class="loader" aria-hidden="true"></span>
            Accediendo…
          </template>
        </button>
      </form>
    </div>
  </GuestLayout>
</template>

<style scoped>
/* Aviso de error: sacudida breve (respuesta a la acción) */
.login-card.shake {
  animation: login-shake 0.22s ease-in-out 0s 2;
}
@keyframes login-shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-6px); }
  50% { transform: translateX(6px); }
  75% { transform: translateX(-4px); }
}
@media (prefers-reduced-motion: reduce) {
  .login-card.shake { animation: none; }
}

.loader {
  width: 16px;
  height: 16px;
  border-radius: 999px;
  border: 2px solid currentColor;
  border-top-color: transparent;
  opacity: 0.85;
  display: inline-block;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
