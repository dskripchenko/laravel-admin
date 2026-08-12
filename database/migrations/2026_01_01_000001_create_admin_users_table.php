<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the base admin_users table.
 *
 * It is published only when `config('admin.auth.strategy')` is 'dedicated'; in
 * the shared mode the admin uses the host project's existing users table.
 *
 * The 2FA columns are added by a separate migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('admin.auth.table', 'admin_users');

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();

            $blueprint->string('name');
            $blueprint->string('email')->unique();
            $blueprint->string('password');
            $blueprint->rememberToken();
            $blueprint->timestamp('email_verified_at')->nullable();

            $blueprint->string('locale', 8)->nullable();
            $blueprint->string('theme', 16)->nullable();

            $blueprint->timestamp('last_login_at')->nullable();
            $blueprint->string('last_login_ip', 45)->nullable();

            $blueprint->boolean('is_active')->default(true)->index();

            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        $table = (string) config('admin.auth.table', 'admin_users');
        Schema::dropIfExists($table);
    }
};
