<?php

namespace Database\Seeders;

use App\Models\Empleado;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $empleados = Empleado::where('activo', true)->get()->shuffle();

        // Un colaborador solo puede tener una cuenta (relación uno a uno).
        $empleadoAdmin = $empleados->shift();
        User::factory()->admin()->create([
            'empleado_id' => $empleadoAdmin?->id,
            'name' => 'Admin Sistema',
            'email' => 'admin@demo.local',
        ]);

        $empleados->take(25)->each(function ($emp, $i) {
            $factory = match ($i % 3) {
                0 => User::factory()->contabilidad(),
                default => User::factory()->colaborador(),
            };

            $factory->create(['empleado_id' => $emp->id]);
        });
    }
}
