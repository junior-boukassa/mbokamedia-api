<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            // Un token FCM dépasse largement 255 caractères : `text`, et l'index
            // unique porte sur un préfixe via une colonne de hachage.
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->string('platform', 16)->index();
            $table->string('app_version', 32)->nullable();
            $table->string('locale', 16)->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
