<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests;

use Dskripchenko\LaravelAdmin\Tests\Fixtures\Shared\TestSharedUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shared strategy: the administrators are the host's own `users`, signed
 * in through the host's `web` guard — what `admin:install --shared` sets up.
 *
 * The users table is Laravel's stock one; the admin columns come from the
 * migration the package publishes for this strategy, run here as is.
 */
abstract class SharedStrategyTestCase extends TestCase
{
    /** @var class-string */
    protected string $userModel = TestSharedUser::class;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.providers.users', ['driver' => 'eloquent', 'model' => $this->userModel]);
        $app['config']->set('auth.passwords.users', [
            'provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60,
        ]);

        $app['config']->set('admin.auth.strategy', 'shared');
        $app['config']->set('admin.auth.guard', 'web');
        $app['config']->set('admin.auth.provider', 'users');
        $app['config']->set('admin.auth.model', $this->userModel);
        $app['config']->set('admin.auth.password_broker', 'users');
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        $this->runSharedMigration('up');
    }

    protected function runSharedMigration(string $direction): void
    {
        $migration = require dirname(__DIR__).'/database/shared-migrations/2026_01_01_000100_add_admin_columns_to_users_table.php';
        $migration->{$direction}();
    }
}
