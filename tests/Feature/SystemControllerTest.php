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

    // Every system action is protected by the AdminAuth middleware. In P1 we
    // create an admin on the fly for the tests.
    $admin = AdminUser::create([
        'name' => 'Test Admin',
        'email' => 'admin-test-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');
});

it('serves /api/admin/system/bootstrap', function (): void {
    // Pest's default Accept-Language may be 'en' — we pin ru explicitly.
    $response = $this->withoutExceptionHandling()->getJson('/api/admin/system/bootstrap', [
        Dskripchenko\LaravelAdmin\Theme\LocaleResolver::HEADER => 'ru',
    ]);

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
    expect($items[0]['url'])->toBe('/r/test-users');
    expect($items[0]['routeName'])->toBe('admin.resource.test-users.index');
});

it('serves /api/admin/system/locales', function (): void {
    // The testing default Accept-Language gives 'en' — we pin it explicitly.
    $response = $this->getJson('/api/admin/system/locales', [
        Dskripchenko\LaravelAdmin\Theme\LocaleResolver::HEADER => 'ru',
    ]);

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

it('serves /api/admin/system/status', function (): void {
    app(Dskripchenko\LaravelAdmin\Admin::class)->statusIndicators([StatusIndicatorStub::class]);

    $response = $this->getJson('/api/admin/system/status');

    $response->assertOk();
    expect($response->json('payload.indicators'))->toBe([[
        'key' => 'test.stub',
        'status' => 'warning',
        'label' => 'Проверки',
        'detail' => '2 из 5 не прошли',
        'url' => '/health',
    ]]);
});

it('drops an indicator that throws instead of failing the request', function (): void {
    app(Dskripchenko\LaravelAdmin\Admin::class)->statusIndicators([
        BrokenStatusIndicatorStub::class,
        StatusIndicatorStub::class,
    ]);

    $response = $this->getJson('/api/admin/system/status');

    // A broken diagnostic must not take the header down with it: the working
    // one still answers.
    $response->assertOk();
    expect($response->json('payload.indicators'))->toHaveCount(1);
    expect($response->json('payload.indicators.0.key'))->toBe('test.stub');
});

it('falls back to unknown for a status outside the vocabulary', function (): void {
    app(Dskripchenko\LaravelAdmin\Admin::class)->statusIndicators([WildStatusIndicatorStub::class]);

    $response = $this->getJson('/api/admin/system/status');

    expect($response->json('payload.indicators.0.status'))->toBe('unknown');
});

it('admin api throttle is config-driven (default 240,1)', function (): void {
    // The routes are registered at boot — we check the getMethods() declaration.
    $mw = implode('|', Dskripchenko\LaravelAdmin\Http\AdminApi::getMethods()['middleware']);
    expect($mw)->toContain(':240,1');

    config()->set('admin.api.throttle', '17,3');
    $mw2 = implode('|', Dskripchenko\LaravelAdmin\Http\AdminApi::getMethods()['middleware']);
    expect($mw2)->toContain(':17,3');
});

it('login throttle is config-driven (config existed but was hardcoded)', function (): void {
    config()->set('admin.auth.login_throttle', '9,2');
    Dskripchenko\LaravelAdmin\Http\AdminApi::clearCache();
    $auth = Dskripchenko\LaravelAdmin\Http\AdminApi::getMethods()['controllers']['auth']['actions'];
    expect(implode('|', $auth['login']['middleware']))->toContain(':9,2,auth-admin');
    expect(implode('|', $auth['twoFactorChallenge']['middleware']))->toContain(':9,2,auth-admin');
});

final class StatusIndicatorStub implements Dskripchenko\LaravelAdmin\Status\StatusIndicator
{
    public function key(): string
    {
        return 'test.stub';
    }

    public function state(): array
    {
        return [
            'status' => 'warning',
            'label' => 'Проверки',
            'detail' => '2 из 5 не прошли',
            'url' => '/health',
        ];
    }
}

final class BrokenStatusIndicatorStub implements Dskripchenko\LaravelAdmin\Status\StatusIndicator
{
    public function key(): string
    {
        return 'test.broken';
    }

    public function state(): array
    {
        throw new RuntimeException('база недоступна');
    }
}

final class WildStatusIndicatorStub implements Dskripchenko\LaravelAdmin\Status\StatusIndicator
{
    public function key(): string
    {
        return 'test.wild';
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        return ['status' => 'на грани', 'label' => 'Что-то'];
    }
}
