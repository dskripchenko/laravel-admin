<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Auth\AdminGuardRegistrar;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Illuminate\Config\Repository as ConfigRepository;

it('registers admin guard, provider and password broker in dedicated mode', function (): void {
    $config = new ConfigRepository([
        'admin' => [
            'auth' => [
                'strategy' => 'dedicated',
                'guard' => 'admin',
                'provider' => 'admin_users',
                'password_broker' => 'admin_users',
                'model' => AdminUser::class,
                'table' => 'admin_users',
            ],
        ],
        'auth' => ['guards' => [], 'providers' => [], 'passwords' => []],
    ]);

    (new AdminGuardRegistrar($config))->register();

    expect($config->get('auth.guards.admin'))->toBe([
        'driver' => 'session',
        'provider' => 'admin_users',
    ]);
    expect($config->get('auth.providers.admin_users'))->toBe([
        'driver' => 'eloquent',
        'model' => AdminUser::class,
        'table' => 'admin_users',
    ]);
    expect($config->get('auth.passwords.admin_users.provider'))->toBe('admin_users');
    expect($config->get('auth.passwords.admin_users.table'))->toBe('admin_password_resets');
});

it('does not touch auth config in shared mode', function (): void {
    $config = new ConfigRepository([
        'admin' => ['auth' => ['strategy' => 'shared', 'guard' => 'web']],
        'auth' => ['guards' => [], 'providers' => [], 'passwords' => []],
    ]);

    (new AdminGuardRegistrar($config))->register();

    expect($config->get('auth.guards'))->toBe([]);
    expect($config->get('auth.providers'))->toBe([]);
    expect($config->get('auth.passwords'))->toBe([]);
});

it('does not overwrite existing guards/providers/passwords', function (): void {
    $existing = ['driver' => 'session', 'provider' => 'something_else'];

    $config = new ConfigRepository([
        'admin' => [
            'auth' => [
                'strategy' => 'dedicated',
                'guard' => 'admin',
                'provider' => 'admin_users',
                'password_broker' => 'admin_users',
                'model' => AdminUser::class,
                'table' => 'admin_users',
            ],
        ],
        'auth' => [
            'guards' => ['admin' => $existing],
            'providers' => [],
            'passwords' => [],
        ],
    ]);

    (new AdminGuardRegistrar($config))->register();

    expect($config->get('auth.guards.admin'))->toBe($existing);
});
