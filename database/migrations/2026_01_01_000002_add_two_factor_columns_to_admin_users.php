<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Добавляет 2FA-колонки к таблице admin_users.
 *
 * `two_factor_secret` и `two_factor_recovery_codes` хранятся encrypted
 * через Eloquent cast (см. AdminUser::casts()).
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('admin.auth.table', 'admin_users');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->text('two_factor_secret')->nullable()->after('password');
            $blueprint->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $blueprint->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        $table = (string) config('admin.auth.table', 'admin_users');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
