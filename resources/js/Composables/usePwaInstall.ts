import { computed, ref } from 'vue'

/**
 * Instalación del ERP como aplicación (PWA) en escritorio (Chrome/Edge) y Android.
 *
 * El navegador emite `beforeinstallprompt` una sola vez y muy temprano, por eso
 * initPwa() se llama al arrancar (app.js) y guarda el evento para usarlo cuando
 * la persona presione "Instalar app".
 */
type InstallPrompt = Event & {
    prompt: () => Promise<void>
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

const deferred = ref<InstallPrompt | null>(null)
const installed = ref(false)
let started = false

const standalone = () =>
    window.matchMedia?.('(display-mode: standalone)').matches ||
    window.matchMedia?.('(display-mode: window-controls-overlay)').matches ||
    (navigator as Navigator & { standalone?: boolean }).standalone === true

export function initPwa() {
    if (started || typeof window === 'undefined') return
    started = true
    installed.value = standalone()

    // El aviso se captura en el <head> (app.blade.php) porque puede llegar antes que este script.
    const w = window as Window & { __erpInstallPrompt?: InstallPrompt }
    const take = () => { if (w.__erpInstallPrompt) deferred.value = w.__erpInstallPrompt }
    take()
    window.addEventListener('erp:installable', take)
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault() // se muestra con nuestro botón, no con el aviso automático
        deferred.value = e as InstallPrompt
    })
    window.addEventListener('appinstalled', () => {
        installed.value = true
        deferred.value = null
    })

    // El service worker solo funciona en HTTPS o localhost.
    if ('serviceWorker' in navigator && window.isSecureContext) {
        const register = () =>
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
                // Sin service worker el ERP funciona igual; solo no se podrá instalar en algunos navegadores.
            })
        if (document.readyState === 'complete') register()
        else window.addEventListener('load', register, { once: true })
    }
}

export function usePwaInstall() {
    const ua = typeof navigator !== 'undefined' ? navigator.userAgent : ''
    const isIOS = /iPad|iPhone|iPod/.test(ua) || (ua.includes('Macintosh') && 'ontouchend' in document)
    const isAndroid = /Android/i.test(ua)
    const isFirefox = /Firefox\//.test(ua)
    const isSafari = /^((?!chrome|android|crios|fxios|edg).)*safari/i.test(ua)

    /** Abre el diálogo nativo de instalación. Devuelve false si el navegador no lo ofrece. */
    async function install(): Promise<boolean> {
        const ev = deferred.value
        if (!ev) return false
        await ev.prompt()
        const { outcome } = await ev.userChoice
        deferred.value = null
        if (outcome === 'accepted') installed.value = true
        return true
    }

    return {
        canPrompt: computed(() => deferred.value !== null),
        installed: computed(() => installed.value),
        isIOS,
        isAndroid,
        isFirefox,
        isSafari,
        secure: typeof window !== 'undefined' && window.isSecureContext,
        install,
    }
}
