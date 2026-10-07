<?php

use App\Support\Database\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notificaciones internas (campana). InnoDB explícito; idempotente ante un intento fallido. */
return new class extends Migration
{
    public function up(): void
    {
        SafeMigration::createTable('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        SafeMigration::assertCanDiscard(['notifications' => null], 'notificaciones de los usuarios');

        Schema::dropIfExists('notifications');
    }
};
