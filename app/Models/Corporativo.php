<?php

namespace App\Models;

use App\Support\Permissions\AccessScope;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Corporativo
 *
 * Representa un corporativo dentro del ERP. Un corporativo puede tener múltiples sucursales, áreas, contratos de inversión, ingresos, gastos y requisiciones donde actúa como comprador.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $rfc
 * @property string|null $direccion
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $codigo
 * @property string|null $logo_path
 * @property bool $activo
 */
class Corporativo extends Model
{
    use HasFactory, LogsActivity;

    // Protección contra asignación masiva.
    protected $guarded = ['id'];

    protected $fillable = [
        'nombre',
        'rfc',
        'direccion',
        'telefono',
        'email',
        'codigo',
        'logo_path',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Un corporativo tiene muchas sucursales.
    public function sucursales()
    {
        return $this->hasMany(Sucursal::class);
    }

    // Un corporativo puede tener muchas áreas.
    public function areas()
    {
        return $this->hasMany(Area::class);
    }

    // Requisiciones donde este corporativo actúa como comprador.
    public function requisicionesComprador()
    {
        return $this->hasMany(Requisicion::class, 'comprador_corp_id');
    }

    /* ============================
     * Alcance (AccessScope): "mi corporativo" o todos
     * ============================ */

    public function scopeVisibleTo($query, User $user)
    {
        return AccessScope::apply($query, $user, 'corporativos', [
            'own' => fn ($q) => $q->where('corporativos.id', AccessScope::corporativoId($user) ?? 0),
        ]);
    }

    public function isVisibleTo(User $user): bool
    {
        return AccessScope::contains($user, 'corporativos', (int) $this->id === AccessScope::corporativoId($user));
    }
}
