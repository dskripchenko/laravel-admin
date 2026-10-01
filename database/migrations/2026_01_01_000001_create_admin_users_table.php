<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the base admin_users table.
 *
 * It runs in either strategy. In the shared one the admin signs in the host's
 * own users instead, and this table simply stays empty — so `admin.auth.table`
 * must keep naming a table of its own there, never the host's `users`.
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
