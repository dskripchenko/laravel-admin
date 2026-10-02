<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;

/**
 * The SPA sends X-Admin-Locale with the locale it renders; the server answers
 * in it — also when the refusal comes from the middleware in front of the
 * action (the session, the account, demo mode), not only from the action.
 * The resolver's order stays: query > header > user > cookie >
 * Accept-Language > default.
 */
function localeAdmin(array $attributes = []): AdminUser
{
    $admin = AdminUser::create(array_merge([
        'name' => 'L', 'email' => 'l-'.uniqid().'@example.com', 'password' => 'secret',
    ], $attributes));
    $admin->assignRole(Role::create(['name' => 'S', 'slug' => 's-'.uniqid(), 'permissions' => ['*']]));

    return $admin->refresh();
}

it('answers an account refusal in the header locale, not in Accept-Language', function (string $header, string $expected): void {
    $admin = localeAdmin(['is_active' => false]);
    $this->actingAs($admin, 'admin');

    $response = $this->withHeaders(['X-Admin-Locale' => $header, 'Accept-Language' => $header === 'ru' ? 'en' : 'ru'])
        ->getJson('/api/admin/system/me');

    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('account_inactive');
    expect($response->json('payload.message'))->toBe($expected);
})->with([
    'ru' => ['ru', 'Учётная запись отключена'],
    'en' => ['en', 'This account is disabled'],
]);

it('answers a demo-mode refusal in the header locale', function (string $header, string $expected): void {
    config()->set('admin.demo', array_merge(config('admin.demo'), ['enabled' => true, 'readonly' => true]));
    $this->actingAs(localeAdmin(), 'admin');

    $response = $this->withHeaders(['X-Admin-Locale' => $header, 'Accept-Language' => $header === 'ru' ? 'en' : 'ru'])
        ->postJson('/api/admin/profile/update', ['name' => 'x', 'email' => 'x@example.com']);

    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('demo_readonly');
    expect($response->json('payload.message'))->toBe($expected);
})->with([
    'ru' => ['ru', 'Демо-режим: это действие отключено.'],
    'en' => ['en', 'Demo mode: this action is disabled.'],
]);

it('falls back to Accept-Language only when the header is absent', function (): void {
    config()->set('admin.demo', array_merge(config('admin.demo'), ['enabled' => true, 'readonly' => true]));
    $this->actingAs(localeAdmin(), 'admin');

    $response = $this->withHeaders(['Accept-Language' => 'en'])
        ->postJson('/api/admin/profile/update', ['name' => 'x', 'email' => 'x@example.com']);

    expect($response->json('payload.message'))->toBe('Demo mode: this action is disabled.');
});

it('resolves the locale before authenticating the request', function (): void {
    $stack = config('admin.middleware.api');

    expect(array_search(Dskripchenko\LaravelAdmin\Http\Middleware\AdminLocale::class, $stack, true))
        ->toBeLessThan(array_search(Dskripchenko\LaravelAdmin\Http\Middleware\AdminAuth::class, $stack, true));
});
