<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $role_id
 * @property bool $receive_all
 * @property list<string>|null $topics
 */
class RoleNotificationPreference extends Model
{
    protected $fillable = ['role_id', 'receive_all', 'topics'];

    protected $casts = [
        'receive_all' => 'boolean',
        'topics' => 'array',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
