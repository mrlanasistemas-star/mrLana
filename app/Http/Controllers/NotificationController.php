<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Support\SafeUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notificaciones internas del usuario autenticado (campana y centro).
 * Cada usuario solo puede ver y marcar sus propias notificaciones.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->query('filtro') === 'no_leidas' ? 'no_leidas' : 'todas';
        $categoria = in_array($request->query('categoria'), NotificationTopic::values(), true) ? $request->query('categoria') : null;

        $query = $request->user()->notifications()->latest();
        if ($filter === 'no_leidas') {
            $query->whereNull('read_at');
        }
        if ($categoria) {
            $query->where('data->category', $categoria);
        }

        $page = $query->paginate(15)->withQueryString();
        $page->getCollection()->transform(fn (DatabaseNotification $n) => $this->present($n));

        return Inertia::render('Notificaciones/Index', [
            'notifications' => $page,
            'filters' => ['filtro' => $filter, 'categoria' => $categoria],
            'categorias' => NotificationTopic::options(),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
            // No leídas por categoría (para los contadores del panel lateral).
            'unreadByCategory' => $request->user()->unreadNotifications()->get(['data'])
                ->countBy(fn (DatabaseNotification $n) => NotificationTopic::tryFrom((string) ($n->data['category'] ?? ''))?->value ?? 'sistema'),
        ]);
    }

    /** JSON para la campana (también usado como respaldo de sondeo si no hay WebSocket). */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(8)->get()->map(fn ($n) => $this->present($n))->values(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['ok' => true, 'unread_count' => $request->user()->unreadNotifications()->count()])
            : back();
    }

    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['ok' => true, 'unread_count' => 0])
            : back()->with('success', 'Todas las notificaciones se marcaron como leídas.');
    }

    /** @return array<string, mixed> */
    private function present(DatabaseNotification $n): array
    {
        $data = $n->data;
        $topic = NotificationTopic::tryFrom((string) ($data['category'] ?? ''));

        return [
            'id' => $n->id,
            'title' => (string) ($data['title'] ?? 'Notificación'),
            'message' => (string) ($data['message'] ?? ''),
            'category' => $topic?->value ?? 'sistema',
            'category_label' => $topic?->label() ?? 'Sistema',
            'severity' => in_array($data['severity'] ?? null, ['info', 'success', 'warning', 'danger'], true) ? $data['severity'] : 'info',
            'url' => SafeUrl::internal($data['url'] ?? null),
            'read_at' => optional($n->read_at)->toISOString(),
            'created_at' => optional($n->created_at)->toISOString(),
        ];
    }
}
