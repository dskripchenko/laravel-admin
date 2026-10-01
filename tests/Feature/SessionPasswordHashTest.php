<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Auth\SessionPasswordHash;
use Dskripchenko\LaravelAdmin\Models\AdminUser;

it('accepts the fingerprint in the guard format and the legacy raw hash', function (): void {
    $hash = password_hash('secret-pass', PASSWORD_BCRYPT);

    expect(SessionPasswordHash::matches('admin', $hash, SessionPasswordHash::make('admin', $hash)))->toBeTrue()
        ->and(SessionPasswordHash::matches('admin', $hash, $hash))->toBeTrue()
        ->and(SessionPasswordHash::matches('admin', $hash, password_hash('other', PASSWORD_BCRYPT)))->toBeFalse();
});

it('keeps a session opened before the upgrade, when it stored the raw hash', function (): void {
    $admin = AdminUser::create(['name' => 'A', 'email' => 'legacy@example.com', 'password' => 'initial-pass']);

    $this->postJson('/api/admin/auth/login', [
        'email' => $admin->email, 'password' => 'initial-pass',
    ])->assertOk();
    session()->put(SessionPasswordHash::key('admin'), (string) $admin->fresh()->getAuthPassword());

    $this->getJson('/api/admin/system/me')->assertOk();
});
