<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\RoleSynchronizer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'empleado_id' => null,
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'rol' => 'COLABORADOR',
            'activo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    /** Asigna un rol (lo crea con sus permisos predeterminados si aún no existe). */
    public function withRole(string $roleName): static
    {
        $legacy = array_flip(PermissionCatalog::LEGACY_ROLE_MAP)[$roleName] ?? 'COLABORADOR';

        return $this->state(fn () => ['rol' => $legacy])
            ->afterCreating(function (User $user) use ($roleName) {
                if (! Role::query()->where('name', $roleName)->exists()) {
                    app(RoleSynchronizer::class)->sync();
                }
                $user->assignRole($roleName);
            });
    }

    public function admin(): static
    {
        return $this->withRole(PermissionCatalog::ROLE_ADMIN);
    }

    public function contabilidad(): static
    {
        return $this->withRole(PermissionCatalog::ROLE_CONTABILIDAD);
    }

    public function colaborador(): static
    {
        return $this->withRole(PermissionCatalog::ROLE_COLABORADOR);
    }
}
