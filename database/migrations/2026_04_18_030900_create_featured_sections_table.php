<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('headline')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedInteger('max_items')->default(6);
            $table->timestamps();
        });

        Schema::create('featured_section_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('featured_section_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('featureable');
            $table->string('manual_title')->nullable();
            $table->text('manual_excerpt')->nullable();
            $table->string('manual_url')->nullable();
            $table->string('manual_image')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_section_items');
        Schema::dropIfExists('featured_sections');
    }
};
