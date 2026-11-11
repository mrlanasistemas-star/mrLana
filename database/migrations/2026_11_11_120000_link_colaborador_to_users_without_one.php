<?php

use App\Models\Empleado;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas activas sin colaborador vinculado: no aparecen como solicitantes y
 * no pueden crear requisiciones. Se reparan solo cuando el dato es inequívoco:
 *
 * 1. Existe un único colaborador activo, sin cuenta, con el mismo correo:
 *    se vincula.
 * 2. Si no, y el sistema tiene una sola sucursal activa: se registra ahí su
 *    colaborador (nombre y correo de la cuenta) y se vincula.
 *
 * Con varias sucursales no se adivina cuál le corresponde (definiría la
 * sucursal y el corporativo de sus requisiciones): la cuenta queda igual y
 * `php artisan erp:check-deploy` la lista para elegir su sucursal en Usuarios.
 *
 * Sin cambios de esquema; idempotente: las cuentas que ya tienen colaborador
 * no se tocan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->pretending() || ! Schema::hasColumn('users', 'empleado_id') || ! Schema::hasTable('empleados')) {
            return;
        }

        $sucursales = DB::table('sucursals')
            ->join('corporativos', 'corporativos.id', '=', 'sucursals.corporativo_id')
            ->where('sucursals.activo', true)
            ->where('corporativos.activo', true)
            ->limit(2)
            ->pluck('sucursals.id');
        $unicaSucursal = $sucursales->count() === 1 ? (int) $sucursales->first() : null;

        $users = DB::table('users')->whereNull('empleado_id')->where('activo', true)->orderBy('id')->get(['id', 'name', 'email']);

        foreach ($users as $user) {
            DB::transaction(function () use ($user, $unicaSucursal) {
                $candidatos = DB::table('empleados')
                    ->whereRaw('LOWER(empleados.email) = ?', [mb_strtolower(trim((string) $user->email))])
                    ->where('empleados.activo', true)
                    ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('users')->whereColumn('users.empleado_id', 'empleados.id'))
                    ->limit(2)
                    ->pluck('empleados.id');

                $empleadoId = match (true) {
                    $candidatos->count() === 1 => (int) $candidatos->first(),
                    $candidatos->isEmpty() && $unicaSucursal !== null => (int) DB::table('empleados')->insertGetId(
                        Empleado::nombreDesde((string) $user->name) + [
                            'sucursal_id' => $unicaSucursal,
                            'email' => $user->email,
                            'activo' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    ),
                    default => null,
                };

                if ($empleadoId !== null) {
                    DB::table('users')->where('id', $user->id)->whereNull('empleado_id')->update(['empleado_id' => $empleadoId]);
                }
            });
        }
    }

    /** No desvincula: el vínculo pudo corregirse después desde la interfaz. */
    public function down(): void {}
};
