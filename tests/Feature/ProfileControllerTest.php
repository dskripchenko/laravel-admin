<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Auth\TwoFactor\Base32;
use Dskripchenko\LaravelAdmin\Auth\TwoFactor\TotpGenerator;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->admin = AdminUser::create([
        'name' => 'Profile User',
        'email' => 'profile@example.com',
        'password' => 'super-secret',
    ]);
    $this->actingAs($this->admin, 'admin');
});

it('profile.show returns current user with locales/themes/2fa state', function (): void {
    $response = $this->getJson('/api/admin/profile/show');

    $response->assertOk();
    expect($response->json('payload.user.email'))->toBe('profile@example.com');
    expect($response->json('payload.available_locales'))->toBeArray();
    expect($response->json('payload.available_themes'))->toBe(['light', 'dark']);
    expect($response->json('payload.two_factor.enabled'))->toBeFalse();
    expect($response->json('payload.api_tokens_enabled'))->toBeFalse();
});

it('profile.update changes name/locale/theme', function (): void {
    $response = $this->postJson('/api/admin/profile/update', [
        'name' => 'Updated Name',
        'locale' => 'ru',
        'theme' => 'dark',
    ]);

    $response->assertOk();
    expect($response->json('payload.user.name'))->toBe('Updated Name');
    expect($response->json('payload.user.locale'))->toBe('ru');
    expect($response->json('payload.user.theme'))->toBe('dark');
});

it('profile.update validates locale against available_locales', function (): void {
    $response = $this->postJson('/api/admin/profile/update', [
        'locale' => 'jp', // не в available_locales
    ]);

    $response->assertStatus(422);
});

it('profile.update with new email invalidates email_verified_at', function (): void {
    $this->admin->forceFill(['email_verified_at' => now()])->save();

    $this->postJson('/api/admin/profile/update', [
        'email' => 'newprofile@example.com',
    ])->assertOk();

    $fresh = $this->admin->fresh();
    expect($fresh->email)->toBe('newprofile@example.com');
    expect($fresh->email_verified_at)->toBeNull();
});

it('profile.update rejects email that already taken by another admin', function (): void {
    AdminUser::create([
        'name' => 'Other',
        'email' => 'taken@example.com',
        'password' => 'pwd',
    ]);

    $response = $this->postJson('/api/admin/profile/update', [
        'email' => 'taken@example.com',
    ]);

    $response->assertStatus(422);
});

it('profile.changePassword changes when current_password matches', function (): void {
    $response = $this->postJson('/api/admin/profile/changePassword', [
        'current_password' => 'super-secret',
        'password' => 'new-stronger-password',
        'password_confirmation' => 'new-stronger-password',
    ]);

    $response->assertOk();
    expect(Hash::check('new-stronger-password', $this->admin->fresh()->password))->toBeTrue();
});

it('profile.changePassword rejects wrong current_password with 422', function (): void {
    $response = $this->postJson('/api/admin/profile/changePassword', [
        'current_password' => 'wrong-old',
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);

    $response->assertStatus(422);
    expect($response->json('payload.messages'))->toHaveKey('current_password');
});

it('profile.changePassword validates length and confirmation', function (): void {
    $this->postJson('/api/admin/profile/changePassword', [
        'current_password' => 'super-secret',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertStatus(422);

    $this->postJson('/api/admin/profile/changePassword', [
        'current_password' => 'super-secret',
        'password' => 'long-enough',
        'password_confirmation' => 'mismatched',
    ])->assertStatus(422);
});

it('profile.twoFactorEnable returns secret + recovery codes and sets pending state', function (): void {
    $response = $this->postJson('/api/admin/profile/twoFactorEnable');

    $response->assertOk();
    expect($response->json('payload.secret'))->toBeString();
    expect($response->json('payload.qr_uri'))->toStartWith('otpauth://totp/');
    expect($response->json('payload.recovery_codes'))->toHaveCount(8);

    $fresh = $this->admin->fresh();
    expect($fresh->two_factor_secret)->not->toBeNull();
    expect($fresh->two_factor_confirmed_at)->toBeNull(); // pending
});

it('profile.twoFactorConfirm with valid code marks confirmed_at', function (): void {
    $secret = Base32::generateSecret();
    $this->admin->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => ['xx-yy'],
    ])->save();

    $code = TotpGenerator::code($secret);
    $response = $this->postJson('/api/admin/profile/twoFactorConfirm', ['code' => $code]);

    $response->assertOk();
    expect($response->json('payload.enabled'))->toBeTrue();
    expect($this->admin->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('profile.twoFactorConfirm rejects invalid code with 422 invalid_two_factor_code', function (): void {
    $this->admin->forceFill([
        'two_factor_secret' => Base32::generateSecret(),
        'two_factor_recovery_codes' => [],
    ])->save();

    $response = $this->postJson('/api/admin/profile/twoFactorConfirm', ['code' => '000000']);

    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('invalid_two_factor_code');
});

it('profile.twoFactorConfirm fails when no secret was issued', function (): void {
    $response = $this->postJson('/api/admin/profile/twoFactorConfirm', ['code' => '123456']);

    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('two_factor_not_initialised');
});

it('profile.twoFactorDisable wipes 2FA state on correct password', function (): void {
    $this->admin->forceFill([
        'two_factor_secret' => 'sec',
        'two_factor_recovery_codes' => ['a-b'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->postJson('/api/admin/profile/twoFactorDisable', [
        'password' => 'super-secret',
    ]);

    $response->assertOk();
    $fresh = $this->admin->fresh();
    expect($fresh->two_factor_secret)->toBeNull();
    expect($fresh->two_factor_confirmed_at)->toBeNull();
});

it('profile.twoFactorDisable rejects wrong password', function (): void {
    $this->admin->forceFill([
        'two_factor_secret' => 'sec',
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->postJson('/api/admin/profile/twoFactorDisable', [
        'password' => 'wrong',
    ]);

    $response->assertStatus(422);
});

it('profile.twoFactorRegenerateCodes replaces recovery_codes', function (): void {
    $this->admin->forceFill([
        'two_factor_secret' => 'sec',
        'two_factor_recovery_codes' => ['old01-old02'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->postJson('/api/admin/profile/twoFactorRegenerateCodes', [
        'password' => 'super-secret',
    ]);

    $response->assertOk();
    $newCodes = $response->json('payload.recovery_codes');
    expect($newCodes)->toHaveCount(8);
    expect($this->admin->fresh()->two_factor_recovery_codes)->toBe($newCodes);
});

it('profile.* requires authentication', function (): void {
    $this->app['auth']->guard('admin')->logout();

    $this->getJson('/api/admin/profile/show')->assertStatus(401);
    $this->postJson('/api/admin/profile/update', ['name' => 'X'])->assertStatus(401);
});

it('profile.twoFactorStatus reports recovery_codes_remaining', function (): void {
    $this->admin->forceFill([
        'two_factor_secret' => 'sec',
        'two_factor_recovery_codes' => ['a-b', 'c-d', 'e-f'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->getJson('/api/admin/profile/twoFactorStatus');

    $response->assertOk();
    expect($response->json('payload.enabled'))->toBeTrue();
    expect($response->json('payload.recovery_codes_remaining'))->toBe(3);
});
