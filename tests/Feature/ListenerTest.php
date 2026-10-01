<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;

/**
 * Listener layouts: the SPA posts {listener, state} and gets back the
 * re-rendered subtree and the handler's state patch.
 */
function listenerActingAs(object $test, array $permissions): void
{
    $admin = AdminUser::create([
        'name' => 'L',
        'email' => 'l-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'L', 'slug' => 'l-'.uniqid(),
        'permissions' => $permissions,
    ]);
    $admin->assignRole($role);
    $test->actingAs($admin->refresh(), 'admin');
}

beforeEach(function (): void {
    /** @var ScreenRegistry $screens */
    $screens = app(ScreenRegistry::class);
    $screens->clear();
    $screens->add(TestListenerScreen::class);

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);
    $resources->clear();
    $resources->add(TestListenerResource::class);

    AdminApi::clearCache();
});

it('serializes a listener with its watched fields and a stable id', function (): void {
    listenerActingAs($this, ['*']);

    $layout = $this->getJson('/api/admin/test-listener/state')->assertOk()->json('payload.layout.0.items');

    expect($layout[1]['type'])->toBe('listener')
        ->and($layout[1]['id'])->toBe('cities')
        ->and($layout[1]['listen'])->toBe(['country'])
        ->and($layout[1]['debounce'])->toBe(300)
        // Rendered against the screen's state: Germany's cities.
        ->and($layout[1]['primed'])->toBeTrue()
        ->and(array_column($layout[1]['items'][0]['attributes']['options'] ?? [], 'value'))->toBe(['ber', 'muc']);

    $total = $layout[2]['items'][2];
    expect($total['type'])->toBe('listener')
        ->and($total['id'])->toStartWith('listener-')
        ->and($total['listen'])->toBe(['price', 'quantity']);

    // The id survives a second compile — the SPA names the listener by it.
    $again = $this->getJson('/api/admin/test-listener/state')->json('payload.layout.0.items.2.items.2.id');
    expect($again)->toBe($total['id']);
});

it('re-renders a closure listener with the posted state', function (): void {
    listenerActingAs($this, ['admin.listener-screen.view']);

    $response = $this->postJson('/api/admin/test-listener/listener', [
        'listener' => 'cities',
        'state' => ['country' => 'ru', 'city' => 'ber'],
    ]);

    $response->assertOk();
    expect($response->json('payload.listener'))->toBe('cities')
        ->and($response->json('payload.state'))->toBe([])
        ->and($response->json('payload.layouts'))->toHaveCount(1)
        ->and($response->json('payload.layouts.0.name'))->toBe('city')
        ->and(array_column($response->json('payload.layouts.0.attributes.options'), 'value'))->toBe(['msk', 'spb']);
});

it('runs a screen-method handler and returns its state patch', function (): void {
    listenerActingAs($this, ['admin.listener-screen.view']);

    $id = $this->getJson('/api/admin/test-listener/state')->json('payload.layout.0.items.2.items.2.id');

    $response = $this->postJson('/api/admin/test-listener/listener', [
        'listener' => $id,
        'state' => ['price' => 2.5, 'quantity' => 4, 'total' => 0],
    ]);

    $response->assertOk();
    expect($response->json('payload.state'))->toBe(['total' => 10])
        ->and($response->json('payload.layouts.0.name'))->toBe('total');
});

it('answers 404 for a listener the screen does not declare', function (): void {
    listenerActingAs($this, ['*']);

    $this->postJson('/api/admin/test-listener/listener', [
        'listener' => 'recalculateTotal',
        'state' => [],
    ])->assertNotFound()->assertJsonPath('payload.errorKey', 'listener_not_found');
});

it('never runs a reserved screen method as a handler', function (): void {
    listenerActingAs($this, ['*']);

    $this->postJson('/api/admin/test-listener/listener', [
        'listener' => 'reserved',
        'state' => [],
    ])->assertNotFound()->assertJsonPath('payload.errorKey', 'listener_handler_not_callable');
});

it('answers 422 without a listener id or with a non-object state', function (): void {
    listenerActingAs($this, ['*']);

    $this->postJson('/api/admin/test-listener/listener', ['state' => []])->assertStatus(422);
    $this->postJson('/api/admin/test-listener/listener', ['listener' => 'cities', 'state' => 'x'])->assertStatus(422);
});

it('applies the screen permission to the listener endpoint', function (): void {
    listenerActingAs($this, ['admin.other.view']);

    $this->postJson('/api/admin/test-listener/listener', [
        'listener' => 'cities',
        'state' => ['country' => 'ru'],
    ])->assertForbidden();
});

it('serves a resource form listener with a resource-method handler', function (): void {
    listenerActingAs($this, ['admin.listener-users.view', 'admin.listener-users.create']);

    $response = $this->postJson('/api/admin/listener-users/listener', [
        'listener' => 'greeting',
        'context' => 'create',
        'state' => ['name' => 'Ann'],
    ]);

    $response->assertOk();
    expect($response->json('payload.state'))->toBe(['greeting' => 'Hello, Ann'])
        ->and($response->json('payload.layouts'))->toHaveCount(2)
        ->and(array_column($response->json('payload.layouts.0.attributes.options'), 'value'))->toBe(['guest'])
        // Rendered with the patched state.
        ->and($response->json('payload.layouts.1.label'))->toBe('Hello, Ann');
});

it('checks the form permission of the context on a resource listener', function (): void {
    listenerActingAs($this, ['admin.listener-users.view', 'admin.listener-users.create']);

    // An id means the edit form, which needs update.
    $this->postJson('/api/admin/listener-users/listener', [
        'listener' => 'greeting',
        'id' => 1,
        'state' => ['name' => 'Ann'],
    ])->assertForbidden();

    $this->postJson('/api/admin/listener-users/listener', [
        'listener' => 'greeting',
        'context' => 'delete',
        'state' => [],
    ])->assertStatus(422);
});

it('denies a resource listener without the view permission', function (): void {
    listenerActingAs($this, ['admin.listener-users.create']);

    $this->postJson('/api/admin/listener-users/listener', [
        'listener' => 'greeting',
        'context' => 'create',
        'state' => [],
    ])->assertForbidden();
});

it('answers 404 for a listener outside the resource form', function (): void {
    listenerActingAs($this, ['*']);

    $this->postJson('/api/admin/listener-users/listener', [
        'listener' => 'cities',
        'context' => 'update',
        'state' => [],
    ])->assertNotFound()->assertJsonPath('payload.errorKey', 'listener_not_found');
});

it('registers the resource listener route only for forms that have listeners', function (): void {
    $resources = app(ResourceRegistry::class);
    $resources->add(TestUserResource::class);
    AdminApi::clearCache();
    listenerActingAs($this, ['*']);

    $this->postJson('/api/admin/test-users/listener', ['listener' => 'x', 'state' => []])
        ->assertNotFound();
});
