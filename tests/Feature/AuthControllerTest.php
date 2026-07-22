<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

it('logs in with valid credentials', function (): void {
    $admin = AdminUser::create([
        'name' => 'Login User',
        'email' => 'login@example.com',
        'password' => 'super-secret',
    ]);

    $response = $this->postJson('/api/admin/auth/login', [
        'email' => 'login@example.com',
        'password' => 'super-secret',
    ]);

    $response->assertOk();
    expect($response->json('success'))->toBeTrue();
    expect($response->json('payload.user.id'))->toBe($admin->id);
    expect($response->json('payload.user.email'))->toBe('login@example.com');
    expect($response->json('payload.redirect_url'))->toBe('/admin');
    expect($this->app['auth']->guard('admin')->check())->toBeTrue();
});

it('updates last_login_at on successful login', function (): void {
    AdminUser::create([
        'name' => 'LL User',
        'email' => 'll@example.com',
        'password' => 'super-secret',
    ]);

    $this->postJson('/api/admin/auth/login', [
        'email' => 'll@example.com',
        'password' => 'super-secret',
    ])->assertOk();

    $admin = AdminUser::where('email', 'll@example.com')->first();
    expect($admin->last_login_at)->not->toBeNull();
});

it('rejects invalid credentials with 401 + invalid_credentials', function (): void {
    AdminUser::create([
        'name' => 'X',
        'email' => 'x@example.com',
        'password' => 'right',
    ]);

    $response = $this->postJson('/api/admin/auth/login', [
        'email' => 'x@example.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(401);
    expect($response->json('payload.errorKey'))->toBe('invalid_credentials');
});

it('rejects non-existent email with 401', function (): void {
    $response = $this->postJson('/api/admin/auth/login', [
        'email' => 'nope@example.com',
        'password' => 'whatever',
    ]);

    $response->assertStatus(401);
});

it('refuses inactive accounts with 403 account_inactive', function (): void {
    AdminUser::create([
        'name' => 'Banned',
        'email' => 'banned@example.com',
        'password' => 'right',
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/admin/auth/login', [
        'email' => 'banned@example.com',
        'password' => 'right',
    ]);

    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('account_inactive');
});

it('validates required fields with 422', function (): void {
    $response = $this->postJson('/api/admin/auth/login', []);

    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('validation');
    expect($response->json('payload.messages'))->toHaveKey('email');
    expect($response->json('payload.messages'))->toHaveKey('password');
});

it('logs out the current admin', function (): void {
    $admin = AdminUser::create([
        'name' => 'Out',
        'email' => 'out@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $response = $this->postJson('/api/admin/auth/logout');
    $response->assertOk();
    expect($this->app['auth']->guard('admin')->check())->toBeFalse();
});

it('forgotPassword always returns success even for unknown email', function (): void {
    $response = $this->postJson('/api/admin/auth/forgotPassword', [
        'email' => 'unknown@example.com',
    ]);

    $response->assertOk();
    expect($response->json('payload.message'))->toContain('email');
});

it('forgotPassword validates email format', function (): void {
    $response = $this->postJson('/api/admin/auth/forgotPassword', [
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422);
});

it('resetPassword validates password confirmation', function (): void {
    $response = $this->postJson('/api/admin/auth/resetPassword', [
        'email' => 'x@example.com',
        'token' => 'whatever',
        'password' => 'short',
        'password_confirmation' => 'mismatch',
    ]);

    $response->assertStatus(422);
});

it('resetPassword applies new password and auto-logins on valid token', function (): void {
    $admin = AdminUser::create([
        'name' => 'Resetter',
        'email' => 'reset@example.com',
        'password' => 'old-password',
    ]);

    // Issue real password reset token via our broker.
    $token = Illuminate\Support\Facades\Password::broker('admin_users')->createToken($admin);

    $response = $this->postJson('/api/admin/auth/resetPassword', [
        'email' => 'reset@example.com',
        'token' => $token,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertOk();
    expect($response->json('payload.user.email'))->toBe('reset@example.com');
    expect(Hash::check('new-password', $admin->fresh()->password))->toBeTrue();
});

it('resetPassword fails on invalid token with 422 + token error', function (): void {
    AdminUser::create([
        'name' => 'NoToken',
        'email' => 'notoken@example.com',
        'password' => 'old',
    ]);

    $response = $this->postJson('/api/admin/auth/resetPassword', [
        'email' => 'notoken@example.com',
        'token' => 'completely-invalid',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertStatus(422);
    expect($response->json('payload.messages'))->toHaveKey('token');
});

it('does not burn the login throttle with ordinary api traffic', function (): void {
    AdminUser::create([
        'name' => 'Throttle User',
        'email' => 'throttle@example.com',
        'password' => 'super-secret',
    ]);

    // Общий api-throttle (:60,1) инкрементит свой счётчик на каждый запрос;
    // до фикса он делил ключ с логинным ':5,1' и логин 429-ил после
    // нескольких ЛЮБЫХ api-запросов с того же IP.
    for ($i = 0; $i < 6; $i++) {
        $this->getJson('/api/admin/system/locales')->assertOk();
    }

    $this->postJson('/api/admin/auth/login', [
        'email' => 'throttle@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(401);

    $this->postJson('/api/admin/auth/login', [
        'email' => 'throttle@example.com',
        'password' => 'super-secret',
    ])->assertOk();
});

it('login consumes exactly one throttle hit per request', function (): void {
    // Дубль был: laravel-api вешает per-action middleware на route, а
    // RunActionMiddleware гонял их же вторым Pipeline'ом — каждый запрос
    // съедал 2+ попытки и 429 приходил на 3-м логине вместо 6-го.
    // TestCase глобально выключает throttle — здесь возвращаем.
    $this->withMiddleware(Illuminate\Routing\Middleware\ThrottleRequests::class);

    $key = 'auth-admin'.sha1('|127.0.0.1');
    Illuminate\Support\Facades\RateLimiter::clear($key);

    $this->postJson('/api/admin/auth/login', [
        'email' => 'nobody@example.com', 'password' => 'wrong',
    ])->assertStatus(401);

    expect(Illuminate\Support\Facades\RateLimiter::attempts($key))->toBe(1);
});
