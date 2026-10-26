<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;

/**
 * Layout::flow talks to its screen through runMethod with positional
 * parameters: the monitoring snapshot, the toggle, the referenced
 * sub-process, the drawer's actions. The methods it names are declared, so
 * the layout's visibility and the toggle's permission guard them.
 */
function flowActingWith(array $permissions): void
{
    $admin = AdminUser::create([
        'name' => 'F', 'email' => 'f-'.uniqid().'@example.com', 'password' => 'secret',
    ]);
    $admin->assignRole(Role::create([
        'name' => 'F', 'slug' => 'f-'.uniqid(), 'permissions' => $permissions,
    ]));
    test()->actingAs($admin->refresh(), 'admin');
}

beforeEach(function (): void {
    $registry = app(ScreenRegistry::class);
    $registry->clear();
    $registry->add(TestFlowScreen::class);
    AdminApi::clearCache();
    TestFlowScreen::$calls = [];
    TestFlowScreen::$hideSecret = true;
});

it('serializes the flow layout into the screen state', function (): void {
    flowActingWith(['*']);

    $layout = $this->getJson('/api/admin/test-flow/state')->assertOk()->json('payload.layout.0');

    expect($layout['type'])->toBe('flow')
        ->and($layout['source'])->toBe('process')
        ->and($layout['graph'])->toBeNull()
        ->and($layout['poll'])->toBe(['method' => 'processState', 'interval' => 2000])
        ->and($layout['toggle'])->toBe(['method' => 'toggleStep'])
        ->and($layout['toggleable'])->toBeTrue()
        ->and($layout['subgraphs']['method'])->toBe('subprocess')
        ->and($layout['instanceSource'])->toBe('order_id')
        ->and($layout['actions'][0]['attributes']['method'])->toBe('retryStep');
});

it('answers the poll method with the snapshot in `extra`', function (): void {
    flowActingWith(['*']);

    $response = $this->postJson('/api/admin/test-flow/runMethod', [
        'method' => 'processState',
        'parameters' => [['path' => [], 'ref' => null, 'instance' => 42]],
    ])->assertOk();

    expect($response->json('payload.extra.current'))->toBe(['paid'])
        ->and($response->json('payload.extra.nodes.paid.status'))->toBe('active')
        ->and(TestFlowScreen::$calls[0])->toBe(['processState', ['path' => [], 'ref' => null, 'instance' => 42]]);
});

it('loads a referenced sub-process with the reference and the node', function (): void {
    flowActingWith(['*']);

    $response = $this->postJson('/api/admin/test-flow/runMethod', [
        'method' => 'subprocess',
        'parameters' => ['payment', ['id' => 'paid', 'type' => 'state']],
    ])->assertOk();

    expect($response->json('payload.extra.nodes.0.id'))->toBe('authorize')
        ->and(TestFlowScreen::$calls[0])->toBe(['subprocess', ['payment', 'paid']]);
});

it('persists a toggle, and a refusal is a 422 with the reason', function (): void {
    flowActingWith(['*']);

    $this->postJson('/api/admin/test-flow/runMethod', [
        'method' => 'toggleStep',
        'parameters' => [['kind' => 'node', 'id' => 'paid', 'enabled' => false, 'path' => []]],
    ])->assertOk();

    $refused = $this->postJson('/api/admin/test-flow/runMethod', [
        'method' => 'toggleStep',
        'parameters' => [['kind' => 'node', 'id' => 'start', 'enabled' => false, 'path' => []]],
    ]);

    $refused->assertStatus(422);
    expect($refused->json('payload.errorKey'))->toBe('action_failed')
        ->and($refused->json('payload.message'))->toBe('The start cannot be switched off')
        ->and(TestFlowScreen::$calls)->toHaveCount(2);
});

it('refuses the toggle to a user without its permission and hides the switches', function (): void {
    flowActingWith(['admin.flow.view']);

    $layout = $this->getJson('/api/admin/test-flow/state')->assertOk()->json('payload.layout.0');
    expect($layout['toggleable'])->toBeFalse()
        ->and($layout['toggle'])->toBeNull()
        ->and($layout['actions'])->toBe([]);

    $this->postJson('/api/admin/test-flow/runMethod', [
        'method' => 'toggleStep',
        'parameters' => [['kind' => 'node', 'id' => 'paid', 'enabled' => false, 'path' => []]],
    ])->assertStatus(403);
    $this->postJson('/api/admin/test-flow/runMethod', [
        'method' => 'retryStep',
        'parameters' => [['kind' => 'node', 'id' => 'paid', 'path' => []]],
    ])->assertStatus(403);

    // Reading still works.
    $this->postJson('/api/admin/test-flow/runMethod', [
        'method' => 'processState',
        'parameters' => [['path' => []]],
    ])->assertOk();

    expect(array_column(TestFlowScreen::$calls, 0))->toBe(['processState']);
});

it('refuses the methods of a flow the user cannot see', function (): void {
    flowActingWith(['*']);

    $this->postJson('/api/admin/test-flow/runMethod', [
        'method' => 'secretState',
        'parameters' => [['path' => []]],
    ])->assertStatus(403);

    TestFlowScreen::$hideSecret = false;
    $this->postJson('/api/admin/test-flow/runMethod', [
        'method' => 'secretState',
        'parameters' => [['path' => []]],
    ])->assertOk();
});

it('runs a drawer action with the selection', function (): void {
    flowActingWith(['*']);

    $response = $this->postJson('/api/admin/test-flow/runMethod', [
        'method' => 'retryStep',
        'parameters' => [['kind' => 'node', 'id' => 'paid', 'path' => [], 'instance' => 42]],
    ])->assertOk();

    expect($response->json('payload.message'))->toBe('Retried')
        ->and(TestFlowScreen::$calls[0][1]['id'])->toBe('paid');
});
