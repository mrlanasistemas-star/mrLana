<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{

    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            /*
            CorporativoSeeder::class,
            SucursalSeeder::class,
            AreaSeeder::class,
            EmpleadoSeeder::class,
            UserSeeder::class,
            ConceptoSeeder::class,
            ProveedorSeeder::class,
            RequisicionSeeder::class,
            DetalleSeeder::class,
            ComprobanteSeeder::class,
            FolioSeeder::class,
            AjusteSeeder::class,
            */
        ]);
    }

}
