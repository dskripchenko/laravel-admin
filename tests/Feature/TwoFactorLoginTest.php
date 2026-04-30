<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Auth\TwoFactor\Base32;
use Dskripchenko\LaravelAdmin\Auth\TwoFactor\TotpGenerator;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Cache;

it('login returns two_factor_required when 2FA is enabled', function (): void {
    $secret = Base32::generateSecret();
    AdminUser::create([
        'name' => '2FA User',
        'email' => '2fa@example.com',
        'password' => 'secret',
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => ['code1-aaaaaa', 'code2-bbbbbb'],
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->postJson('/api/admin/auth/login', [
        'email' => '2fa@example.com',
        'password' => 'secret',
    ]);

    $response->assertOk();
    expect($response->json('success'))->toBeFalse();
    expect($response->json('payload.errorKey'))->toBe('two_factor_required');
    expect($response->json('payload.challenge_token'))->toBeString();
    expect(strlen($response->json('payload.challenge_token')))->toBe(64);
    // Не залогинены до challenge'а.
    expect($this->app['auth']->guard('admin')->check())->toBeFalse();
});

it('login still succeeds when 2FA secret is set but not confirmed', function (): void {
    AdminUser::create([
        'name' => 'Pending 2FA',
        'email' => 'pending@example.com',
        'password' => 'secret',
        'two_factor_secret' => Base32::generateSecret(),
        // two_factor_confirmed_at IS NULL → 2FA не активна
    ]);

    $response = $this->postJson('/api/admin/auth/login', [
        'email' => 'pending@example.com',
        'password' => 'secret',
    ]);

    $response->assertOk();
    expect($response->json('success'))->toBeTrue();
    expect($response->json('payload.user.email'))->toBe('pending@example.com');
});

it('twoFactorChallenge: completes login on valid TOTP code', function (): void {
    $secret = Base32::generateSecret();
    AdminUser::create([
        'name' => 'TOTP User',
        'email' => 'totp@example.com',
        'password' => 'secret',
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => ['fake01-fake02'],
        'two_factor_confirmed_at' => now(),
    ]);

    $loginResponse = $this->postJson('/api/admin/auth/login', [
        'email' => 'totp@example.com',
        'password' => 'secret',
    ]);
    $token = $loginResponse->json('payload.challenge_token');

    $code = TotpGenerator::code($secret);
    $response = $this->postJson('/api/admin/auth/twoFactorChallenge', [
        'challenge_token' => $token,
        'code' => $code,
    ]);

    $response->assertOk();
    expect($response->json('success'))->toBeTrue();
    expect($response->json('payload.user.email'))->toBe('totp@example.com');
    expect($this->app['auth']->guard('admin')->check())->toBeTrue();
    // Challenge token инвалидируется.
    expect(Cache::get("admin:2fa:challenge:{$token}"))->toBeNull();
});

it('twoFactorChallenge: rejects invalid code with 401 invalid_two_factor_code', function (): void {
    $secret = Base32::generateSecret();
    AdminUser::create([
        'name' => 'Bad Code',
        'email' => 'bad@example.com',
        'password' => 'secret',
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => [],
        'two_factor_confirmed_at' => now(),
    ]);

    $loginResponse = $this->postJson('/api/admin/auth/login', [
        'email' => 'bad@example.com',
        'password' => 'secret',
    ]);
    $token = $loginResponse->json('payload.challenge_token');

    $response = $this->postJson('/api/admin/auth/twoFactorChallenge', [
        'challenge_token' => $token,
        'code' => '000000',
    ]);

    $response->assertStatus(401);
    expect($response->json('payload.errorKey'))->toBe('invalid_two_factor_code');
});

it('twoFactorChallenge: rejects expired/unknown challenge_token', function (): void {
    $response = $this->postJson('/api/admin/auth/twoFactorChallenge', [
        'challenge_token' => 'nonexistent',
        'code' => '123456',
    ]);

    $response->assertStatus(401);
    expect($response->json('payload.errorKey'))->toBe('challenge_expired');
});

it('twoFactorRecovery: consumes a recovery code and logs in', function (): void {
    $codes = ['aaaaaa-bbbbbb', 'cccccc-dddddd'];
    AdminUser::create([
        'name' => 'Recovery',
        'email' => 'rec@example.com',
        'password' => 'secret',
        'two_factor_secret' => Base32::generateSecret(),
        'two_factor_recovery_codes' => $codes,
        'two_factor_confirmed_at' => now(),
    ]);

    $loginResponse = $this->postJson('/api/admin/auth/login', [
        'email' => 'rec@example.com',
        'password' => 'secret',
    ]);
    $token = $loginResponse->json('payload.challenge_token');

    $response = $this->postJson('/api/admin/auth/twoFactorRecovery', [
        'challenge_token' => $token,
        'recovery_code' => 'aaaaaa-bbbbbb',
    ]);

    $response->assertOk();
    expect($response->json('payload.recovery_codes_remaining'))->toBe(1);
    expect($this->app['auth']->guard('admin')->check())->toBeTrue();

    // Использованный код удалён из БД.
    $admin = AdminUser::where('email', 'rec@example.com')->first();
    expect($admin->two_factor_recovery_codes)->toBe(['cccccc-dddddd']);
});

it('twoFactorRecovery: rejects invalid recovery_code with 401', function (): void {
    AdminUser::create([
        'name' => 'BadRecovery',
        'email' => 'badrec@example.com',
        'password' => 'secret',
        'two_factor_secret' => Base32::generateSecret(),
        'two_factor_recovery_codes' => ['aaaaaa-bbbbbb'],
        'two_factor_confirmed_at' => now(),
    ]);

    $loginResponse = $this->postJson('/api/admin/auth/login', [
        'email' => 'badrec@example.com',
        'password' => 'secret',
    ]);
    $token = $loginResponse->json('payload.challenge_token');

    $response = $this->postJson('/api/admin/auth/twoFactorRecovery', [
        'challenge_token' => $token,
        'recovery_code' => 'wrong-code',
    ]);

    $response->assertStatus(401);
    expect($response->json('payload.errorKey'))->toBe('invalid_recovery_code');
});

it('twoFactorChallenge: validates required fields with 422', function (): void {
    $response = $this->postJson('/api/admin/auth/twoFactorChallenge', []);
    $response->assertStatus(422);
});

it('AdminUser::hasTwoFactorEnabled returns false without confirmed_at', function (): void {
    $user = new AdminUser([
        'two_factor_secret' => 'foo',
    ]);
    expect($user->hasTwoFactorEnabled())->toBeFalse();
});

it('AdminUser::hasTwoFactorEnabled returns true with secret + confirmed_at', function (): void {
    $user = AdminUser::create([
        'name' => 'X',
        'email' => 'x2fa@example.com',
        'password' => 'secret',
        'two_factor_secret' => 'foo',
        'two_factor_confirmed_at' => now(),
    ]);
    expect($user->hasTwoFactorEnabled())->toBeTrue();
});
