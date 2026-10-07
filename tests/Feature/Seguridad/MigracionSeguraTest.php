<?php

namespace Tests\Feature\Seguridad;

use App\Support\Database\SafeMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class MigracionSeguraTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_se_detiene_si_borraria_informacion(): void
    {
        DB::table('notifications')->insert([
            'id' => 'b1c2', 'type' => 'x', 'notifiable_type' => 'App\\Models\\User', 'notifiable_id' => 1,
            'data' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        SafeMigration::assertCanDiscard(['notifications' => null], 'notificaciones');
    }

    public function test_rollback_procede_sin_datos_o_con_autorizacion_explicita(): void
    {
        SafeMigration::assertCanDiscard(['notifications' => null], 'notificaciones');

        DB::table('notifications')->insert([
            'id' => 'b1c3', 'type' => 'x', 'notifiable_type' => 'App\\Models\\User', 'notifiable_id' => 1,
            'data' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        config(['erp.allow_destructive_rollback' => true]);
        SafeMigration::assertCanDiscard(['notifications' => null], 'notificaciones');

        $this->addToAssertionCount(1);
    }

    public function test_crear_tabla_reconstruye_restos_vacios_pero_respeta_datos(): void
    {
        Schema::create('restos_prueba', fn (Blueprint $t) => $t->id());
        SafeMigration::createTable('restos_prueba', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
        });
        $this->assertTrue(Schema::hasColumn('restos_prueba', 'nombre'));

        DB::table('restos_prueba')->insert(['nombre' => 'dato real']);
        $this->expectException(RuntimeException::class);
        SafeMigration::createTable('restos_prueba', fn (Blueprint $t) => $t->id());
    }
}
