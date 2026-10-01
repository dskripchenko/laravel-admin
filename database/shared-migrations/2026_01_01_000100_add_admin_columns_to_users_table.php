<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shared strategy: adds the columns the admin reads and writes to the
 * host's own users table — the table of config('admin.auth.model').
 *
 *   locale, theme            the administrator's choices in the panel
 *   is_active                switches an account off (null/true lets it in)
 *   last_login_at, _ip       written at every admin login
 *   two_factor_*             the admin's 2FA (HasAdminTwoFactor casts them)
 *
 * A column the table already has is left alone. Rolling back drops the
 * listed columns: if your table had some of them before, delete them from
 * down() first.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = $this->usersTable();

        foreach ($this->columns() as $column => $define) {
            if (Schema::hasColumn($table, $column)) {
                continue;
            }
            Schema::table($table, static function (Blueprint $blueprint) use ($define): void {
                $define($blueprint);
            });
        }
    }

    public function down(): void
    {
        $table = $this->usersTable();

        foreach (array_keys($this->columns()) as $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, static function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropColumn($column);
                });
            }
        }
    }

    /**
     * @return array<string, Closure(Blueprint): void>
     */
    private function columns(): array
    {
        return [
            'locale' => static function (Blueprint $t): void {
                $t->string('locale', 8)->nullable();
            },
            'theme' => static function (Blueprint $t): void {
                $t->string('theme', 16)->nullable();
            },
            'is_active' => static function (Blueprint $t): void {
                $t->boolean('is_active')->default(true);
            },
            'last_login_at' => static function (Blueprint $t): void {
                $t->timestamp('last_login_at')->nullable();
            },
            'last_login_ip' => static function (Blueprint $t): void {
                $t->string('last_login_ip', 45)->nullable();
            },
            'two_factor_secret' => static function (Blueprint $t): void {
                $t->text('two_factor_secret')->nullable();
            },
            'two_factor_recovery_codes' => static function (Blueprint $t): void {
                $t->text('two_factor_recovery_codes')->nullable();
            },
            'two_factor_confirmed_at' => static function (Blueprint $t): void {
                $t->timestamp('two_factor_confirmed_at')->nullable();
            },
        ];
    }

    private function usersTable(): string
    {
        $model = (string) config('admin.auth.model', '');

        if ($model !== '' && is_subclass_of($model, Model::class)) {
            return (new $model)->getTable();
        }

        return 'users';
    }
};
