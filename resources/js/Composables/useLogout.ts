import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

/** Confirmación y cierre de sesión (compartido por sidebar y navbar). */
export function useLogout() {
    const isDark = () => document.documentElement.classList.contains('dark')

    async function confirmLogout() {
        const dark = isDark()
        const result = await Swal.fire({
            title: '¿Cerrar sesión?',
            text: 'Perderás acceso hasta volver a iniciar sesión.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cerrar sesión',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            background: dark ? '#18181b' : '#ffffff',
            color: dark ? '#e4e4e7' : '#111827',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: dark ? '#3f3f46' : '#6b7280',
        })
        if (!result.isConfirmed) return

        router.post(route('logout'), {}, { preserveScroll: true })
    }

    return { confirmLogout }
}
