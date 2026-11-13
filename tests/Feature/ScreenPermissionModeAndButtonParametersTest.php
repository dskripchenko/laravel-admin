<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Menu\MenuNode;
use Dskripchenko\LaravelAdmin\Menu\MenuRegistry;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenAccess;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;

/**
 * Screen::permissionMode() 'any' — a screen open to whoever holds one of its
 * keys — on every path that decides access, and Button::parameters() reaching
 * the screen method.
 */
function actingWithScreenKeys(array $permissions): AdminUser
{
    $admin = AdminUser::create([
        'name' => 'U', 'email' => 'u-'.uniqid().'@example.com', 'password' => 'secret',
    ]);
    $admin->assignRole(Role::create([
        'name' => 'R', 'slug' => 'r-'.uniqid(), 'permissions' => $permissions,
    ]));
    test()->actingAs($admin->refresh(), 'admin');

    return $admin;
}

/**
 * @return list<string>
 */
function menuUrls(mixed $test): array
{
    $urls = [];
    $walk = static function (array $nodes) use (&$walk, &$urls): void {
        foreach ($nodes as $node) {
            if (is_string($node['url'] ?? null)) {
                $urls[] = $node['url'];
            }
            $walk($node['children'] ?? []);
        }
    };
    $walk($test->getJson('/api/admin/system/menu')->assertOk()->json('payload.items'));

    return $urls;
}

beforeEach(function (): void {
    app(MenuRegistry::class)->clear();
    app(ResourceRegistry::class)->clear();
    $screens = app(ScreenRegistry::class);
    $screens->clear();
    $screens->add(TestAnyPermissionScreen::class);
    $screens->add(TestAllPermissionScreen::class);
    $screens->add(TestAnyPermissionDashboard::class);
    AdminApi::clearCache();
    TestAnyPermissionScreen::$calls = [];
});

/* ---------------- permissionMode ---------------- */

it('opens an any-mode screen to a user with one of its keys', function (string $key): void {
    actingWithScreenKeys([$key]);

    $state = $this->getJson('/api/admin/test-any-section/state')->assertOk()->json('payload');
    expect($state['permission_mode'])->toBe('any');
    $this->postJson('/api/admin/test-any-section/runMethod', ['method' => 'ping', 'payload' => []])->assertOk();
    $this->postJson('/api/admin/test-any-section/listener', ['listener' => 'nope'])->assertStatus(404);
})->with(['admin.section.orders', 'admin.section.quotes']);

it('refuses an any-mode screen to a user with none of its keys', function (): void {
    actingWithScreenKeys(['admin.other.view']);

    $this->getJson('/api/admin/test-any-section/state')->assertStatus(403);
    $this->postJson('/api/admin/test-any-section/runMethod', ['method' => 'ping', 'payload' => []])->assertStatus(403);
    $this->postJson('/api/admin/test-any-section/listener', ['listener' => 'nope'])->assertStatus(403);
    expect(TestAnyPermissionScreen::$calls)->toBe([]);
});

it('keeps the default all-mode: one key is not enough', function (): void {
    actingWithScreenKeys(['admin.section.orders']);
    $this->getJson('/api/admin/test-all-section/state')->assertStatus(403);
});

it('keeps the default all-mode: every key opens it', function (): void {
    actingWithScreenKeys(['admin.section.orders', 'admin.section.quotes']);
    $state = $this->getJson('/api/admin/test-all-section/state')->assertOk()->json('payload');
    expect($state['permission_mode'])->toBe('all');
});

it('tells the mode in the manifest', function (): void {
    actingWithScreenKeys(['*']);

    $screens = collect($this->getJson('/api/admin/system/manifest')->assertOk()->json('payload.screens'));

    expect($screens->firstWhere('slug', 'test-any-section')['permission_mode'])->toBe('any');
    expect($screens->firstWhere('slug', 'test-all-section')['permission_mode'])->toBe('all');
});

it('applies the screen rule in the auto menu', function (): void {
    actingWithScreenKeys(['admin.section.quotes']);
    expect(menuUrls($this))
        ->toContain('/screens/test-any-section')
        ->not->toContain('/screens/test-all-section');
});

it('leaves an any-mode screen out of the menu of a user with none of its keys', function (): void {
    actingWithScreenKeys(['admin.other.view']);
    expect(menuUrls($this))->not->toContain('/screens/test-any-section', '/screens/test-all-section');
});

it('applies the dashboard rule to MenuNode::dashboard', function (array $keys, bool $shown): void {
    app(MenuRegistry::class)->add(MenuNode::dashboard('test-any-dash'));
    actingWithScreenKeys($keys);

    expect(in_array('/dashboard/test-any-dash', menuUrls($this), true))->toBe($shown);
})->with([
    'one key' => [['admin.section.orders'], true],
    'no key' => [['admin.other.view'], false],
]);

it('applies the screen rule to explicit MenuNode::screen nodes', function (): void {
    app(MenuRegistry::class)->add(MenuNode::make('section', 'Section')->children([
        MenuNode::screen('test-any-section'),
        MenuNode::screen('test-all-section'),
    ]))->withAuto(false);

    actingWithScreenKeys(['admin.section.orders']);

    expect(menuUrls($this))->toBe(['/screens/test-any-section']);
});

it('hides a screen from the auto menu with hideAuto, by slug or as screen.{slug}', function (string $hidden): void {
    app(MenuRegistry::class)->hideAuto($hidden);
    actingWithScreenKeys(['*']);

    expect(menuUrls($this))
        ->not->toContain('/screens/test-any-section')
        ->toContain('/screens/test-all-section');
})->with(['test-any-section', 'screen.test-any-section']);

it('opens an any-mode dashboard to a user with one of its keys', function (array $keys, int $status): void {
    actingWithScreenKeys($keys);
    $this->getJson('/api/admin/dashboard/get?key=test-any-dash')->assertStatus($status);
})->with([
    'one key' => [['admin.section.orders'], 200],
    'no key' => [['admin.other.view'], 403],
]);

it('builds the middleware of each mode', function (): void {
    expect(ScreenAccess::middleware(new TestAnyPermissionScreen))
        ->toBe([Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess::class.':admin.section.orders;admin.section.quotes,any']);
    expect(ScreenAccess::middleware(new TestAllPermissionScreen))
        ->toBe([Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess::class.':admin.section.orders;admin.section.quotes']);
});

/* ---------------- Button::parameters ---------------- */

it('serializes the parameters of a button', function (): void {
    expect(Button::make('Sync')->method('sync')->parameters(['dictionary' => 'x'])->toArray()['attributes']['parameters'])
        ->toBe(['dictionary' => 'x']);
});

it('passes the parameters of the pressed button to the method as its second argument', function (): void {
    actingWithScreenKeys(['admin.section.orders']);

    $response = $this->postJson('/api/admin/test-any-section/runMethod', [
        'method' => 'sync',
        'payload' => ['title' => 'x'],
        'params' => ['dictionary' => 'vehicle_marks'],
    ])->assertOk();

    expect($response->json('payload.message'))->toBe('synced vehicle_marks');
    expect(TestAnyPermissionScreen::$calls)->toBe([['sync', [['title' => 'x'], ['dictionary' => 'vehicle_marks']]]]);
});

it('calls a one-argument method with the state alone', function (): void {
    actingWithScreenKeys(['admin.section.orders']);

    $this->postJson('/api/admin/test-any-section/runMethod', [
        'method' => 'ping',
        'payload' => ['title' => 'x'],
        'params' => ['source' => 'bar'],
    ])->assertOk();
    $this->postJson('/api/admin/test-any-section/runMethod', ['method' => 'sync', 'payload' => ['title' => 'y']])->assertOk();

    expect(TestAnyPermissionScreen::$calls)->toBe([
        ['ping', [['title' => 'x']]],
        ['sync', [['title' => 'y'], []]],
    ]);
});

it('refuses the parameters of a button the user may not press', function (): void {
    actingWithScreenKeys(['admin.section.orders']);

    $denied = $this->postJson('/api/admin/test-any-section/runMethod', [
        'method' => 'sync',
        'payload' => [],
        'params' => ['dictionary' => 'vehicle_models'],
    ]);
    $denied->assertStatus(403);
    expect($denied->json('payload.errorKey'))->toBe('action_forbidden');
    expect(TestAnyPermissionScreen::$calls)->toBe([]);
});

it('runs with the parameters of a button the user may press', function (): void {
    actingWithScreenKeys(['admin.section.orders', 'admin.dictionaries.models']);
    $this->postJson('/api/admin/test-any-section/runMethod', [
        'method' => 'sync',
        'payload' => [],
        'params' => ['dictionary' => 'vehicle_models'],
    ])->assertOk();
});
