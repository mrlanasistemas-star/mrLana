<script setup lang="ts">
import { watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import type { SharedProps } from '@/types/shared'

/**
 * Muestra los mensajes de error y advertencia que envía el servidor
 * (p. ej. "Este ajuste ya fue aplicado"). Los mensajes de éxito los
 * presenta cada pantalla con su propio contexto.
 */
const page = usePage<SharedProps>()

watch(
    () => [page.props.flash?.error, page.props.flash?.warning] as const,
    ([error, warning]) => {
        const message = error || warning
        if (!message) return
        const dark = document.documentElement.classList.contains('dark')
        void Swal.fire({
            toast: true,
            position: 'top-end',
            icon: error ? 'error' : 'warning',
            title: message,
            showConfirmButton: false,
            showCloseButton: true,
            timer: 6000,
            timerProgressBar: true,
            background: dark ? '#18181b' : '#ffffff',
            color: dark ? '#e4e4e7' : '#111827',
        })
    },
    { immediate: true },
)
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>
