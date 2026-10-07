<?php

// app/Models/SystemLog.php

namespace App\Models;

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
}
