<?php

namespace App\Notifications;

use App\Enums\NotificationTopic;
use App\Support\SafeUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación interna del ERP (campana).
 *
 * - Se guarda en la tabla `notifications` (canal database).
 * - Se emite en tiempo real por el canal privado del usuario (Reverb).
 * - Se encola y se despacha solo después de confirmar la transacción.
 */
class ErpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const SEVERITIES = ['info', 'success', 'warning', 'danger'];

    public string $severity;

    public ?string $url;

    public function __construct(
        public NotificationTopic $topic,
        public string $event,
        public string $title,
        public string $message,
        string $severity = 'info',
        ?string $url = null,
    ) {
        $this->severity = in_array($severity, self::SEVERITIES, true) ? $severity : 'info';
        $this->url = SafeUrl::internal($url);
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->event;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'category' => $this->topic->value,
            'category_label' => $this->topic->label(),
            'severity' => $this->severity,
            'url' => $this->url,
            'event' => $this->event,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            ...$this->toArray($notifiable),
            'id' => $this->id,
            'created_at' => now()->toISOString(),
        ]);
    }

    public function broadcastType(): string
    {
        return 'erp.notification';
    }
}
