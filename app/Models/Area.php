<?php

// app/Models/Area.php

namespace App\Models;

use App\Support\Permissions\AccessScope;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Area
 *
 * Representa un área o departamento dentro de un corporativo, a la cual se asocian empleados.
 *
 * @property int $id
 * @property int|null $corporativo_id
 * @property string $nombre
 * @property bool $activo
 */
class Area extends Model
{
    use HasFactory, LogsActivity;

    // Protección contra asignación masiva.
    protected $guarded = ['id'];

    protected $fillable = [
        'corporativo_id',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // El área pertenece a un corporativo.
    public function corporativo()
    {
        return $this->belongsTo(Corporativo::class);
    }

    // Un área agrupa múltiples empleados.
    public function empleados()
    {
        return $this->hasMany(Empleado::class);
    }

    /* ============================
     * Alcance (AccessScope): mi área, las de mi corporativo o todas
     * ============================ */

    public function scopeVisibleTo($query, User $user)
    {
        return AccessScope::apply($query, $user, 'areas', [
            'own' => fn ($q) => $q->where('areas.id', AccessScope::areaId($user) ?? 0),
            'corporativo' => 'areas.corporativo_id',
        ]);
    }

    public function isVisibleTo(User $user): bool
    {
        return AccessScope::contains(
            $user,
            'areas',
            (int) $this->id === AccessScope::areaId($user),
            null,
            $this->corporativo_id ? (int) $this->corporativo_id : null,
        );
    }
}
