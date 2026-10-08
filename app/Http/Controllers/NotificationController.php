<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Models\User;
use App\Support\SafeUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Centro de notificaciones (un solo módulo).
 *
 * - Con "Ver mis notificaciones" muestra solo las del usuario.
 * - Con "Ver todas las notificaciones" (se asigna en Roles y permisos) la
 *   misma pantalla muestra las de todas las personas, con su destinatario.
 *   Las ajenas son de solo lectura: abrirlas no cambia su estado de lectura.
 * - recent / markAsRead / markAllAsRead operan SOLO sobre las propias.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $todas = $user->can('notificaciones.ver_todas');

        $filter = $request->query('filtro') === 'no_leidas' ? 'no_leidas' : 'todas';
        $categoria = in_array($request->query('categoria'), NotificationTopic::values(), true) ? $request->query('categoria') : null;
        $q = trim((string) $request->query('q', ''));
        $destinatario = $todas ? ($request->integer('destinatario') ?: null) : null;

        $query = ($todas
            ? DatabaseNotification::query()->where('notifiable_type', $user->getMorphClass())
            : $user->notifications()->getQuery())
            ->when($destinatario, fn ($w, $id) => $w->where('notifiable_id', $id))
            ->when($filter === 'no_leidas', fn ($w) => $w->whereNull('read_at'))
            ->when($categoria, fn ($w, $c) => $w->where('data->category', $c))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('data->title', 'like', "%{$q}%")
                ->orWhere('data->message', 'like', "%{$q}%")))
            ->latest();

        $page = $query->paginate(15)->withQueryString();
        $recipients = $todas
            ? User::query()->whereIn('id', $page->getCollection()->pluck('notifiable_id')->unique())->get(['id', 'name', 'email'])->keyBy('id')
            : collect();

        $page->getCollection()->transform(function (DatabaseNotification $n) use ($user, $todas, $recipients) {
            $own = (int) $n->notifiable_id === (int) $user->id;
            $to = $recipients->get($n->notifiable_id);

            return $this->present($n) + [
                'own' => $own,
                'recipient' => $todas && ! $own ? ($to ? ['id' => $to->id, 'name' => $to->name, 'email' => $to->email] : ['id' => null, 'name' => 'Cuenta eliminada', 'email' => null]) : null,
            ];
        });

        return Inertia::render('Notificaciones/Index', [
            'notifications' => $page,
            'alcance' => $todas ? 'todas' : 'propias',
            'filters' => ['filtro' => $filter, 'categoria' => $categoria, 'q' => $q, 'destinatario' => $destinatario],
            'categorias' => NotificationTopic::options(),
            'destinatarios' => $todas
                ? User::query()->whereIn('id', DatabaseNotification::query()->where('notifiable_type', $user->getMorphClass())->select('notifiable_id'))
                    ->orderBy('name')->get(['id', 'name as nombre'])
                : [],
            // Contadores de no leídas: siempre las propias.
            'unreadCount' => $user->unreadNotifications()->count(),
            'unreadByCategory' => $user->unreadNotifications()->get(['data'])
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
