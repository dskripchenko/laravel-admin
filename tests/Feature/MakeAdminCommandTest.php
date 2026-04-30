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

it('warns about --super flag being not implemented yet', function (): void {
    $this->artisan('admin:user', [
        'name' => 'Super',
        'email' => 'super@test.local',
        'password' => 'super-secret-password',
        '--super' => true,
    ])
        ->expectsOutputToContain('пока не реализована')
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
