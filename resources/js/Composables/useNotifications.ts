import { computed, ref } from 'vue'
import axios from 'axios'
import { getEcho, onEchoStateChange } from '@/echo'
import type { ErpNotificationItem } from '@/types/shared'

/**
 * Estado global de la campana de notificaciones.
 *
 * - Tiempo real: canal privado del usuario (Reverb/Echo).
 * - Respaldo: si el WebSocket no está disponible, consulta cada 45 s y al
 *   volver a la pestaña.
 */
const POLL_MS = 45_000

const items = ref<ErpNotificationItem[]>([])
const unread = ref(0)
const loading = ref(false)
const realtime = ref(false)
const pulse = ref(0)
const lastError = ref<string | null>(null)

let startedFor: number | null = null
let pollTimer: number | undefined

async function refresh(): Promise<void> {
    loading.value = true
    try {
        const { data } = await axios.get<{ unread_count: number; items: ErpNotificationItem[] }>(route('notificaciones.recent'))
        if (data.unread_count > unread.value) pulse.value++
        unread.value = data.unread_count
        items.value = data.items
        lastError.value = null
    } catch {
        lastError.value = 'No se pudieron cargar las notificaciones.'
    } finally {
        loading.value = false
    }
}

function startPolling() {
    if (pollTimer !== undefined) return
    pollTimer = window.setInterval(() => {
        if (document.visibilityState === 'visible') void refresh()
    }, POLL_MS)
}

function stopPolling() {
    if (pollTimer === undefined) return
    window.clearInterval(pollTimer)
    pollTimer = undefined
}

function onIncoming(payload: Partial<ErpNotificationItem> & { id?: string }) {
    if (!payload?.id || items.value.some((n) => n.id === payload.id)) return
    items.value = [
        {
            id: payload.id,
            title: payload.title ?? 'Notificación',
            message: payload.message ?? '',
            category: payload.category ?? 'sistema',
            category_label: payload.category_label ?? 'Sistema',
            severity: payload.severity ?? 'info',
            url: payload.url ?? null,
            read_at: null,
            created_at: payload.created_at ?? new Date().toISOString(),
        },
        ...items.value,
    ].slice(0, 8)
    unread.value++
    pulse.value++
}

export function useNotifications() {
    function start(userId: number, initialUnread: number) {
        if (startedFor === userId) return
        startedFor = userId
        unread.value = initialUnread
        void refresh()

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && !realtime.value) void refresh()
        })

        const echo = getEcho()
        if (!echo) {
            startPolling()
            return
        }

        echo.private(`App.Models.User.${userId}`).notification(onIncoming)
        onEchoStateChange(echo, (state) => {
            realtime.value = state === 'connected'
            if (realtime.value) {
                stopPolling()
            } else if (['unavailable', 'failed', 'disconnected'].includes(state)) {
                startPolling()
            }
        })
    }

    async function markAsRead(item: ErpNotificationItem) {
        if (item.read_at) return
        item.read_at = new Date().toISOString()
        unread.value = Math.max(0, unread.value - 1)
        try {
            const { data } = await axios.patch<{ unread_count: number }>(route('notificaciones.read', item.id))
            unread.value = data.unread_count
        } catch {
            item.read_at = null
            unread.value++
        }
    }

    async function markAllAsRead() {
        const previous = items.value.map((n) => n.read_at)
        const now = new Date().toISOString()
        items.value.forEach((n) => (n.read_at = n.read_at ?? now))
        const before = unread.value
        unread.value = 0
        try {
            await axios.post(route('notificaciones.readAll'))
        } catch {
            items.value.forEach((n, i) => (n.read_at = previous[i]))
            unread.value = before
            lastError.value = 'No se pudieron marcar como leídas. Intenta de nuevo.'
        }
    }

    return {
        items,
        unread,
        loading,
        realtime,
        pulse,
        lastError,
        hasUnread: computed(() => unread.value > 0),
        start,
        refresh,
        markAsRead,
        markAllAsRead,
    }
}

/** Solo URLs internas: rutas relativas de la propia aplicación. */
export function safeInternalUrl(url: string | null | undefined): string | null {
    if (!url) return null
    if (url.startsWith('/') && !url.startsWith('//') && !url.startsWith('/\\')) return url
    try {
        const parsed = new URL(url, window.location.origin)
        return parsed.origin === window.location.origin ? parsed.pathname + parsed.search + parsed.hash : null
    } catch {
        return null
    }
}
