<?php

// app/Models/User.php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Support\Permissions\AccessScope;
use App\Support\Permissions\PermissionCatalog;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * Class User
 *
 * Cuenta autenticable del sistema. Puede estar vinculada (uno a uno) a un
 * colaborador (tabla `empleados`). La autorización se resuelve con roles y
 * permisos (spatie/laravel-permission).
 *
 * `rol` es un campo LEGADO (ADMIN/CONTADOR/COLABORADOR): se conserva por
 * compatibilidad y se mantiene sincronizado con el rol asignado, pero ya no es
 * la fuente de autorización. No lo uses para decidir accesos.
 *
 * @property int $id
 * @property int|null $empleado_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $rol
 * @property bool $activo
 */
class User extends Authenticatable
{
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    /**
     * Atributos con asignación masiva permitida.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'empleado_id',
        'activo',
    ];

    /**
     * Atributos ocultos al serializar.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'roles',
        'permissions',
    ];

    /**
     * Casts de atributos especiales.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Canal privado de broadcasting para notificaciones del usuario.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'App.Models.User.'.$this->id;
    }

    /*=========================================================
     | ESTADO Y PERMISOS
     =========================================================*/

    /**
     * Al desactivar una cuenta se revoca todo acceso vigente: sesiones abiertas
     * en otros dispositivos y la cookie "recordarme". EnsureUserIsActive cubre
     * cualquier petición que aún llegue con una sesión previa.
     */
    protected static function booted(): void
    {
        static::updating(function (User $user) {
            if ($user->isDirty('activo') && ! $user->activo) {
                $user->remember_token = Str::random(60);
            }
        });

        static::updated(function (User $user) {
            if ($user->wasChanged('activo') && ! $user->activo && config('session.driver') === 'database') {
                DB::table(config('session.table') ?: 'sessions')->where('user_id', $user->id)->delete();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** Tiene administración total (roles + usuarios). */
    public function hasFullAdministration(): bool
    {
        foreach (PermissionCatalog::ADMIN_PERMISSIONS as $permission) {
            if (! $this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /** Nombres de permisos efectivos (directos + vía roles). */
    public function permissionNames(): array
    {
        return $this->getAllPermissions()->pluck('name')->values()->all();
    }

    /** Mantiene el campo legado `rol` coherente con el rol asignado. */
    public function syncLegacyRol(?Role $role): void
    {
        $map = array_flip(PermissionCatalog::LEGACY_ROLE_MAP);
        $legacy = $role ? ($map[$role->name] ?? null) : null;

        if ($legacy === null && $role) {
            $legacy = $role->hasPermissionTo('roles.editar') ? 'ADMIN'
                : ($role->hasPermissionTo('pagos.autorizar') ? 'CONTADOR' : 'COLABORADOR');
        }

        $this->rol = $legacy ?? 'COLABORADOR';
    }

    /*=========================================================
     | RELACIONES DEL ERP
     =========================================================*/

    /**
     * Relación: Usuario → Colaborador (opcional, uno a uno).
     */
    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    /**
     * Proveedores creados por este usuario.
     */
    public function proveedors()
    {
        return $this->hasMany(Proveedor::class, 'user_duenio_id');
    }

    /**
     * Requisiciones creadas por este usuario.
     */
    public function requisicionsCreadas()
    {
        return $this->hasMany(Requisicion::class, 'creada_por_user_id');
    }

    /**
     * Comprobantes cargados al sistema por este usuario.
     */
    public function comprobantesCargados()
    {
        return $this->hasMany(Comprobante::class, 'user_carga_id');
    }

    /**
     * Ajustes (devolución/faltante) registrados por este usuario.
     */
    public function ajustesRegistrados()
    {
        return $this->hasMany(Ajuste::class, 'user_registro_id');
    }

    /**
     * Folios de factura registrados por este usuario.
     */
    public function foliosRegistrados()
    {
        return $this->hasMany(Folio::class, 'user_registro_id');
    }

    /* =========================================================
     | ALCANCE (AccessScope)
     =========================================================*/

    /**
     * Cuentas que el usuario puede administrar: las de colaboradores de su
     * sucursal o corporativo, o todas (incluidas las que no tienen
     * colaborador, que solo se ven con "Ver todos los usuarios").
     */
    public function scopeVisibleTo($query, User $user)
    {
        return AccessScope::apply($query, $user, 'usuarios', [
            'own' => fn ($q) => $q->where('users.id', $user->id),
            'sucursal' => fn ($q, int $id) => $q->whereIn('users.empleado_id', Empleado::query()->select('id')->where('sucursal_id', $id)),
            'corporativo' => fn ($q, int $id) => $q->whereIn('users.empleado_id', Empleado::query()->select('id')->whereIn('sucursal_id', AccessScope::sucursalesOf($id))),
        ]);
    }

    public function isVisibleTo(User $viewer): bool
    {
        $this->loadMissing('empleado.sucursal');

        return AccessScope::contains(
            $viewer,
            'usuarios',
            $this->is($viewer),
            $this->empleado?->sucursal_id ? (int) $this->empleado->sucursal_id : null,
            $this->empleado?->sucursal?->corporativo_id ? (int) $this->empleado->sucursal->corporativo_id : null,
        );
    }
}
