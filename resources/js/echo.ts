import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

/**
 * Cliente de tiempo real (Laravel Reverb vía protocolo Pusher).
 * Si no hay configuración VITE_REVERB_* devuelve null y la campana usa
 * actualización periódica como respaldo.
 */
let instance: Echo<'reverb'> | null | undefined

declare global {
    interface Window {
        Pusher: typeof Pusher
    }
}

export function getEcho(): Echo<'reverb'> | null {
    if (instance !== undefined) return instance

    const env = import.meta.env
    const key = env.VITE_REVERB_APP_KEY as string | undefined
    if (!key) {
        instance = null
        return instance
    }

    const scheme = (env.VITE_REVERB_SCHEME as string | undefined) ?? 'https'
    const port = Number(env.VITE_REVERB_PORT ?? (scheme === 'https' ? 443 : 80))

    window.Pusher = Pusher
    try {
        instance = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: (env.VITE_REVERB_HOST as string | undefined) ?? window.location.hostname,
            wsPort: port,
            wssPort: port,
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        })
    } catch {
        instance = null
    }

    return instance
}

/** Estado de la conexión WebSocket ('connected', 'unavailable', 'failed'...). */
export function onEchoStateChange(echo: Echo<'reverb'>, cb: (state: string) => void): void {
    const pusher = (echo.connector as unknown as { pusher?: Pusher }).pusher
    pusher?.connection.bind('state_change', (states: { current: string }) => cb(states.current))
    if (pusher) cb(pusher.connection.state)
}
