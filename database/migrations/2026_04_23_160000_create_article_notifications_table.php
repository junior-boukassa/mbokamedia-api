<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->string('featured_image')->nullable();
            $table->timestamp('published_at')->index();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();

            $table->unique('article_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_notifications');
    }
};
