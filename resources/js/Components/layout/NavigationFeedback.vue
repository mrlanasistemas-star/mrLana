<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

/**
 * Avisos globales de navegación:
 * - Carga: si una consulta tarda, muestra "Cargando…" y, si se alarga,
 *   explica que puede tardar con muchos registros.
 * - Errores: cuando el servidor falla o no hay conexión, dice qué pasó y
 *   qué hacer, en lugar de dejar la pantalla sin respuesta.
 */
/** Lo que usamos de los eventos globales del router de Inertia. */
type GlobalEvent<_N extends string> = {
    preventDefault: () => void
    detail: { visit: { method: string; prefetch?: boolean }; response?: { status?: number } }
}

const loading = ref(false)
const slow = ref(false)

let showTimer: ReturnType<typeof setTimeout> | undefined
let slowTimer: ReturnType<typeof setTimeout> | undefined
let active = 0
const off: Array<() => void> = []

const clearTimers = () => {
    clearTimeout(showTimer)
    clearTimeout(slowTimer)
}

const stopLoading = () => {
    clearTimers()
    loading.value = false
    slow.value = false
}

const theme = () => {
    const dark = document.documentElement.classList.contains('dark')
    return { background: dark ? '#1C1830' : '#ffffff', color: dark ? '#F1EFF8' : '#1C1830' }
}

/** Mensaje claro para cada tipo de error del servidor. */
const messageFor = (status: number) => {
    if (status === 419) return { title: 'Tu sesión expiró', text: 'Recarga la página para continuar. Si estabas capturando algo, vuelve a intentarlo después de recargar.', reload: true }
    if (status === 401) return { title: 'Tu sesión terminó', text: 'Vuelve a iniciar sesión para continuar.', reload: true }
    if (status === 403) return { title: 'No tienes permiso', text: 'Tu rol no permite hacer esto. Si lo necesitas, pide acceso a un administrador.', reload: false }
    if (status === 404) return { title: 'No se encontró', text: 'Lo que buscas ya no existe o cambió de lugar.', reload: false }
    if (status === 413) return { title: 'El archivo es demasiado grande', text: 'Sube un archivo más ligero e inténtalo de nuevo.', reload: false }
    if (status === 429) return { title: 'Demasiadas solicitudes', text: 'Espera unos segundos y vuelve a intentarlo.', reload: false }
    if (status === 503) return { title: 'El sistema está en mantenimiento', text: 'Vuelve a intentarlo en unos minutos.', reload: true }
    if (status === 504 || status === 408) return { title: 'La consulta tardó demasiado', text: 'Intenta de nuevo. Si pediste muchos registros a la vez, usa filtros o menos registros por página.', reload: false }
    return { title: 'Ocurrió un error en el servidor', text: 'No se completó la acción. Intenta de nuevo; si sigue pasando, avisa a soporte.', reload: true }
}

const showError = (title: string, text: string, reload: boolean) =>
    void Swal.fire({
        icon: 'error',
        title,
        text,
        showCancelButton: reload,
        confirmButtonText: reload ? 'Recargar página' : 'Entendido',
        cancelButtonText: 'Cerrar',
        ...theme(),
    }).then((r) => {
        if (reload && r.isConfirmed) window.location.reload()
    })

onMounted(() => {
    off.push(router.on('start', (event: GlobalEvent<'start'>) => {
            const visit = event.detail.visit
            // Solo consultas visibles: los envíos de formularios ya muestran su propio aviso.
            if (visit.method !== 'get' || visit.prefetch) return
            active++
            clearTimers()
            showTimer = setTimeout(() => (loading.value = true), 350)
            slowTimer = setTimeout(() => (slow.value = true), 3500)
        }))
    off.push(router.on('finish', (event: GlobalEvent<'finish'>) => {
            const visit = event.detail.visit
            if (visit.method !== 'get' || visit.prefetch) return
            active = Math.max(0, active - 1)
            if (active === 0) stopLoading()
        }))
        // Respuesta que no es de la aplicación (p. ej. error 500 o sesión vencida).
    off.push(router.on('invalid', (event: GlobalEvent<'invalid'>) => {
            stopLoading()
            active = 0
            // En desarrollo se deja la página de error de Laravel para depurar.
            if (!import.meta.env.PROD) return
            event.preventDefault()
            const { title, text, reload } = messageFor(Number(event.detail.response?.status ?? 500))
            showError(title, text, reload)
        }))
        // Sin respuesta del servidor (sin internet, servidor caído, tiempo agotado).
    off.push(router.on('exception', (event: GlobalEvent<'exception'>) => {
            stopLoading()
            active = 0
            event.preventDefault()
            showError(
                navigator.onLine ? 'No se pudo contactar al servidor' : 'Sin conexión a internet',
                navigator.onLine
                    ? 'La solicitud no obtuvo respuesta. Revisa tu conexión e intenta de nuevo.'
                    : 'Revisa tu conexión. Cuando vuelva, intenta de nuevo.',
                false,
            )
        }))
})

onBeforeUnmount(() => {
    clearTimers()
    off.forEach((fn) => fn())
})
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
        enter-from-class="opacity-0 -translate-y-2"
        leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div
            v-if="loading"
            class="pointer-events-none fixed inset-x-0 top-20 z-[450] flex justify-center px-4"
        >
            <div
                role="status"
                aria-live="polite"
                class="pointer-events-auto flex max-w-md items-center gap-3 rounded-2xl border border-slate-200 bg-white/95 px-4 py-3 shadow-xl backdrop-blur
                       dark:border-white/10 dark:bg-zinc-900/95"
            >
                <span
                    class="h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-slate-200 border-t-brand-primary motion-reduce:animate-none dark:border-white/15 dark:border-t-zinc-100"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <div class="text-sm font-black text-slate-900 dark:text-zinc-50">Cargando…</div>
                    <div v-if="slow" class="text-xs text-slate-600 dark:text-zinc-300">
                        Sigue cargando. Puede tardar un poco si hay muchos registros; no cierres la página.
                    </div>
                </div>
            </div>
        </div>
    </Transition>

    <!-- Atenúa el contenido mientras carga para que se note que aún no es el resultado nuevo. -->
    <div
        v-if="loading"
        class="pointer-events-none fixed inset-0 z-[150] bg-white/40 transition-opacity motion-reduce:transition-none dark:bg-zinc-950/40"
        aria-hidden="true"
    />
</template>
