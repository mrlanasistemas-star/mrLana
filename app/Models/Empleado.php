<?php

// app/Models/Empleado.php

namespace App\Models;

use App\Support\Permissions\AccessScope;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Empleado
 *
 * Representa a un empleado ligado a una sucursal y área. Puede tener usuario de sistema, generar requisiciones y estar asociado a gastos.
 *
 * @property int $id
 * @property int $sucursal_id
 * @property int|null $area_id
 * @property string $nombre
 * @property string $apellido_paterno
 * @property string|null $apellido_materno
 * @property string|null $email
 * @property string|null $telefono
 * @property string|null $puesto
 * @property bool $activo
 */
class Empleado extends Model
{
    use HasFactory, LogsActivity;

    // Protección contra asignación masiva.
    protected $guarded = ['id'];

    protected $fillable = [
        'sucursal_id',
        'area_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'email',
        'telefono',
        'puesto',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // El empleado pertenece a una sucursal.
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    // El empleado pertenece a un área.
    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    // Relación uno a uno con el usuario del sistema.
    public function user()
    {
        return $this->hasOne(User::class);
    }

    // Requisiciones donde el empleado es el solicitante.
    public function requisicionsSolicitadas()
    {
        return $this->hasMany(Requisicion::class, 'solicitante_id');
    }

    /**
     * Reparte un nombre completo ("Héctor Pérez García") en los campos del
     * colaborador. Con tres palabras o más, las dos últimas son los apellidos.
     * Se usa al registrar el colaborador de una cuenta que no tenía uno.
     *
     * @return array{nombre: string, apellido_paterno: string, apellido_materno: ?string}
     */
    public static function nombreDesde(string $nombreCompleto): array
    {
        $partes = preg_split('/\s+/u', trim($nombreCompleto), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $apellidos = count($partes) >= 3 ? array_splice($partes, -2) : array_splice($partes, 1);

        return [
            'nombre' => mb_substr(implode(' ', $partes), 0, 120),
            'apellido_paterno' => mb_substr($apellidos[0] ?? '', 0, 120),
            'apellido_materno' => isset($apellidos[1]) ? mb_substr($apellidos[1], 0, 120) : null,
        ];
    }

    /* ============================
     * Alcance (AccessScope): mi registro, mi sucursal, mi corporativo o todos
     * ============================ */

    public function scopeVisibleTo($query, User $user)
    {
        return AccessScope::apply($query, $user, 'colaboradores', [
            'own' => fn ($q) => $q->where('empleados.id', $user->empleado_id ?? 0),
            'sucursal' => 'empleados.sucursal_id',
        ]);
    }

    public function isVisibleTo(User $user): bool
    {
        return AccessScope::contains(
            $user,
            'colaboradores',
            $user->empleado_id !== null && (int) $this->id === (int) $user->empleado_id,
            $this->sucursal_id ? (int) $this->sucursal_id : null,
            fn () => Sucursal::query()->whereKey($this->sucursal_id)->value('corporativo_id'),
        );
    }
}
