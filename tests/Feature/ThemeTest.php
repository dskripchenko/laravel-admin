<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Theme\ThemeManager;
use Illuminate\Http\Request;

it('ThemeManager::default falls back to config', function (): void {
    config()->set('admin.ui.default_theme', 'dark');
    expect(app(ThemeManager::class)->default())->toBe('dark');
});

it('ThemeManager::available returns configured list or default', function (): void {
    config()->set('admin.ui.available_themes', ['light', 'dark', 'sepia']);
    expect(app(ThemeManager::class)->available())->toBe(['light', 'dark', 'sepia']);

    config()->set('admin.ui.available_themes', null);
    expect(app(ThemeManager::class)->available())->toBe(['light', 'dark']);
});

it('ThemeManager::isAvailable rejects unknown', function (): void {
    expect(app(ThemeManager::class)->isAvailable('cyberpunk'))->toBeFalse();
    expect(app(ThemeManager::class)->isAvailable('light'))->toBeTrue();
});

it('ThemeManager::current returns user.theme when logged in', function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
        'theme' => 'dark',
    ]);
    $this->actingAs($admin, 'admin');

    expect(app(ThemeManager::class)->current(Request::create('/')))->toBe('dark');
});

it('ThemeManager::current uses cookie for anonymous user', function (): void {
    $req = Request::create('/');
    $req->cookies->set(ThemeManager::COOKIE_NAME, 'dark');

    expect(app(ThemeManager::class)->current($req))->toBe('dark');
});

it('ThemeManager::current ignores invalid cookie value', function (): void {
    $req = Request::create('/');
    $req->cookies->set(ThemeManager::COOKIE_NAME, 'invalid-theme');

    expect(app(ThemeManager::class)->current($req))->toBe(app(ThemeManager::class)->default());
});

it('ThemeManager::persist updates user.theme + returns cookie', function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $cookie = app(ThemeManager::class)->persist('dark');
    expect($cookie->getName())->toBe(ThemeManager::COOKIE_NAME);
    expect($cookie->getValue())->toBe('dark');
    expect($admin->fresh()->theme)->toBe('dark');
});

it('system.theme returns current/default/available', function (): void {
    config()->set('admin.ui.default_theme', 'light');
    config()->set('admin.ui.available_themes', ['light', 'dark']);

    $response = $this->getJson('/api/admin/system/theme');
    $response->assertOk();
    expect($response->json('payload.current'))->toBe('light');
    expect($response->json('payload.default'))->toBe('light');
    expect($response->json('payload.available'))->toBe(['light', 'dark']);
});

it('system.setTheme persists for logged-in user', function (): void {
    $admin = AdminUser::create([
        'name' => 'T',
        'email' => 't-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $response = $this->postJson('/api/admin/system/setTheme', ['theme' => 'dark']);
    $response->assertOk();
    expect($response->json('payload.theme'))->toBe('dark');
    expect($admin->fresh()->theme)->toBe('dark');
});

it('system.setTheme rejects unsupported theme with 422', function (): void {
    $response = $this->postJson('/api/admin/system/setTheme', ['theme' => 'cyberpunk']);
    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('unsupported_theme');
});

it('system.theme is accessible without auth (cookie-based)', function (): void {
    $response = $this->getJson('/api/admin/system/theme');
    $response->assertOk();
});

it('system.setTheme works for anonymous (cookie-only)', function (): void {
    $response = $this->postJson('/api/admin/system/setTheme', ['theme' => 'dark']);
    $response->assertOk();
});
