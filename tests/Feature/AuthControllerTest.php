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

    // The shared api throttle (:60,1) increments its own counter on every
    // request; before the fix it shared a key with the login's ':5,1' and the
    // login 429'd after a handful of ANY api requests from the same IP.
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
    // There used to be a duplicate: laravel-api attaches the per-action
    // middleware to the route while RunActionMiddleware ran the very same ones
    // through a second pipeline — every request ate 2+ attempts and the 429 came
    // on the 3rd login instead of the 6th. The TestCase switches throttling off
    // globally — here we put it back.
    $this->withMiddleware(Illuminate\Routing\Middleware\ThrottleRequests::class);

    $key = 'auth-admin'.sha1('|127.0.0.1');
    Illuminate\Support\Facades\RateLimiter::clear($key);

    $this->postJson('/api/admin/auth/login', [
        'email' => 'nobody@example.com', 'password' => 'wrong',
    ])->assertStatus(401);

    expect(Illuminate\Support\Facades\RateLimiter::attempts($key))->toBe(1);
});

/*
 * BL-44: the user's shape is the same in the bootstrap and in the sign-in
 * response.
 *
 * The `serializeUser` docblock promises "the combined shape the SPA receives in
 * bootstrap/me/login", yet `locale` and `theme` were substituted there from the
 * config while BootstrapBuilder returns the raw values. The discrepancy was
 * visible to the eye: for a user with no saved locale the panel went to the
 * default language after a SIGN-IN and came back to the browser's language after
 * an F5. A full load received null and did not touch the language, a sign-in
 * received 'en' and accepted it.
 */

it('вход не выдаёт умолчание за выбор пользователя', function (): void {
    config(['admin.ui.default_locale' => 'en', 'admin.ui.default_theme' => 'dark']);
    $user = AdminUser::create([
        'name' => 'Без предпочтений', 'email' => 'nopref@example.com',
        'password' => 'secret-password', 'locale' => null, 'theme' => null,
    ]);

    $response = $this->postJson('/api/admin/auth/login', [
        'email' => 'nopref@example.com', 'password' => 'secret-password',
    ]);

    $response->assertOk();
    expect($response->json('payload.user.locale'))->toBeNull('умолчание локали выдано за выбор пользователя');
    expect($response->json('payload.user.theme'))->toBeNull('умолчание темы выдано за выбор пользователя');
});

it('сохранённые значения по-прежнему отдаются', function (): void {
    // The other side: the fix must not stop returning the real choice.
    config(['admin.ui.default_locale' => 'en']);
    AdminUser::create([
        'name' => 'С выбором', 'email' => 'withpref@example.com',
        'password' => 'secret-password', 'locale' => 'ru', 'theme' => 'dark',
    ]);

    $response = $this->postJson('/api/admin/auth/login', [
        'email' => 'withpref@example.com', 'password' => 'secret-password',
    ]);

    expect($response->json('payload.user.locale'))->toBe('ru');
    expect($response->json('payload.user.theme'))->toBe('dark');
});

it('вход и bootstrap описывают пользователя одинаково', function (): void {
    // The essence of the defect was not the default itself but the DIVERGENCE
    // of two serializers of one and the same shape.
    config(['admin.ui.default_locale' => 'en']);
    AdminUser::create([
        'name' => 'Сверка', 'email' => 'compare@example.com',
        'password' => 'secret-password', 'locale' => null, 'theme' => null,
    ]);

    $login = $this->postJson('/api/admin/auth/login', [
        'email' => 'compare@example.com', 'password' => 'secret-password',
    ])->json('payload.user');

    $bootstrap = $this->getJson('/api/admin/system/bootstrap')->json('payload.user');

    foreach (['locale', 'theme'] as $key) {
        expect($login[$key])->toBe($bootstrap[$key], "поле {$key} расходится между входом и bootstrap");
    }
});
