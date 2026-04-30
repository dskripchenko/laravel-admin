<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Support\BootstrapBuilder;
use Illuminate\Http\Request;

beforeEach(function (): void {
    config()->set('admin.path', 'admin');
    config()->set('admin.api_path', 'api/admin');
    config()->set('admin.ui.available_locales', ['ru', 'en']);
    config()->set('admin.ui.default_locale', 'ru');
    config()->set('admin.ui.default_theme', 'light');
});

it('build returns full payload for guest', function (): void {
    $payload = app(BootstrapBuilder::class)->build(Request::create('/'));

    expect($payload)->toHaveKeys([
        'csrf', 'baseUrl', 'apiUrl', 'locale', 'availableLocales',
        'theme', 'availableThemes', 'brand', 'user', 'permissions',
        'manifestVersion', 'plugins', 'unread_notifications_count', 'config',
    ]);
    expect($payload['user'])->toBeNull();
    expect($payload['permissions'])->toBe([]);
    expect($payload['unread_notifications_count'])->toBe(0);
    expect($payload['baseUrl'])->toContain('/admin');
    expect($payload['apiUrl'])->toContain('/api/admin');
});

it('build serializes logged-in user', function (): void {
    $admin = AdminUser::create([
        'name' => 'Admin Name',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
        'theme' => 'dark',
    ]);
    $this->actingAs($admin, 'admin');

    $payload = app(BootstrapBuilder::class)->build(Request::create('/'));

    expect($payload['user'])->not->toBeNull();
    expect($payload['user']['name'])->toBe('Admin Name');
    expect($payload['user']['theme'])->toBe('dark');
    expect($payload['user']['twoFactorEnabled'])->toBeFalse();
});

it('build returns flat list of permissions for user with HasAdminAccess', function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'V', 'slug' => 'v-'.uniqid(),
        'permissions' => ['admin.users.view', 'admin.users.update'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');

    $payload = app(BootstrapBuilder::class)->build(Request::create('/'));

    expect($payload['permissions'])->toContain('admin.users.view');
    expect($payload['permissions'])->toContain('admin.users.update');
});

it('build resolves theme via ThemeManager', function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
        'theme' => 'dark',
    ]);
    $this->actingAs($admin, 'admin');

    $payload = app(BootstrapBuilder::class)->build(Request::create('/'));
    expect($payload['theme'])->toBe('dark');
});

it('build resolves locale via LocaleResolver query > default', function (): void {
    $payload = app(BootstrapBuilder::class)->build(
        Request::create('/?locale=en'),
    );
    expect($payload['locale'])->toBe('en');
});

it('build exposes config.bootstrap.strategy from admin.bootstrap.strategy', function (): void {
    config()->set('admin.bootstrap.strategy', 'xhr');
    $payload = app(BootstrapBuilder::class)->build(Request::create('/'));
    expect($payload['config']['bootstrap']['strategy'])->toBe('xhr');
});

it('SystemController.bootstrap action uses BootstrapBuilder', function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $response = $this->getJson('/api/admin/system/bootstrap');
    $response->assertOk();
    expect($response->json('payload.user.email'))->toBe($admin->email);
    expect($response->json('payload.locale'))->toBeString();
    expect($response->json('payload.theme'))->toBeString();
});
