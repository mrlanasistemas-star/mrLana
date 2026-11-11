import { ref } from 'vue'

/**
 * Estado global del sidebar.
 * - Escritorio (lg+): rail de íconos (con tooltips) o menú expandido con etiquetas.
 *   El usuario alterna entre ambos y la preferencia se recuerda en este equipo.
 *   Sin preferencia guardada, arranca expandido en pantallas anchas (≥ 1280 px).
 * - Móvil y tableta vertical: drawer con overlay.
 */
const STORAGE_KEY = 'erp.sidebar.pinned'

function readPinned(): boolean {
    try {
        const saved = window.localStorage.getItem(STORAGE_KEY)
        if (saved !== null) return saved === '1'
    } catch {
        // Sin almacenamiento disponible: se usa el valor por omisión.
    }
    return window.matchMedia?.('(min-width: 1280px)').matches ?? false
}

const mobileOpen = ref(false)
/** true = menú expandido (etiquetas visibles); false = rail de íconos. */
const pinned = ref(typeof window !== 'undefined' ? readPinned() : false)

export function useSidebar() {
    function togglePinned() {
        pinned.value = !pinned.value
        try {
            window.localStorage.setItem(STORAGE_KEY, pinned.value ? '1' : '0')
        } catch {
            // Sin almacenamiento disponible (modo privado): el estado vive solo en esta sesión.
        }
    }

    return {
        mobileOpen,
        pinned,
        openMobile: () => (mobileOpen.value = true),
        closeMobile: () => (mobileOpen.value = false),
        togglePinned,
    }
}
