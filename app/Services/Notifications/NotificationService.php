<?php

namespace App\Services\Notifications;

use App\Enums\NotificationTopic;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ErpNotification;
use App\Support\Permissions\AccessScope;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Despacha notificaciones internas según la configuración de cada rol.
 *
 * Destinatarios = (usuarios activos cuyos roles reciben el tema o "todas",
 * que pueden ver notificaciones y —si el evento es sobre un registro— pueden
 * ver ese registro) ∪ destinatarios directos activos
 * (p. ej. el solicitante de una requisición). Cada usuario recibe una sola
 * notificación por evento aunque tenga varios roles.
 *
 * Si el rol de una persona tiene activo el tema, recibe el aviso sin importar
 * quién hizo la acción, incluso si fue ella misma (así un administrador ve en
 * su campana las requisiciones que envía). A los destinatarios directos no se
 * les avisa de sus propias acciones.
 *
 * Suscribirse a un tema no amplía el alcance: con `canSee` solo se avisa a
 * quien puede abrir el registro relacionado. La excepción son los roles que
 * reciben todas las notificaciones y además pueden ver todas las
 * notificaciones (consulta global, actual y futura).
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
        ?Closure $canSee = null,
    ): Collection {
        $recipients = $includeSubscribers ? $this->subscribersFor($topic, $canSee) : collect();

        foreach ($direct as $user) {
            if ($user instanceof User && $user->activo && ! ($actor && $user->is($actor))) {
                $recipients->push($user);
            }
        }

        $recipients = $recipients->unique('id')->values();

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
    public function subscribersFor(NotificationTopic $topic, ?Closure $canSee = null): Collection
    {
        $roles = Role::query()
            ->with('notificationPreference')
            ->whereHas('notificationPreference', function ($q) use ($topic) {
                $q->where('receive_all', true)
                    ->orWhereJsonContains('topics', $topic->value);
            })
            ->get();

        if ($roles->isEmpty()) {
            return collect();
        }

        $receiveAllIds = $roles->filter(fn (Role $r) => $r->notificationPreference?->receive_all)->pluck('id');

        return User::query()
            ->active()
            ->with('roles:id')
            ->whereHas('roles', fn ($q) => $q->whereIn('id', $roles->pluck('id')))
            ->get()
            ->filter(function (User $u) use ($canSee, $receiveAllIds) {
                if (! AccessScope::for($u, 'notificaciones')->allows()) {
                    return false;
                }
                if ($canSee === null) {
                    return true;
                }
                $global = $u->can('notificaciones.ver_todas')
                    && $u->roles->pluck('id')->intersect($receiveAllIds)->isNotEmpty();

                return $global || $canSee($u);
            })
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
