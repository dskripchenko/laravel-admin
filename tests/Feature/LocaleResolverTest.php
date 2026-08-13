<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Theme\LocaleResolver;
use Illuminate\Http\Request;

beforeEach(function (): void {
    config()->set('admin.ui.available_locales', ['ru', 'en', 'de']);
    config()->set('admin.ui.default_locale', 'ru');
});

it('resolves from query?locale param', function (): void {
    $req = Request::create('/', 'GET', ['locale' => 'en']);
    expect(app(LocaleResolver::class)->resolve($req))->toBe('en');
});

it('resolves from X-Admin-Locale header', function (): void {
    $req = Request::create('/');
    $req->headers->set(LocaleResolver::HEADER, 'de');
    expect(app(LocaleResolver::class)->resolve($req))->toBe('de');
});

it('resolves from user.locale when logged in', function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
        'locale' => 'en',
    ]);
    $this->actingAs($admin, 'admin');

    expect(app(LocaleResolver::class)->resolve(Request::create('/')))->toBe('en');
});

it('resolves from cookie for anonymous', function (): void {
    $req = Request::create('/');
    $req->cookies->set(LocaleResolver::COOKIE_NAME, 'de');
    expect(app(LocaleResolver::class)->resolve($req))->toBe('de');
});

it('resolves from Accept-Language with full match', function (): void {
    $req = Request::create('/');
    $req->headers->set('Accept-Language', 'de;q=1.0, ru;q=0.5');
    expect(app(LocaleResolver::class)->resolve($req))->toBe('de');
});

it('resolves from Accept-Language with short-form fallback (ru-RU → ru)', function (): void {
    $req = Request::create('/');
    $req->headers->set('Accept-Language', 'ru-RU;q=1.0, en;q=0.5');
    expect(app(LocaleResolver::class)->resolve($req))->toBe('ru');
});

it('priority: query > header > user > cookie', function (): void {
    config()->set('admin.ui.available_locales', ['ru', 'en', 'de', 'fr']);

    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
        'locale' => 'fr',
    ]);
    $this->actingAs($admin, 'admin');

    $req = Request::create('/', 'GET', ['locale' => 'en']);
    $req->headers->set(LocaleResolver::HEADER, 'de');
    $req->cookies->set(LocaleResolver::COOKIE_NAME, 'ru');

    expect(app(LocaleResolver::class)->resolve($req))->toBe('en'); // query wins
});

it('falls back to default if no candidate matches', function (): void {
    $req = Request::create('/');
    $req->headers->set('Accept-Language', 'jp,zh-CN'); // not in available
    expect(app(LocaleResolver::class)->resolve($req))->toBe('ru');
});

it('default returns first available if config default is unsupported', function (): void {
    config()->set('admin.ui.default_locale', 'jp');
    config()->set('admin.ui.available_locales', ['ru', 'en']);
    expect(app(LocaleResolver::class)->default())->toBe('ru');
});

it('persist updates user.locale + returns cookie', function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $cookie = app(LocaleResolver::class)->persist('en');
    expect($cookie->getName())->toBe(LocaleResolver::COOKIE_NAME);
    expect($cookie->getValue())->toBe('en');
    expect($admin->fresh()->locale)->toBe('en');
});

it('AdminLocale middleware sets app locale from resolver', function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
        'locale' => 'de',
    ]);
    $this->actingAs($admin, 'admin');

    // The notifications table may be missing — system.me needs a Schema guard,
    // and it is already there since P15.2.
    $this->getJson('/api/admin/system/me');
    expect(app()->getLocale())->toBe('de');
});

it('system.locales returns available + current', function (): void {
    // The testing default Accept-Language may be 'en' — we state ru explicitly.
    $response = $this->getJson('/api/admin/system/locales', [
        LocaleResolver::HEADER => 'ru',
    ]);
    $response->assertOk();
    expect($response->json('payload.available'))->toBe(['ru', 'en', 'de']);
    expect($response->json('payload.current'))->toBe('ru');
});

it('system.setLocale persists for logged-in user', function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $response = $this->postJson('/api/admin/system/setLocale', ['locale' => 'en']);
    $response->assertOk();
    expect($admin->fresh()->locale)->toBe('en');
});

it('system.setLocale rejects unknown locale with 422', function (): void {
    $response = $this->postJson('/api/admin/system/setLocale', ['locale' => 'jp']);
    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('unsupported_locale');
});

it('ответ панели объявляет Vary по всем источникам локали', function (): void {
    // The shell carries an inline bootstrap with the strings, the manifest
    // carries the resources' labels. Without a Vary a reverse proxy or a CDN in
    // front of the panel will hand one visitor's language to another. The cookie
    // here is not over-caution: two of the five sources are cookies (the session
    // with user.locale, and admin_locale).
    $vary = $this->get('/admin')->headers->get('Vary');

    expect($vary)->not->toBeNull('шелл не объявляет Vary');
    foreach (['Accept-Language', 'X-Admin-Locale', 'Cookie'] as $source) {
        expect(strtolower((string) $vary))->toContain(strtolower($source));
    }
});
