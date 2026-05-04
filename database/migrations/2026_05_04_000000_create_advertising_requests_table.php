<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_requests', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('company_name');
            $table->string('website_url')->nullable();
            $table->string('article_title');
            $table->longText('description');
            $table->string('image_url')->nullable();
            $table->string('package_type', 40);
            $table->decimal('package_price', 10, 2);
            $table->text('message')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->string('payment_reference')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_requests');
    }
};
