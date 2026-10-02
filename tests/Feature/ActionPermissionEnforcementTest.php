<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Action\DropDown;
use Dskripchenko\LaravelAdmin\DelayedProcess\AllowlistRegistrar;
use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Action::permission() and canSee() are enforced on the server: the actions
 * are left out of what the user is sent, and running one anyway is a 403.
 */
function actingWithPermissions(array $permissions): AdminUser
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

const GUARDED_BASE = ['admin.test-guarded.view', 'admin.test-guarded.update'];

beforeEach(function (): void {
    $resources = app(ResourceRegistry::class);
    $resources->clear();
    $resources->add(TestGuardedActionResource::class);
    $screens = app(ScreenRegistry::class);
    $screens->clear();
    $screens->add(TestGuardedActionScreen::class);
    AdminApi::clearCache();
    app(AllowlistRegistrar::class)->clear();

    TestGuardedActionResource::$calls = [];
    TestGuardedActionScreen::$calls = [];

    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('name')->nullable();
        $t->string('email')->nullable();
        $t->string('password')->nullable();
        $t->string('status')->nullable();
        $t->integer('amount')->nullable();
        $t->timestamps();
    });
});

/* ---------------- resource actions ---------------- */

dataset('guarded resource actions', [
    'bulk' => ['guarded-bulk', true],
    'row' => ['guarded-row', true],
    'standalone' => ['guarded-standalone', false],
    'modal' => ['guarded-modal', true],
    'dropdown child' => ['nested-guarded', true],
    'child of a guarded dropdown' => ['inside-locked', true],
]);

it('refuses a guarded resource action to a user without its permission', function (string $key, bool $withIds): void {
    actingWithPermissions(GUARDED_BASE);
    $record = TestResourceUserModel::create(['name' => 'A']);

    $response = $this->postJson('/api/admin/test-guarded/action', array_filter([
        'key' => $key,
        'ids' => $withIds ? [$record->id] : null,
        'payload' => ['reason' => 'x'],
    ]));

    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('action_forbidden');
    expect($response->json('payload.message'))->toContain('admin.test-guarded.archive');
    expect(TestGuardedActionResource::$calls)->toBe([]);
})->with('guarded resource actions');

it('runs a guarded resource action for a user with its permission', function (string $key, bool $withIds): void {
    actingWithPermissions([...GUARDED_BASE, 'admin.test-guarded.archive']);
    $record = TestResourceUserModel::create(['name' => 'A']);

    $response = $this->postJson('/api/admin/test-guarded/action', array_filter([
        'key' => $key,
        'ids' => $withIds ? [$record->id] : null,
        'payload' => ['reason' => 'x'],
    ]));

    $response->assertOk();
    expect(TestGuardedActionResource::$calls)->toBe(['mark']);
})->with('guarded resource actions');

it('runs an unguarded resource action, nested or not, for any user of the resource', function (string $key): void {
    actingWithPermissions(GUARDED_BASE);
    $record = TestResourceUserModel::create(['name' => 'A']);

    $this->postJson('/api/admin/test-guarded/action', ['key' => $key, 'ids' => [$record->id]])->assertOk();
})->with(['open', 'nested-open']);

it('refuses a resource action hidden by canSee(false), even to a super administrator', function (): void {
    actingWithPermissions(['*']);

    $response = $this->postJson('/api/admin/test-guarded/action', ['key' => 'hidden']);

    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('action_forbidden');
    expect(TestGuardedActionResource::$calls)->toBe([]);
});

it('leaves guarded actions out of the resource meta for a user without the permission', function (): void {
    actingWithPermissions(GUARDED_BASE);

    $actions = collect($this->getJson('/api/admin/test-guarded/meta')->assertOk()->json('payload.actions'));
    $names = $actions->pluck('name')->all();

    expect($names)->toContain('open', 'more')
        ->not->toContain('guarded-bulk', 'guarded-row', 'guarded-standalone', 'guarded-modal', 'hidden', 'locked', 'guarded-async');
    $more = $actions->firstWhere('name', 'more');
    expect(array_column($more['items'], 'name'))->toBe(['nested-open']);
});

it('sends every permitted action in the resource meta to a user with the permission', function (): void {
    actingWithPermissions([...GUARDED_BASE, 'admin.test-guarded.archive']);

    $actions = collect($this->getJson('/api/admin/test-guarded/meta')->assertOk()->json('payload.actions'));

    expect($actions->pluck('name')->all())
        ->toContain('guarded-bulk', 'guarded-row', 'guarded-standalone', 'guarded-modal', 'locked', 'guarded-async')
        ->not->toContain('hidden');
    expect(array_column($actions->firstWhere('name', 'more')['items'], 'name'))->toBe(['nested-open', 'nested-guarded']);
});

it('filters the actions in the panel manifest by the user', function (): void {
    actingWithPermissions(GUARDED_BASE);

    $resources = collect($this->getJson('/api/admin/system/manifest')->assertOk()->json('payload.resources'));
    $names = array_column($resources->firstWhere('slug', 'test-guarded')['actions'], 'name');

    expect($names)->toContain('open')->not->toContain('guarded-bulk', 'hidden');
});

/* ---------------- screen command bar and methods ---------------- */

it('leaves guarded buttons out of the screen command bar and layout', function (): void {
    actingWithPermissions([]);

    $payload = $this->getJson('/api/admin/test-guarded-action/state')->assertOk()->json('payload');
    $bar = collect($payload['command_bar']);

    expect($bar->pluck('attributes.method')->filter()->values()->all())->toBe(['open']);
    expect($bar->firstWhere('type', 'dropdown')['items'])->toBe([]);
    expect($payload['layout'][0]['items'])->toBe([]);
});

it('shows the guarded buttons to a user with the permission', function (): void {
    actingWithPermissions(['admin.screen.guarded', 'admin.screen.layout']);

    $payload = $this->getJson('/api/admin/test-guarded-action/state')->assertOk()->json('payload');

    expect(collect($payload['command_bar'])->pluck('attributes.method')->filter()->values()->all())
        ->toBe(['open', 'guarded', 'modal']);
    expect($payload['layout'][0]['items'])->toHaveCount(1);
});

it('refuses a screen method bound to a guarded action', function (string $method): void {
    actingWithPermissions([]);

    $response = $this->postJson('/api/admin/test-guarded-action/runMethod', ['method' => $method, 'payload' => []]);

    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('action_forbidden');
    expect(TestGuardedActionScreen::$calls)->toBe([]);
})->with(['guarded', 'modal', 'nested', 'layoutGuarded', 'hidden']);

it('runs a screen method bound to a guarded action for a user with the permission', function (string $method): void {
    actingWithPermissions(['admin.screen.guarded', 'admin.screen.layout']);

    $this->postJson('/api/admin/test-guarded-action/runMethod', ['method' => $method, 'payload' => []])->assertOk();
    expect(TestGuardedActionScreen::$calls)->toBe([$method]);
})->with(['guarded', 'modal', 'nested', 'layoutGuarded']);

it('keeps unguarded and unbound screen methods callable', function (string $method): void {
    actingWithPermissions([]);

    $this->postJson('/api/admin/test-guarded-action/runMethod', ['method' => $method, 'payload' => []])->assertOk();
    expect(TestGuardedActionScreen::$calls)->toBe([$method]);
})->with(['open', 'unbound']);

/* ---------------- async actions ---------------- */

it('refuses to start the handler of a guarded async action', function (string $method): void {
    app(AllowlistRegistrar::class)->allow(TestGuardedAsyncHandler::class, $method);
    actingWithPermissions(GUARDED_BASE);

    $response = $this->postJson('/api/admin/delayed/run', [
        'entity' => TestGuardedAsyncHandler::class,
        'method' => $method,
    ]);

    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('action_forbidden');
})->with(['run', 'screenRun']);

it('starts the handler of a guarded async action for a user with the permission', function (): void {
    app(AllowlistRegistrar::class)->allow(TestGuardedAsyncHandler::class, 'run');
    actingWithPermissions([...GUARDED_BASE, 'admin.test-guarded.archive']);

    $this->postJson('/api/admin/delayed/run', [
        'entity' => TestGuardedAsyncHandler::class,
        'method' => 'run',
    ])->assertOk();
});

it('requires the permission a handler was allowlisted with', function (): void {
    app(AllowlistRegistrar::class)->allow(TestGuardedAsyncHandler::class, 'allowlistGuarded', 'admin.reports.build');
    actingWithPermissions([]);

    $denied = $this->postJson('/api/admin/delayed/run', [
        'entity' => TestGuardedAsyncHandler::class,
        'method' => 'allowlistGuarded',
    ]);
    $denied->assertStatus(403);
    expect($denied->json('payload.errorKey'))->toBe('action_forbidden');
    expect($denied->json('payload.message'))->toContain('admin.reports.build');
    expect(app(AllowlistRegistrar::class)->permissionsFor(TestGuardedAsyncHandler::class, 'allowlistGuarded'))
        ->toBe(['admin.reports.build']);
});

it('starts a handler allowlisted with a permission for a user who has it', function (): void {
    app(AllowlistRegistrar::class)->allow(TestGuardedAsyncHandler::class, 'allowlistGuarded', 'admin.reports.build');
    actingWithPermissions(['admin.reports.build']);
    $this->postJson('/api/admin/delayed/run', [
        'entity' => TestGuardedAsyncHandler::class,
        'method' => 'allowlistGuarded',
    ])->assertOk();
});

it('starts an allowlisted handler no guarded action names', function (): void {
    app(AllowlistRegistrar::class)->allow(TestGuardedAsyncHandler::class, 'free');
    actingWithPermissions([]);

    $this->postJson('/api/admin/delayed/run', [
        'entity' => TestGuardedAsyncHandler::class,
        'method' => 'free',
    ])->assertOk();
});

/* ---------------- the Action API ---------------- */

it('combines canSee() and permission() in isVisible()', function (): void {
    actingWithPermissions(['admin.x']);

    expect(Button::make('A')->permission('admin.x')->isVisible())->toBeTrue();
    expect(Button::make('B')->permission('admin.y')->isVisible())->toBeFalse();
    expect(Button::make('C')->permission('admin.x')->canSee(fn (): bool => false)->isVisible())->toBeFalse();
    expect(Button::make('D')->canSee(fn (): bool => true)->isVisible())->toBeTrue();
    expect(Button::make('E')->permission('admin.y')->passesVisibility())->toBeTrue();
});

it('hides a permission-guarded action when nobody is signed in', function (): void {
    expect(Button::make('A')->permission('admin.x')->isVisible())->toBeFalse();
    expect(Button::make('B')->isVisible())->toBeTrue();
});

it('drops dropdown children the user may not run', function (): void {
    actingWithPermissions(['admin.x']);

    $items = DropDown::make('More')->items([
        Button::make('Allowed')->permission('admin.x'),
        Button::make('Denied')->permission('admin.y'),
    ])->toArray()['items'];

    expect(array_column($items, 'label'))->toBe(['Allowed']);
});
