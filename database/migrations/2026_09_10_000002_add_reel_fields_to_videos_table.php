<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table): void {
            if (! Schema::hasColumn('videos', 'is_reel')) {
                $table->boolean('is_reel')->default(false)->index();
            }

            if (! Schema::hasColumn('videos', 'youtube_url')) {
                $table->string('youtube_url')->nullable();
            }

            if (! Schema::hasColumn('videos', 'external_url')) {
                $table->string('external_url')->nullable();
            }

            if (! Schema::hasColumn('videos', 'views_count')) {
                $table->unsignedBigInteger('views_count')->default(0)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table): void {
            if (Schema::hasColumn('videos', 'is_reel')) {
                $table->dropColumn('is_reel');
            }

            if (Schema::hasColumn('videos', 'youtube_url')) {
                $table->dropColumn('youtube_url');
            }

            if (Schema::hasColumn('videos', 'external_url')) {
                $table->dropColumn('external_url');
            }

            if (Schema::hasColumn('videos', 'views_count')) {
                $table->dropColumn('views_count');
            }
        });
    }
};
