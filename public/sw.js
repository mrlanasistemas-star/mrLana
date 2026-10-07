/*
 * Service worker de MR-Lana ERP (aplicación instalable).
 *
 * - Nunca guarda en caché páginas ni respuestas de la API: los datos del ERP
 *   son privados y deben estar siempre actualizados.
 * - Solo cachea recursos estáticos versionados (/build/*, /icons/*) para
 *   abrir más rápido.
 * - Sin conexión, las navegaciones muestran /offline.html.
 */
const VERSION = 'mrlana-v1'
const OFFLINE_URL = '/offline.html'
const PRECACHE = [OFFLINE_URL, '/icons/icon-192.png', '/icons/icon-512.png']

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()))
})

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    )
})

self.addEventListener('fetch', (event) => {
    const { request } = event
    if (request.method !== 'GET') return
    const url = new URL(request.url)
    if (url.origin !== self.location.origin) return

    // Navegación: siempre a la red; sin conexión, página informativa.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)))
        return
    }

    // Recursos estáticos con hash en el nombre: primero caché.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((res) => {
                if (res.ok) {
                    const copy = res.clone()
                    caches.open(VERSION).then((cache) => cache.put(request, copy))
                }
                return res
            })),
        )
    }
    // Todo lo demás (Inertia, JSON, archivos) pasa directo a la red.
})
