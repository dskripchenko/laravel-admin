<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

it('creates an admin via non-interactive arguments', function (): void {
    $this->artisan('admin:user', [
        'name' => 'Test Admin',
        'email' => 'admin@test.local',
        'password' => 'super-secret-password',
    ])->assertSuccessful();

    $admin = AdminUser::where('email', 'admin@test.local')->first();

    expect($admin)->not->toBeNull();
    expect($admin->name)->toBe('Test Admin');
    expect($admin->is_active)->toBeTrue();
    expect(Hash::check('super-secret-password', $admin->password))->toBeTrue();
});

it('reports the Super Admin role assignment with --super', function (): void {
    $this->artisan('admin:user', [
        'name' => 'Super',
        'email' => 'super@test.local',
        'password' => 'super-secret-password',
        '--super' => true,
    ])
        ->expectsOutputToContain('Super Admin')
        ->assertSuccessful();
});

it('fails on duplicate email', function (): void {
    AdminUser::create([
        'name' => 'Existing',
        'email' => 'dup@test.local',
        'password' => 'hashed',
    ]);

    $this->artisan('admin:user', [
        'name' => 'Other',
        'email' => 'dup@test.local',
        'password' => 'super-secret-password',
    ])->assertFailed();
});

it('assigns the system Super Admin role with --super', function (): void {
    $this->artisan('admin:user', [
        'name' => 'Root', 'email' => 'root-super@example.com', 'password' => 'secret-pass',
        '--super' => true,
    ])->assertExitCode(0);

    $admin = AdminUser::where('email', 'root-super@example.com')->firstOrFail();
    expect($admin->getAllPermissions())->toContain('*');

    $role = Dskripchenko\LaravelAdmin\Permission\Models\Role::where('slug', 'super-admin')->firstOrFail();
    expect($role->is_system)->toBeTrue();

    // Идемпотентность роли: второй запуск не создаёт дубль.
    $this->artisan('admin:user', [
        'name' => 'Root2', 'email' => 'root-super2@example.com', 'password' => 'secret-pass',
        '--super' => true,
    ])->assertExitCode(0);
    expect(Dskripchenko\LaravelAdmin\Permission\Models\Role::where('slug', 'super-admin')->count())->toBe(1);
});
