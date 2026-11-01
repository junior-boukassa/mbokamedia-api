<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->json('additional_images')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('additional_images');
        });
    }
};
