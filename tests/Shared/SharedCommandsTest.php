<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Support\PrebuiltAssets;
use Dskripchenko\LaravelAdmin\Tests\Fixtures\Shared\TestSharedUser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

afterEach(function (): void {
    File::deleteDirectory(PrebuiltAssets::publishedPath());
    File::delete(config_path('admin.php'));
    // admin:install --shared publishes both the package's migrations and the shared one.
    foreach ([...File::files(__DIR__.'/../../database/migrations'), ...File::files(__DIR__.'/../../database/shared-migrations')] as $migration) {
        File::delete(database_path('migrations/'.$migration->getFilename()));
    }
});

it('creates an administrator on a users table without the admin columns', function (): void {
    // A stock users table: no is_active, locale or theme.
    $this->runSharedMigration('down');

    $this->artisan('admin:user', ['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'secret-pass', '--super' => true])
        ->assertSuccessful();

    $user = TestSharedUser::query()->where('email', 'alice@example.com')->firstOrFail();
    expect(Hash::check('secret-pass', $user->password))->toBeTrue()
        ->and($user->getAllPermissions())->toBe(['*']);
});

it('grants Super Admin to an existing site user by email alone', function (): void {
    $user = TestSharedUser::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret-pass']);

    $this->artisan('admin:user', ['name' => 'bob@example.com', '--super' => true])
        ->expectsOutputToContain('already exists')
        ->assertSuccessful();

    expect($user->fresh()->getAllPermissions())->toBe(['*'])
        ->and(TestSharedUser::query()->count())->toBe(1);
});

it('refuses to create a second user with a taken email without --super', function (): void {
    TestSharedUser::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret-pass']);

    $this->artisan('admin:user', ['name' => 'Other', 'email' => 'bob@example.com', 'password' => 'secret-pass'])
        ->assertFailed();
});

it('points the config at the host guard with admin:install --shared', function (): void {
    config()->set('auth.providers.users.model', TestSharedUser::class);

    $this->artisan('admin:install', ['--shared' => true, '--no-migrate' => true, '--no-user' => true, '--no-composer-hook' => true])
        ->assertSuccessful();

    $config = File::get(config_path('admin.php'));
    expect($config)->toContain("env('ADMIN_AUTH_STRATEGY', 'shared')")
        ->toContain("env('ADMIN_GUARD', 'web')")
        ->toContain("env('ADMIN_PROVIDER', 'users')")
        ->toContain("'model' => \\".TestSharedUser::class.'::class,')
        ->toContain("'password_broker' => 'users',")
        // The admin's own table stays named: its migration runs in any strategy.
        ->toContain("'table' => 'admin_users',");

    expect(File::exists(database_path('migrations/2026_01_01_000100_add_admin_columns_to_users_table.php')))->toBeTrue();

    // The published file is valid PHP that evaluates to the same settings.
    $evaluated = require config_path('admin.php');
    expect($evaluated['auth']['model'])->toBe(TestSharedUser::class);
});
