<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;

/**
 * Layout::kanban (and calendar) talk to their screen through runMethod: the
 * new markup after a change, the lock. The methods they name are declared,
 * so the layout's visibility and the permissions guard them; a user without
 * the permission gets a read-only board.
 */
function documentsActingWith(array $permissions): void
{
    $admin = AdminUser::create([
        'name' => 'D', 'email' => 'd-'.uniqid().'@example.com', 'password' => 'secret',
    ]);
    $admin->assignRole(Role::create([
        'name' => 'D', 'slug' => 'd-'.uniqid(), 'permissions' => $permissions,
    ]));
    test()->actingAs($admin->refresh(), 'admin');
}

beforeEach(function (): void {
    $registry = app(ScreenRegistry::class);
    $registry->clear();
    $registry->add(TestDocumentScreen::class);
    AdminApi::clearCache();
    TestDocumentScreen::$calls = [];
    TestDocumentScreen::$saved = null;
    TestDocumentScreen::$lockedTo = null;
});

it('serializes the document layouts into the screen state', function (): void {
    documentsActingWith(['*']);

    $layout = $this->getJson('/api/admin/test-document/state')->assertOk()->json('payload.layout');

    expect($layout)->toHaveCount(2)
        ->and($layout[0]['type'])->toBe('kanban')
        ->and($layout[0]['title'])->toBe('Tasks')
        ->and($layout[0]['source'])->toBe('board')
        ->and($layout[0]['content'])->toBeNull()
        ->and($layout[0]['editable'])->toBeTrue()
        ->and($layout[0]['saveMethod'])->toBe('saveBoard')
        ->and($layout[0]['lockable'])->toBeTrue()
        ->and($layout[0]['lockMethod'])->toBe('lockBoard')
        ->and($layout[0]['lockedSource'])->toBe('board_locked')
        ->and($layout[1]['type'])->toBe('diagram')
        ->and($layout[1]['syntax'])->toBe('mermaid')
        ->and($layout[1]['source'])->toBe('schema');
});

it('saves the new markup through the screen method', function (): void {
    documentsActingWith(['*']);
    // Without a trailing newline: the request's strings are trimmed by the app.
    $markup = "## To do\n## Done\n- Write docs";

    $this->postJson('/api/admin/test-document/runMethod', [
        'method' => 'saveBoard',
        'parameters' => [$markup],
    ])->assertOk();

    expect(TestDocumentScreen::$saved)->toBe($markup);
});

it('answers a refused change with a 422 and the reason', function (): void {
    documentsActingWith(['*']);

    $refused = $this->postJson('/api/admin/test-document/runMethod', [
        'method' => 'saveBoard',
        'parameters' => ["## To do\n- forbidden\n"],
    ]);

    $refused->assertStatus(422);
    expect($refused->json('payload.errorKey'))->toBe('action_failed')
        ->and($refused->json('payload.message'))->toBe('This board cannot hold that card')
        ->and(TestDocumentScreen::$saved)->toBeNull();
});

it('locks the board through the screen method', function (): void {
    documentsActingWith(['*']);

    $this->postJson('/api/admin/test-document/runMethod', [
        'method' => 'lockBoard',
        'parameters' => [true],
    ])->assertOk();

    expect(TestDocumentScreen::$lockedTo)->toBeTrue();
});

it('makes the board read-only for a user without the permissions and refuses the methods', function (): void {
    documentsActingWith(['admin.documents.view']);

    $layout = $this->getJson('/api/admin/test-document/state')->assertOk()->json('payload.layout.0');
    expect($layout['editable'])->toBeFalse()
        ->and($layout['saveMethod'])->toBeNull()
        ->and($layout['lockable'])->toBeFalse()
        ->and($layout['lockMethod'])->toBeNull();

    $this->postJson('/api/admin/test-document/runMethod', [
        'method' => 'saveBoard',
        'parameters' => ['## X'],
    ])->assertStatus(403);
    $this->postJson('/api/admin/test-document/runMethod', [
        'method' => 'lockBoard',
        'parameters' => [true],
    ])->assertStatus(403);

    expect(TestDocumentScreen::$calls)->toBe([]);
});

it('lets editing without locking for a user holding one permission', function (): void {
    documentsActingWith(['admin.board.edit']);

    $layout = $this->getJson('/api/admin/test-document/state')->assertOk()->json('payload.layout.0');
    expect($layout['editable'])->toBeTrue()
        ->and($layout['lockable'])->toBeFalse();

    $this->postJson('/api/admin/test-document/runMethod', [
        'method' => 'saveBoard',
        'parameters' => ['## X'],
    ])->assertOk();
    $this->postJson('/api/admin/test-document/runMethod', [
        'method' => 'lockBoard',
        'parameters' => [true],
    ])->assertStatus(403);

    expect(TestDocumentScreen::$calls)->toBe(['saveBoard']);
});

it('refuses the methods of a board the user cannot see', function (): void {
    documentsActingWith(['*']);

    $this->postJson('/api/admin/test-document/runMethod', [
        'method' => 'saveSecret',
        'parameters' => ['## X'],
    ])->assertStatus(403);

    expect(TestDocumentScreen::$calls)->toBe([]);
});
