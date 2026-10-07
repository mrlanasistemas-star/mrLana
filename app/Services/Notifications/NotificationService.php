<?php

namespace App\Services\Notifications;

use App\Enums\NotificationTopic;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ErpNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Despacha notificaciones internas según la configuración de cada rol.
 *
 * Destinatarios = (usuarios activos cuyos roles reciben el tema o "todas",
 * y que pueden ver notificaciones) ∪ destinatarios directos activos
 * (p. ej. el solicitante de una requisición). Cada usuario recibe una sola
 * notificación por evento aunque tenga varios roles. Quien realiza la acción
 * no se notifica a sí mismo.
 */
class NotificationService
{
    /**
     * @param  iterable<User|null>  $direct
     */
    public function notify(
        NotificationTopic $topic,
        string $event,
        string $title,
        string $message,
        string $severity = 'info',
        ?string $url = null,
        iterable $direct = [],
        ?User $actor = null,
        bool $includeSubscribers = true,
    ): Collection {
        $recipients = collect();

        if ($includeSubscribers) {
            $recipients = $recipients->merge($this->subscribersFor($topic));
        }

        foreach ($direct as $user) {
            if ($user instanceof User && $user->activo) {
                $recipients->push($user);
            }
        }

        $recipients = $recipients
            ->unique('id')
            ->reject(fn (User $u) => $actor && (int) $u->id === (int) $actor->id)
            ->values();

        if ($recipients->isNotEmpty()) {
            Notification::send(
                $recipients,
                new ErpNotification($topic, $event, $title, $message, $severity, $url)
            );
        }

        return $recipients;
    }

    /**
     * Notifica solo a destinatarios directos (resoluciones personales).
     *
     * @param  iterable<User|null>  $direct
     */
    public function notifyDirect(
        NotificationTopic $topic,
        string $event,
        string $title,
        string $message,
        iterable $direct,
        string $severity = 'info',
        ?string $url = null,
        ?User $actor = null,
    ): Collection {
        return $this->notify($topic, $event, $title, $message, $severity, $url, $direct, $actor, false);
    }

    /**
     * Usuarios activos suscritos al tema por alguno de sus roles y con
     * permiso para ver notificaciones.
     *
     * @return Collection<int, User>
     */
    public function subscribersFor(NotificationTopic $topic): Collection
    {
        $roleIds = Role::query()
            ->whereHas('notificationPreference', function ($q) use ($topic) {
                $q->where('receive_all', true)
                    ->orWhereJsonContains('topics', $topic->value);
            })
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->active()
            ->whereHas('roles', fn ($q) => $q->whereIn('id', $roleIds))
            ->get()
            ->filter(fn (User $u) => $u->can('notificaciones.ver'))
            ->values();
    }

    /**
     * Correo como canal secundario: un fallo SMTP se reporta pero nunca
     * revierte ni interrumpe la operación ya guardada.
     *
     * @param  string|list<string>  $to
     */
    public function mail(string|array $to, \Illuminate\Mail\Mailable $mailable): bool
    {
        $to = array_values(array_filter((array) $to, fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL)));
        if ($to === []) {
            return false;
        }

        try {
            Mail::to($to)->send($mailable);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
