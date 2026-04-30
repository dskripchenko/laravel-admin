<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;

beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();

    /** @var ScreenRegistry $sr */
    $sr = app(ScreenRegistry::class);
    $sr->clear();

    // Все system-actions защищены AdminAuth middleware. На P1 для тестов
    // создаём админа на лету.
    $admin = AdminUser::create([
        'name' => 'Test Admin',
        'email' => 'admin-test-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');
});

it('serves /api/admin/system/bootstrap', function (): void {
    $response = $this->withoutExceptionHandling()->getJson('/api/admin/system/bootstrap');

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $payload = $response->json('payload');
    expect($payload)->toHaveKey('csrf');
    expect($payload)->toHaveKey('apiUrl');
    expect($payload)->toHaveKey('manifestVersion');
    expect($payload['locale'])->toBe('ru');
});

it('serves /api/admin/system/manifest with ETag header', function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->add(TestUserResource::class);

    $response = $this->getJson('/api/admin/system/manifest');

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $etag = $response->headers->get('ETag');
    expect($etag)->not->toBeNull();

    $payload = $response->json('payload');
    expect($payload)->toHaveKey('version');
    expect($payload['resources'])->toHaveCount(1);
    expect($payload['resources'][0]['slug'])->toBe('test-users');
});

it('returns 304 on matching If-None-Match', function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->add(TestUserResource::class);

    $first = $this->getJson('/api/admin/system/manifest');
    $etag = (string) $first->headers->get('ETag');

    $second = $this->getJson('/api/admin/system/manifest', ['If-None-Match' => $etag]);
    $second->assertStatus(304);
});

it('serves /api/admin/system/me (placeholder on P1, real auth in P2)', function (): void {
    $response = $this->getJson('/api/admin/system/me');

    $response->assertOk();
    expect($response->json('success'))->toBeTrue();
});

it('serves /api/admin/system/menu listing registered resources', function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->add(TestUserResource::class);

    $response = $this->getJson('/api/admin/system/menu');

    $response->assertOk();
    $items = $response->json('payload.items');
    expect($items)->toHaveCount(1);
    expect($items[0]['key'])->toBe('test-users');
    expect($items[0]['url'])->toBe('/admin/resources/test-users');
});

it('serves /api/admin/system/locales', function (): void {
    $response = $this->getJson('/api/admin/system/locales');

    $response->assertOk();
    $payload = $response->json('payload');
    expect($payload['available'])->toBe(['ru', 'en']);
    expect($payload['current'])->toBe('ru');
});

it('serves /api/admin/system/permissions (empty on P1)', function (): void {
    $response = $this->getJson('/api/admin/system/permissions');

    $response->assertOk();
    expect($response->json('payload.groups'))->toBe([]);
});

it('serves /api/admin/system/plugins', function (): void {
    $response = $this->getJson('/api/admin/system/plugins');

    $response->assertOk();
    expect($response->json('payload.plugins'))->toBeArray();
});
