/**
 * ======================================================
 * useLogin.ts
 * Login robusto (Laravel + Inertia + Vue)
 * - Validación UX mínima
 * - Submit seguro
 * - Normaliza auth.failed (sin tocar traducciones de Laravel)
 * - Maneja estados típicos (419/429/500)
 * ======================================================
 */

import { computed, ref } from 'vue'
import type { InertiaForm } from '@inertiajs/vue3'
import { swalErr, swalLoading, swalClose } from '@/lib/swal'

export interface LoginData {
  email: string
  password: string
  remember: boolean
}

type FieldErrors = Record<string, string>

function isEmail(v: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((v ?? '').trim())
}

/**
 * Validación UX mínima para login:
 * - requerido + formato email
 * (NO reglas de fortaleza aquí)
 */
function validateLogin(data: Pick<LoginData, 'email' | 'password'>): FieldErrors {
  const errors: FieldErrors = {}
  const email = (data.email ?? '').trim()
  const password = data.password ?? ''

  if (!email) errors.email = 'El correo es obligatorio.'
  else if (!isEmail(email)) errors.email = 'Escribe un correo válido.'

  if (!password) errors.password = 'La contraseña es obligatoria.'

  return errors
}

/**
 * Traduce / normaliza mensajes de backend sin depender del idioma.
 */
function friendlyAuthMessage(raw: unknown): string {
  const s = String(raw ?? '').trim()

  if (!s) return 'No se pudo iniciar sesión. Intenta de nuevo.'

  // Clave de traducción cruda
  if (s === 'auth.failed' || s.toLowerCase() === 'auth.failed') {
    return 'Correo o contraseña incorrectos.'
  }

  // Default común en inglés
  if (/credentials do not match/i.test(s)) {
    return 'Correo o contraseña incorrectos.'
  }

  // Throttle típico
  if (s.includes('auth.throttle') || /too many login attempts/i.test(s)) {
    return 'Demasiados intentos. Espera un momento e inténtalo de nuevo.'
  }

  // Si el backend manda algo útil, lo pasamos tal cual
  return s
}

/**
 * Mensajes por status HTTP comunes.
 */
function friendlyHttpStatus(status?: number): string | null {
  if (!status) return null
  if (status === 419) return 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.'
  if (status === 429) return 'Demasiados intentos. Espera un momento e inténtalo de nuevo.'
  if (status >= 500) return 'El servidor tuvo un problema. Intenta de nuevo en unos segundos.'
  return null
}

export function useLogin(form: InertiaForm<LoginData>) {
  const isSubmitting = ref(false)
  const attempted = ref(false)

  const clientErrors = computed(() =>
    validateLogin({
      email: form.email,
      password: form.password,
    })
  )

  const canSubmit = computed(() => !clientErrors.value.email && !clientErrors.value.password)

  /**
   * Errores inline:
   * - Preferimos UX si intentó enviar
   * - Si backend manda auth.failed lo normalizamos
   */
  const errors = computed(() => {
    const backendEmail = friendlyAuthMessage((form.errors as any)?.email)
    const backendPass = String((form.errors as any)?.password ?? '')

    return {
      email:
        // si backend trajo algo
        ((form.errors as any)?.email ? backendEmail : '') ||
        // si ya intentó, muestra UX
        (attempted.value ? clientErrors.value.email : '') ||
        '',
      password:
        backendPass ||
        (attempted.value ? clientErrors.value.password : '') ||
        '',
    }
  })

  function submit() {
    attempted.value = true

    // UX: no mandes request si falta algo
    if (!canSubmit.value) return
    if (isSubmitting.value) return

    isSubmitting.value = true
    swalLoading('Validando acceso...')

    form.clearErrors()

    form.post(route('login'), {
      preserveScroll: true,

      onError: (backendErrors: Record<string, string>) => {
        // 1) Si hay status HTTP especial, lo priorizamos
        const statusMsg = friendlyHttpStatus((backendErrors as any)?.status)
        if (statusMsg) {
          swalErr(statusMsg)
          return
        }

        // 2) Intentamos sacar un mensaje de donde sea
        const raw =
          (backendErrors as any)?.email ||
          (backendErrors as any)?.message ||
          (backendErrors as any)?.error ||
          ''

        swalErr(friendlyAuthMessage(raw))
      },

      onFinish: () => {
        swalClose()
        isSubmitting.value = false
        form.reset('password')
      },
    })
  }

  return {
    attempted,
    isSubmitting,
    canSubmit,
    errors,
    submit,
  }
}
