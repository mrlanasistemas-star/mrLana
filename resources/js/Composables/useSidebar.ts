import { ref } from 'vue'

/**
 * Estado global del sidebar.
 * - Escritorio: se expande al pasar el puntero (si el dispositivo tiene hover)
 *   o se puede "anclar" abierto con un botón (útil en tabletas táctiles).
 * - Móvil y tableta vertical: drawer con overlay.
 */
const STORAGE_KEY = 'erp.sidebar.pinned'

function readPinned(): boolean {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === '1'
    } catch {
        return false
    }
}

const mobileOpen = ref(false)
const pinned = ref(typeof window !== 'undefined' ? readPinned() : false)
const hovering = ref(false)

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
        hovering,
        openMobile: () => (mobileOpen.value = true),
        closeMobile: () => (mobileOpen.value = false),
        togglePinned,
    }
}
