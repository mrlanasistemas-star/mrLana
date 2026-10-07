<?php

use App\Support\Database\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Descripción de roles y preferencias de notificación por rol (InnoDB, idempotente). */
return new class extends Migration
{
    public function up(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');

        if (! Schema::hasColumn($rolesTable, 'descripcion')) {
            Schema::table($rolesTable, function (Blueprint $table) {
                $table->string('descripcion', 500)->nullable()->after('guard_name');
            });
        }

        SafeMigration::createTable('role_notification_preferences', function (Blueprint $table) use ($rolesTable) {
            $table->id();
            $table->foreignId('role_id')->unique()->constrained($rolesTable)->cascadeOnDelete();
            $table->boolean('receive_all')->default(false);
            $table->json('topics')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');

        SafeMigration::assertCanDiscard([
            'role_notification_preferences' => null,
            $rolesTable => fn ($q) => $q->whereNotNull('descripcion'),
        ], 'descripciones y preferencias de notificación de los roles');

        Schema::dropIfExists('role_notification_preferences');

        if (Schema::hasColumn($rolesTable, 'descripcion')) {
            Schema::table($rolesTable, fn (Blueprint $table) => $table->dropColumn('descripcion'));
        }
    }
};
