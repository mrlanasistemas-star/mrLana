<?php

namespace App\Models;

use App\Enums\NotificationTopic;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Rol del sistema (spatie/laravel-permission) con descripción y
 * preferencias de notificación.
 *
 * @property int $id
 * @property string $name
 * @property string|null $descripcion
 */
class Role extends SpatieRole
{
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(RoleNotificationPreference::class);
    }

    public function receivesTopic(NotificationTopic $topic): bool
    {
        $pref = $this->notificationPreference;
        if (! $pref) {
            return false;
        }

        return $pref->receive_all || in_array($topic->value, $pref->topics ?? [], true);
    }
}
