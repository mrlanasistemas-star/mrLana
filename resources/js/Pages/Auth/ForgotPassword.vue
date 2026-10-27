<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  status: { type: String },
})

const form = useForm({ email: '' })

// UI state
const sending = ref(false)
const done = ref(false)
const progress = ref(0)
let timer = null

const isDark = computed(() => document.documentElement.classList.contains('dark'))

watch(
  () => props.status,
  (v) => {
    if (!v) return
    // Cuando Laravel regresa "passwords.sent"
    sending.value = false
    done.value = true
    progress.value = 100
    if (timer) clearInterval(timer)
  }
)

function startProgress() {
  progress.value = 8
  done.value = false
  if (timer) clearInterval(timer)

  timer = setInterval(() => {
    // Avanza suave hasta 92% mientras el server responde
    if (progress.value < 92) progress.value += Math.max(1, Math.round((92 - progress.value) * 0.08))
  }, 120)
}

const submit = () => {
  done.value = false
  sending.value = true
  startProgress()

  form.post(route('password.email'), {
    preserveScroll: true,
    onSuccess: () => {
      // el watch(props.status) finaliza el estado
    },
    onError: () => {
      sending.value = false
      if (timer) clearInterval(timer)
      progress.value = 0
    },
    onFinish: () => {
      // si por alguna razón no llega status pero terminó, cortamos
      if (!props.status && sending.value) {
        sending.value = false
        if (timer) clearInterval(timer)
      }
    },
  })
}
</script>

<template>
  <GuestLayout>
    <Head title="Olvidé la contraseña" />

    <div class="mb-4 text-sm text-slate-600 dark:text-zinc-300">
      <p class="text-justify">
        ¿Olvidaste tu contraseña? No hay problema. Ingresa tu dirección de correo electrónico
        y te enviaremos un enlace de restablecimiento.
      </p>
    </div>

    <!-- Barra de progreso -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div v-if="sending || done" class="mb-4">
        <div class="flex items-center justify-between text-xs mb-2"
             :class="isDark ? 'text-zinc-300' : 'text-slate-600'">
          <span>{{ done ? 'Correo enviado' : 'Enviando correo…' }}</span>
          <span>{{ progress }}%</span>
        </div>

        <div class="h-2 w-full overflow-hidden rounded-full"
             :class="isDark ? 'bg-zinc-800/60' : 'bg-slate-200'">
          <div
            class="h-2 rounded-full transition-all duration-300 ease-out"
            :class="done ? 'bg-emerald-500' : 'bg-brand-primary'"
            :style="{ width: `${progress}%` }"
          />
        </div>

        <p v-if="done" class="mt-3 text-sm text-emerald-700 dark:text-emerald-300">
          Listo. Revisa tu bandeja de entrada (y spam). Si no te llega en 2 minutos, vuelve a intentarlo.
        </p>
      </div>
    </Transition>

    <!-- Status bonito (evita mostrar "passwords.sent") -->
    <div
      v-if="props.status && props.status !== 'passwords.sent'"
      class="mb-4 text-sm font-medium text-emerald-700 dark:text-emerald-300"
    >
      {{ props.status }}
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <InputLabel for="email" value="Correo electrónico:" />

        <TextInput
          id="email"
          type="email"
          class="mt-1 block w-full"
          v-model="form.email"
          required
          autofocus
          autocomplete="username"
          :disabled="sending"
        />

        <InputError class="mt-2" :message="form.errors.email" />
      </div>

      <div class="flex items-center justify-end">
        <PrimaryButton
          :class="{ 'opacity-25': form.processing }"
          :disabled="form.processing || sending"
        >
          {{ sending ? 'Enviando…' : 'Enviar enlace de restablecimiento' }}
        </PrimaryButton>
      </div>
    </form>
  </GuestLayout>
</template>
