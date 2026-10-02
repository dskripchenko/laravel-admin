<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Auth\TwoFactor\Base32;
use Dskripchenko\LaravelAdmin\Auth\TwoFactor\TwoFactorPolicy;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;

function policyUser(string $roleSlug = 'editor'): AdminUser
{
    $user = AdminUser::create(['name' => 'U', 'email' => 'u-'.uniqid().'@example.com', 'password' => 'secret']);
    $user->assignRole(Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => $roleSlug, 'permissions' => ['*']]));

    return $user->refresh();
}

it('refuses to set 2FA up when it is switched off', function (): void {
    config()->set('admin.auth.two_factor.enabled', false);
    $this->actingAs(policyUser(), 'admin');

    $this->getJson('/api/admin/profile/twoFactorStatus')
        ->assertOk()
        ->assertJsonPath('payload.available', false)
        ->assertJsonPath('payload.required', false);
    $this->postJson('/api/admin/profile/twoFactorEnable')
        ->assertForbidden()
        ->assertJsonPath('payload.errorKey', 'two_factor_disabled');
    $this->postJson('/api/admin/profile/twoFactorConfirm', ['code' => '123456'])->assertForbidden();
    $this->getJson('/api/admin/system/bootstrap')->assertJsonPath('payload.config.twoFactor.enabled', false);
});

it('still lets an enrolled user switch 2FA off when the feature is off', function (): void {
    config()->set('admin.auth.two_factor.enabled', false);
    $user = policyUser();
    $user->forceFill([
        'two_factor_secret' => Base32::generateSecret(),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $this->actingAs($user, 'admin');

    $this->postJson('/api/admin/profile/twoFactorDisable', ['password' => 'secret'])->assertOk();
});

it('keeps an enforced role on the profile until 2FA is on', function (): void {
    config()->set('admin.auth.two_factor.enforce_for', ['editor']);
    $user = policyUser('editor');
    $this->actingAs($user, 'admin');

    expect(TwoFactorPolicy::setupPending($user))->toBeTrue();

    $this->getJson('/api/admin/system/me')
        ->assertOk()
        ->assertJsonPath('payload.twoFactorRequired', true);
    $this->getJson('/api/admin/profile/twoFactorStatus')->assertOk()->assertJsonPath('payload.required', true);
    $this->getJson('/api/admin/audit/list')
        ->assertForbidden()
        ->assertJsonPath('payload.errorKey', 'two_factor_setup_required');

    $user->forceFill([
        'two_factor_secret' => Base32::generateSecret(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->getJson('/api/admin/audit/list')->assertJsonMissing(['errorKey' => 'two_factor_setup_required']);
});

it('leaves the other roles alone, and enforces everyone with *', function (): void {
    config()->set('admin.auth.two_factor.enforce_for', ['security']);
    $user = policyUser('editor');

    expect(TwoFactorPolicy::requiredFor($user))->toBeFalse();

    config()->set('admin.auth.two_factor.enforce_for', ['*']);
    expect(TwoFactorPolicy::requiredFor($user))->toBeTrue();

    // A switched-off feature enforces nothing.
    config()->set('admin.auth.two_factor.enabled', false);
    expect(TwoFactorPolicy::requiredFor($user))->toBeFalse();
});
