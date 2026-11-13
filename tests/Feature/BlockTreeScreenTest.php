<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;

/**
 * Layout::blockTree on a screen: the tree in the screen's state, the draft,
 * publish and discard through runMethod with the whole state, the methods
 * guarded by their permissions, the fingerprint conflict and the validator's
 * errors in the answer.
 */
function blockTreeActingWith(array $permissions): void
{
    $admin = AdminUser::create([
        'name' => 'B', 'email' => 'b-'.uniqid().'@example.com', 'password' => 'secret',
    ]);
    $admin->assignRole(Role::create([
        'name' => 'B', 'slug' => 'b-'.uniqid(), 'permissions' => $permissions,
    ]));
    test()->actingAs($admin->refresh(), 'admin');
}

beforeEach(function (): void {
    $registry = app(ScreenRegistry::class);
    $registry->clear();
    $registry->add(TestBlockTreeScreen::class);
    AdminApi::clearCache();
    TestBlockTreeScreen::reset();
});

function blockTreeState(): array
{
    return test()->getJson('/api/admin/test-block-tree/state')->assertOk()->json('payload');
}

it('puts the tree into the state and the editor into the layout', function (): void {
    blockTreeActingWith(['*']);

    $payload = blockTreeState();
    $layout = $payload['layout'][0];

    expect($payload['state']['content']['nodes'])->toHaveCount(2)
        ->and($payload['state']['content']['fingerprint'])->toBeString()
        ->and($layout['type'])->toBe('block_tree')
        ->and($layout['source'])->toBe('content')
        ->and($layout['draftMethod'])->toBe('saveDraft')
        ->and($layout['publishMethod'])->toBe('publish')
        ->and($layout['discardMethod'])->toBe('discardDraft')
        ->and($layout['readOnly'])->toBeFalse()
        ->and($layout['previewUrl'])->toBe('/test-preview')
        ->and(array_column($layout['types'], 'type'))->toBe(['hero', 'columns', 'text']);
});

it('saves the draft with the whole state and refuses a tree out of the rules', function (): void {
    blockTreeActingWith(['*']);
    $state = blockTreeState()['state'];
    $state['content']['nodes'][] = ['ref' => 'new-1', 'type' => 'columns', 'data' => [], 'children' => [
        ['ref' => 'new-2', 'type' => 'text', 'data' => ['body' => 'Inside'], 'children' => []],
    ]];

    $saved = $this->postJson('/api/admin/test-block-tree/runMethod', ['method' => 'saveDraft', 'parameters' => [$state]])
        ->assertOk();
    expect($saved->json('payload.extra.blockTree.savedAt'))->toBeString()
        ->and(TestBlockTreeScreen::$draft)->toHaveCount(3);

    // A hero inside the columns: the validator's ValidationException, keyed by the path.
    $state['content']['nodes'][2]['children'][0]['type'] = 'hero';
    $refused = $this->postJson('/api/admin/test-block-tree/runMethod', ['method' => 'saveDraft', 'parameters' => [$state]]);
    $refused->assertStatus(422);
    expect(json_encode($refused->json('payload')))->toContain('content.nodes.2.children.0');
});

it('publishes, answering with the new value', function (): void {
    blockTreeActingWith(['*']);
    $state = blockTreeState()['state'];
    $state['content']['nodes'][0]['data']['title'] = 'Hi';

    $res = $this->postJson('/api/admin/test-block-tree/runMethod', ['method' => 'publish', 'parameters' => [$state]])
        ->assertOk();

    expect($res->json('payload.message'))->toBe('Published')
        ->and($res->json('payload.extra.blockTree.value.nodes.0.data.title'))->toBe('Hi')
        ->and(TestBlockTreeScreen::$published[0]['data']['title'])->toBe('Hi');
});

it('stops a publish over a changed fingerprint and over invalid data', function (): void {
    blockTreeActingWith(['*']);
    $state = blockTreeState()['state'];

    $state['content']['fingerprint'] = 'stale';
    $conflict = $this->postJson('/api/admin/test-block-tree/runMethod', ['method' => 'publish', 'parameters' => [$state]])
        ->assertOk();
    expect($conflict->json('payload.extra.blockTree.conflict.message'))->toBe('The page was changed by someone else')
        ->and($conflict->json('payload.extra.blockTree.conflict.differences'))->toBe(['Hero: title']);

    $state = blockTreeState()['state'];
    $state['content']['nodes'][0]['data']['title'] = str_repeat('x', 50);
    $invalid = $this->postJson('/api/admin/test-block-tree/runMethod', ['method' => 'publish', 'parameters' => [$state]])
        ->assertOk();
    expect($invalid->json('payload.extra.blockTree.errors.0'))->toMatchArray(['ref' => 1, 'path' => '0', 'field' => 'title'])
        ->and(TestBlockTreeScreen::$published[0]['data']['title'])->toBe('Welcome');
});

it('gives a read-only editor and refuses the methods without the permissions', function (): void {
    blockTreeActingWith(['admin.pages.view']);

    $layout = blockTreeState()['layout'][0];
    expect($layout['readOnly'])->toBeTrue()
        ->and($layout['draftMethod'])->toBeNull()
        ->and($layout['publishMethod'])->toBeNull();

    $state = blockTreeState()['state'];
    $this->postJson('/api/admin/test-block-tree/runMethod', ['method' => 'publish', 'parameters' => [$state]])->assertStatus(403);
    $this->postJson('/api/admin/test-block-tree/runMethod', ['method' => 'saveDraft', 'parameters' => [$state]])->assertStatus(403);
    expect(TestBlockTreeScreen::$calls)->toBe([]);
});

it('lets an editor save drafts but not publish', function (): void {
    blockTreeActingWith(['admin.pages.edit']);

    $layout = blockTreeState()['layout'][0];
    expect($layout['readOnly'])->toBeFalse()
        ->and($layout['draftMethod'])->toBe('saveDraft')
        ->and($layout['publishMethod'])->toBeNull();

    $state = blockTreeState()['state'];
    $this->postJson('/api/admin/test-block-tree/runMethod', ['method' => 'saveDraft', 'parameters' => [$state]])->assertOk();
    $this->postJson('/api/admin/test-block-tree/runMethod', ['method' => 'publish', 'parameters' => [$state]])->assertStatus(403);
    $discarded = $this->postJson('/api/admin/test-block-tree/runMethod', ['method' => 'discardDraft', 'parameters' => [$state]])->assertOk();

    expect(TestBlockTreeScreen::$calls)->toBe(['saveDraft', 'discardDraft'])
        ->and($discarded->json('payload.extra.blockTree.value.nodes'))->toHaveCount(2);
});
