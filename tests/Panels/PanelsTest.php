<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Panel\PanelRegistry;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Support\Manifest;

// ── Реестр панелей ─────────────────────────────────────────────────────────

it('registers the default panel plus configured panels', function (): void {
    $registry = app(PanelRegistry::class);

    expect(array_keys($registry->all()))->toBe(['admin', 'client'])
        ->and($registry->default()->id)->toBe('admin')
        ->and($registry->get('client')?->path)->toBe('')
        ->and($registry->get('client')?->guard)->toBe('client')
        ->and($registry->get('client')?->apiPath)->toBe('api/client');
});

it('registers a dedicated auth guard for the extra panel', function (): void {
    expect(config('auth.guards.client.provider'))->toBe('test_client_users')
        ->and(config('auth.providers.test_client_users.model'))->toBe(TestPanelClientUser::class)
        ->and(config('auth.passwords.test_client_users'))->not->toBeNull();
});

// ── Скоупинг реестров ──────────────────────────────────────────────────────

it('scopes plugin registrations to their panel', function (): void {
    $resources = app(ResourceRegistry::class);

    expect(array_keys($resources->all('client')))->toBe(['test-panel-projects'])
        ->and($resources->all('admin'))->not->toHaveKey('test-panel-projects')
        ->and($resources->all('admin'))->toHaveKey('test-users')
        ->and($resources->panelOf('test-panel-projects'))->toBe('client');
});

it('builds a panel-scoped manifest', function (): void {
    $manifest = app(Manifest::class);

    $client = $manifest->build('ru', 'client');
    $clientSlugs = array_column($client['resources'], 'slug');
    expect($clientSlugs)->toBe(['test-panel-projects'])
        ->and($client['panel'])->toBe('client');

    $admin = $manifest->build('ru');
    $adminSlugs = array_column($admin['resources'], 'slug');
    expect($adminSlugs)->toContain('test-users')
        ->and($adminSlugs)->not->toContain('test-panel-projects')
        ->and($admin['version'])->not->toBe($client['version']);
});

// ── Shell-роуты ────────────────────────────────────────────────────────────

it('serves the client panel shell at the site root', function (): void {
    $response = $this->get('/');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('"panel":"client"')
        ->toContain('"apiUrl":"'.str_replace('/', '\\/', url('api/client')).'"');
});

it('keeps the default admin shell and bootstrap intact', function (): void {
    $response = $this->get('/admin');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('"panel":"admin"')
        ->toContain('"apiUrl":"'.str_replace('/', '\\/', url('api/admin')).'"');
});

it('root panel catch-all does not swallow excluded prefixes', function (): void {
    // Прямой /admin обслуживается панелью admin (см. тест выше). Исключённый
    // префикс api не перехватывается root-панелью: /api/client/... доходит
    // до laravel-api (401 от AdminAuth, а не HTML shell).
    $response = $this->getJson('/api/client/system/me');

    $response->assertStatus(401);
    expect($response->headers->get('content-type'))->toContain('application/json');
});

// ── Guards и API-поверхности ───────────────────────────────────────────────

it('authenticates the client panel via its own guard', function (): void {
    $user = TestPanelClientUser::create([
        'name' => 'Client One',
        'email' => 'client@example.com',
        'password' => bcrypt('secret'),
    ]);

    $this->actingAs($user, 'client');

    $me = $this->getJson('/api/client/system/me');
    $me->assertOk();
    expect($me->json('payload.email'))->toBe('client@example.com');

    // Сессия client-guard'а не даёт доступа к admin-API.
    $this->getJson('/api/admin/system/me')->assertStatus(401);
});

it('admin session does not authenticate the client panel api', function (): void {
    $admin = AdminUser::create([
        'name' => 'Root', 'email' => 'root@example.com', 'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $this->getJson('/api/admin/system/me')->assertOk();
    $this->getJson('/api/client/system/me')->assertStatus(401);
});

it('exposes panel resources only in the panel api surface', function (): void {
    $user = TestPanelClientUser::create([
        'name' => 'C', 'email' => 'c@example.com', 'password' => bcrypt('secret'),
    ]);
    $this->actingAs($user, 'client');

    TestPanelProjectModel::create(['name' => 'P1']);
    $this->getJson('/api/client/test-panel-projects/meta')->assertOk();

    // В admin-версии этого контроллера нет вовсе (панельная компиляция).
    $admin = AdminUser::create([
        'name' => 'Root', 'email' => 'root2@example.com', 'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');
    $this->getJson('/api/admin/test-panel-projects/meta')->assertStatus(404);
});

it('does not leak admin resources into the panel api', function (): void {
    $methods = TestPanelClientApi::getPreparedMethods();
    $controllers = array_keys((array) ($methods['controllers'] ?? []));

    expect($controllers)->toContain('test-panel-projects')
        ->and($controllers)->not->toContain('test-users')
        ->and($controllers)->toContain('system');
});

// ── Bootstrap per panel ────────────────────────────────────────────────────

it('client bootstrap serializes the client user and panel menu', function (): void {
    $user = TestPanelClientUser::create([
        'name' => 'Client One', 'email' => 'boot@example.com', 'password' => bcrypt('secret'),
    ]);
    $this->actingAs($user, 'client');

    $response = $this->getJson('/api/client/system/bootstrap');
    $response->assertOk();
    expect($response->json('payload.panel'))->toBe('client')
        ->and($response->json('payload.user.email'))->toBe('boot@example.com')
        ->and($response->json('payload.manifestVersion'))->toBeString();

    $menu = $this->getJson('/api/client/system/menu');
    $menu->assertOk();
    $keys = array_column((array) $menu->json('payload.items'), 'key');
    expect($keys)->toContain('resource.test-panel-projects')
        ->and($keys)->not->toContain('test-users');
});

it('logs a client user in through the panel login endpoint', function (): void {
    TestPanelClientUser::create([
        'name' => 'Login User',
        'email' => 'login@example.com',
        'password' => bcrypt('secret-password'),
    ]);

    $login = $this->postJson('/api/client/auth/login', [
        'email' => 'login@example.com',
        'password' => 'secret-password',
    ]);

    $login->assertOk();
    $this->getJson('/api/client/system/me')->assertOk();
    // Логин в client-панель не даёт admin-сессии.
    $this->getJson('/api/admin/system/me')->assertStatus(401);
});
