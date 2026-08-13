<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;

function makeSessionAdmin(): AdminUser
{
    return AdminUser::create([
        'name' => 'S', 'email' => 'sess-'.uniqid().'@example.com', 'password' => 'initial-pass',
    ]);
}

it('invalidates the session when the password changes elsewhere', function (): void {
    $admin = makeSessionAdmin();

    $this->postJson('/api/admin/auth/login', [
        'email' => $admin->email, 'password' => 'initial-pass',
    ])->assertOk();

    // The first request stores the hash in the session.
    $this->getJson('/api/admin/system/me')->assertOk();

    // The password is changed outside this session (an admin through the resource, or tinker).
    $admin->forceFill(['password' => 'brand-new-pass'])->save();
    // In tests the guard caches the user in the process's memory — we reset it
    // the way it would happen between real HTTP requests.
    app('auth')->forgetGuards();

    $r = $this->getJson('/api/admin/system/me');
    $r->assertStatus(401);
    expect($r->json('payload.errorKey'))->toBe('session_expired');
});

it('keeps the own session alive after profile password change', function (): void {
    $admin = makeSessionAdmin();

    $this->postJson('/api/admin/auth/login', [
        'email' => $admin->email, 'password' => 'initial-pass',
    ])->assertOk();
    $this->getJson('/api/admin/system/me')->assertOk();

    $this->postJson('/api/admin/profile/changePassword', [
        'current_password' => 'initial-pass',
        'password' => 'brand-new-pass',
        'password_confirmation' => 'brand-new-pass',
    ])->assertOk();

    // Our own session is alive.
    $this->getJson('/api/admin/system/me')->assertOk();
});

it('cuts access on the next request when the account is deactivated', function (): void {
    $admin = makeSessionAdmin();

    $this->postJson('/api/admin/auth/login', [
        'email' => $admin->email, 'password' => 'initial-pass',
    ])->assertOk();
    $this->getJson('/api/admin/system/me')->assertOk();

    $admin->forceFill(['is_active' => false])->save();
    app('auth')->forgetGuards();

    $r = $this->getJson('/api/admin/system/me');
    $r->assertStatus(403);
    expect($r->json('payload.errorKey'))->toBe('account_inactive');
});
