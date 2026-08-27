<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_notifications', function (Blueprint $table): void {
            // Distinct de `email_sent_at` : un envoi d'e-mails en échec ne doit
            // pas empêcher le push, ni l'inverse.
            $table->timestamp('push_sent_at')->nullable()->after('email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('article_notifications', function (Blueprint $table): void {
            $table->dropColumn('push_sent_at');
        });
    }
};
