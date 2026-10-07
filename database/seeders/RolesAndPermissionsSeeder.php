<?php

namespace Database\Seeders;

use App\Support\Permissions\RoleSynchronizer;
use Illuminate\Database\Seeder;

/**
 * Sincroniza (idempotente) el catálogo de permisos, los roles iniciales y la
 * asignación de rol de los usuarios que aún no tienen uno.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(RoleSynchronizer $synchronizer): void
    {
        $synchronizer->sync();
    }
}
