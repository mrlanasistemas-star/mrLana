<?php // app/Models/Folio.php

namespace App\Models;

use App\Support\Permissions\AccessScope;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Folio de factura registrado en el panel de folios de comprobaciones.
 *
 * - El número de folio es el identificador: se fija al crearlo y nunca cambia
 *   (ni por el formulario ni por asignación masiva). Solo el monto es editable.
 * - El alcance sigue al módulo Comprobaciones y se calcula sobre quien lo
 *   registró (propio = lo registró la persona; sucursal/corporativo = el
 *   colaborador de quien lo registró).
 */
class Folio extends Model
{
    use HasFactory, LogsActivity;

    // Protección contra asignación masiva.
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (Folio $folio) {
            if ($folio->isDirty('folio')) {
                throw new LogicException('El número de folio no se puede modificar.');
            }
        });
    }

    // Usuario que registró este folio en el sistema.
    public function usuarioRegistro()
    {
        return $this->belongsTo(User::class, 'user_registro_id');
    }

    /** Folios dentro del alcance de comprobaciones del usuario. */
    public function scopeVisibleTo($query, User $user)
    {
        $registradoPor = fn (\Closure $empleados) => fn ($q) => $q->whereIn(
            'folios.user_registro_id',
            User::query()->select('users.id')->whereIn('users.empleado_id', $empleados(Empleado::query()->select('empleados.id')))
        );

        return AccessScope::apply($query, $user, 'comprobaciones', [
            'own' => fn ($q) => $q->where('folios.user_registro_id', $user->id),
            'sucursal' => fn ($q, int $sucursalId) => $registradoPor(fn ($e) => $e->where('empleados.sucursal_id', $sucursalId))($q),
            'corporativo' => fn ($q, int $corpId) => $registradoPor(fn ($e) => $e->whereIn('empleados.sucursal_id', AccessScope::sucursalesOf($corpId)))($q),
        ]);
    }

    public function isVisibleTo(User $user): bool
    {
        $own = (int) $this->user_registro_id === (int) $user->id;
        $registrador = $own ? null : $this->usuarioRegistro;

        return AccessScope::contains(
            $user,
            'comprobaciones',
            $own,
            $registrador ? AccessScope::sucursalId($registrador) : null,
            fn () => $registrador ? AccessScope::corporativoId($registrador) : null,
        );
    }
}
