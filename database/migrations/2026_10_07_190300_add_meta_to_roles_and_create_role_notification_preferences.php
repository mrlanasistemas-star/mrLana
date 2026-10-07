<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');

        Schema::table($rolesTable, function (Blueprint $table) {
            $table->string('descripcion', 500)->nullable()->after('guard_name');
        });

        Schema::create('role_notification_preferences', function (Blueprint $table) use ($rolesTable) {
            $table->id();
            $table->foreignId('role_id')->unique()->constrained($rolesTable)->cascadeOnDelete();
            $table->boolean('receive_all')->default(false);
            $table->json('topics')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_notification_preferences');

        Schema::table(config('permission.table_names.roles', 'roles'), function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
