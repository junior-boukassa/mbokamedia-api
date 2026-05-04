<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('otp_code_hash')->nullable()->after('password');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code_hash');
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_expires_at');
            $table->boolean('must_change_password')->default(false)->after('otp_attempts')->index();
            $table->timestamp('temporary_password_set_at')->nullable()->after('must_change_password');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('is_sponsored')->default(false)->after('is_featured')->index();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('is_sponsored');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'otp_code_hash',
                'otp_expires_at',
                'otp_attempts',
                'must_change_password',
                'temporary_password_set_at',
            ]);
        });
    }
};
