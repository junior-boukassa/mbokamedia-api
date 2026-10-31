<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('briefings', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->date('edition_date')->index();
            $table->string('presenter');
            $table->longText('text');
            $table->string('status')->default('draft')->index();
            $table->boolean('notify_readers')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->string('audio_path')->nullable();
            $table->unsignedInteger('audio_duration_seconds')->nullable();
            $table->unsignedBigInteger('audio_size_bytes')->nullable();
            $table->string('audio_status')->default('missing')->index();
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('article_briefing', function (Blueprint $table): void {
            $table->foreignId('briefing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->primary(['briefing_id', 'article_id']);
        });

        Schema::create('briefing_audio_transcodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('briefing_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->index();
            $table->string('source_path');
            $table->string('previous_audio_path')->nullable();
            $table->string('target_status')->default('draft');
            $table->boolean('target_notify_readers')->default(false);
            $table->string('lock_token')->nullable()->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('briefing_audio_transcodes');
        Schema::dropIfExists('article_briefing');
        Schema::dropIfExists('briefings');
    }
};
