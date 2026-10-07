import { watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { swalNotify } from '@/lib/swal'
import type { SharedProps } from '@/types/shared'

/**
 * Muestra como toast el mensaje de éxito que envía el servidor (flash.success).
 * Errores y advertencias ya los presenta FlashToaster en el layout.
 */
export function useFlashSuccess() {
    const page = usePage<SharedProps>()
    // Se observa el objeto flash (nuevo en cada respuesta) para que dos mensajes
    // idénticos seguidos también se muestren.
    watch(
        () => page.props.flash,
        (flash) => {
            if (flash?.success) void swalNotify(flash.success, 'ok')
        },
        { immediate: true },
    )
}
