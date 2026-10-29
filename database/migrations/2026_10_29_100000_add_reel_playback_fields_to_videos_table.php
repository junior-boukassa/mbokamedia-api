<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table): void {
            if (! Schema::hasColumn('videos', 'duration_seconds')) {
                $table->unsignedInteger('duration_seconds')->nullable();
            }

            if (! Schema::hasColumn('videos', 'width')) {
                $table->unsignedInteger('width')->nullable();
            }

            if (! Schema::hasColumn('videos', 'height')) {
                $table->unsignedInteger('height')->nullable();
            }

            if (! Schema::hasColumn('videos', 'notify_on_publish')) {
                $table->boolean('notify_on_publish')->default(true);
            }

            if (! Schema::hasColumn('videos', 'push_sent_at')) {
                $table->timestamp('push_sent_at')->nullable();
            }
        });

        // Les reels déjà publiés ne doivent jamais être « annoncés » : sans ce
        // marquage, `notify_on_publish` vaut vrai pour eux et la première
        // modification d'un vieux reel dans le dashboard enverrait une
        // notification à tous les lecteurs.
        DB::table('videos')
            ->where('is_reel', true)
            ->where('status', 'published')
            ->whereNull('push_sent_at')
            ->update(['push_sent_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table): void {
            foreach (['duration_seconds', 'width', 'height', 'notify_on_publish', 'push_sent_at'] as $column) {
                if (Schema::hasColumn('videos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
