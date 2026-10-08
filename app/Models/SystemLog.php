<?php

// app/Models/SystemLog.php

namespace App\Models;

use App\Support\Permissions\AccessScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SystemLog
 *
 * Registro simple de acciones realizadas dentro del sistema.
 * Guarda quién hizo qué, sobre qué tabla, qué registro y desde dónde.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $accion
 * @property string $tabla
 * @property int|null $registro_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $descripcion
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class SystemLog extends Model
{
    use HasFactory;

    // Protección contra asignación masiva.
    protected $guarded = ['id'];

    protected $casts = [
        'cambios' => 'array',
    ];

    // Usuario que ejecutó la acción.
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alcance (AccessScope): mi actividad, la de personas de mi sucursal o
     * corporativo (según su colaborador), o toda la bitácora.
     */
    public function scopeVisibleTo($query, User $user)
    {
        $usersOf = fn ($sucursales) => User::query()->select('users.id')
            ->whereIn('users.empleado_id', Empleado::query()->select('empleados.id')->whereIn('empleados.sucursal_id', $sucursales));

        return AccessScope::apply($query, $user, 'logs', [
            'own' => fn ($q) => $q->where('system_logs.user_id', $user->id),
            'sucursal' => fn ($q, int $id) => $q->whereIn('system_logs.user_id', $usersOf([$id])),
            'corporativo' => fn ($q, int $id) => $q->whereIn('system_logs.user_id', $usersOf(AccessScope::sucursalesOf($id))),
        ]);
    }
}
