<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Panel\PanelRegistry;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Support\Manifest;

// -- The panel registry -----------------------------------------------------

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

// -- Scoping the registries -------------------------------------------------

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

// -- The shell routes -------------------------------------------------------

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
    // A direct /admin is served by the admin panel (see the test above). The
    // excluded api prefix is not intercepted by the root panel:
    // /api/client/... reaches laravel-api (a 401 from AdminAuth rather than the
    // HTML shell).
    $response = $this->getJson('/api/client/system/me');

    $response->assertStatus(401);
    expect($response->headers->get('content-type'))->toContain('application/json');
});

// -- The guards and the API surfaces ----------------------------------------

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

    // A client-guard session grants no access to the admin API.
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

    // The admin version has no such controller at all (the per-panel compilation).
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
    // Signing in to the client panel does not give an admin session.
    $this->getJson('/api/admin/system/me')->assertStatus(401);
});

it('refuses a disabled panel user at login, not one request later', function (): void {
    TestPanelClientUser::create([
        'name' => 'Disabled User',
        'email' => 'disabled@example.com',
        'password' => bcrypt('secret-password'),
        'enabled' => false,
    ]);

    // The switch promises "signing in is impossible". By letting such a user in
    // and throwing them out on the very first request, the panel would show them
    // a successful login with permissions — and an account is usually disabled
    // for an employee who has been let go.
    $login = $this->postJson('/api/client/auth/login', [
        'email' => 'disabled@example.com',
        'password' => 'secret-password',
    ]);

    $login->assertStatus(403);
    expect($login->json('payload.errorKey'))->toBe('account_inactive');
    $this->getJson('/api/client/system/me')->assertStatus(401);
});

it('модель может закрыть вход сама — состоянием своего владельца', function (): void {
    TestPanelClientUser::create([
        'name' => 'Suspended Owner',
        'email' => 'owner-suspended@example.com',
        'password' => bcrypt('secret-password'),
        'enabled' => true,
        'owner_suspended' => true,
    ]);

    // The account is enabled and the password is correct — but the account it
    // belongs to is suspended. The panel does not know those rules, so it asks
    // the model.
    $login = $this->postJson('/api/client/auth/login', [
        'email' => 'owner-suspended@example.com',
        'password' => 'secret-password',
    ]);

    $login->assertStatus(403);
    expect($login->json('payload.errorKey'))->toBe('account_inactive');
});

it('panel login throttles use independent buckets', function (): void {
    $this->withMiddleware(Illuminate\Routing\Middleware\ThrottleRequests::class);
    $sig = sha1('|127.0.0.1');
    Illuminate\Support\Facades\RateLimiter::clear('auth-admin'.$sig);
    Illuminate\Support\Facades\RateLimiter::clear('auth-client'.$sig);

    // Attempts against the client panel must not burn the admin panel's limit.
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/client/auth/login', [
            'email' => 'nobody@example.com', 'password' => 'wrong',
        ])->assertStatus(401);
    }
    $this->postJson('/api/client/auth/login', [
        'email' => 'nobody@example.com', 'password' => 'wrong',
    ])->assertStatus(429);

    $this->postJson('/api/admin/auth/login', [
        'email' => 'nobody@example.com', 'password' => 'wrong',
    ])->assertStatus(401);
});

it('login payload gives wildcard permissions to hasAccess-only panel users', function (): void {
    TestPanelClientUser::create([
        'name' => 'Perm User',
        'email' => 'perm@example.com',
        'password' => bcrypt('secret-password'),
    ]);

    $login = $this->postJson('/api/client/auth/login', [
        'email' => 'perm@example.com',
        'password' => 'secret-password',
    ]);

    $login->assertOk();
    // The contract of the panel models is hasAccess() only; without the
    // wildcard the SPA guards would send the user to /forbidden on every
    // permission route.
    expect($login->json('payload.permissions'))->toBe(['*']);
});

it('один экран может служить нескольким панелям', function (): void {
    // The trap this closes: `panels` held ONE panel per slug, so registering
    // the same screen into a second panel silently moved it out of the first.
    // The section disappeared from a panel that used to have it, with nothing
    // in any log — found on a live stand, not by a test.
    $screens = new Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
    $screens->add(PanelsSharedScreen::class, 'admin');
    $screens->add(PanelsSharedScreen::class, 'client');

    expect($screens->all('admin'))->toHaveKey('panels-shared')
        ->and($screens->all('client'))->toHaveKey('panels-shared')
        ->and($screens->panelsOf('panels-shared'))->toBe(['admin', 'client'])
        ->and($screens->panelOf('panels-shared'))->toBe('admin');
});

it('повторная регистрация в ту же панель не плодит дублей', function (): void {
    $screens = new Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
    $screens->add(PanelsSharedScreen::class, 'client');
    $screens->add(PanelsSharedScreen::class, 'client');

    expect($screens->panelsOf('panels-shared'))->toBe(['client']);
});

final class PanelsSharedScreen extends Dskripchenko\LaravelAdmin\Screen\Screen
{
    public static function slug(): string
    {
        return 'panels-shared';
    }

    public function name(): string
    {
        return 'Shared';
    }

    /** @return array<string, mixed> */
    public function query(mixed ...$params): array
    {
        return [];
    }

    /** @return list<Dskripchenko\LaravelAdmin\Layout\Layout> */
    public function layout(): array
    {
        return [];
    }
}
