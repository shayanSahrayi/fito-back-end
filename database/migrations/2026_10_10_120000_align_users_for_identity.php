<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Point users at phone and national code.
     * The original Laravel migration is left in place.
     */
    public function up(): void
    {
        if (DB::table('users')->count() > 0) {
            throw new RuntimeException(
                'users already has rows. Refusing to drop email and require phone without an explicit data migration.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->char('phone', 11)->unique();
            $table->char('national_code', 10)->unique();
            $table->boolean('password_is_initial')->default(false);
            $table->string('status', 32)->default('active');
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['name', 'email', 'email_verified_at', 'remember_token']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->dropUnique(['phone']);
            $table->dropUnique(['national_code']);
            $table->dropColumn([
                'phone',
                'national_code',
                'password_is_initial',
                'status',
                'suspended_at',
                'last_login_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
