<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reel_transcodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->index();
            $table->string('source_path', 2048);
            $table->string('target_status');
            $table->timestamp('target_published_at')->nullable();
            $table->boolean('target_notify_on_publish')->default(true);
            $table->uuid('lock_token')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reel_transcodes');
    }
};
