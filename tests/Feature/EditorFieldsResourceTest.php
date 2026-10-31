<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The editor fields on a resource form: what the SPA submits — the BPMN/DMN
 * XML, the kanban and calendar markup, Markdown — is saved as it is, and a
 * file the rules refuse comes back as a 422 with the field's error.
 */
beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestEditorFieldsResource::class);
    AdminApi::clearCache();

    Schema::create('test_editor_records', function (Blueprint $t): void {
        $t->id();
        $t->string('title');
        $t->mediumText('process')->nullable();
        $t->mediumText('rules')->nullable();
        $t->text('board')->nullable();
        $t->text('schedule')->nullable();
        $t->text('notes')->nullable();
        $t->timestamps();
    });

    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create(['name' => 'S', 'slug' => 's-'.uniqid(), 'permissions' => ['*']]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('saves a kanban markup, a BPMN and a DMN file, a calendar and Markdown on update', function (): void {
    $record = TestEditorRecord::create(['title' => 'Order']);
    $bpmn = (string) file_get_contents(__DIR__.'/../Fixtures/models/order.bpmn');
    $dmn = (string) file_get_contents(__DIR__.'/../Fixtures/models/loan.dmn');
    $board = "## To do {wip: 3}\n- Write the brief #docs {who: Anna, due: 2026-12-01}\n\n## Done {done}\n- Kick-off";
    $schedule = "2026-12-01 10:00-11:00 Release review #release\nevery mon 09:30-09:45 Stand-up";

    $this->postJson('/api/admin/test-editor-fields/update', [
        'id' => $record->id,
        'title' => 'Order',
        'process' => $bpmn,
        'rules' => $dmn,
        'board' => $board,
        'schedule' => $schedule,
        'notes' => "# Notes\n\n```kanban\n## To do\n- Card\n```",
    ])->assertOk();

    $stored = $record->fresh();
    // The panel trims the strings it receives; the files end in a newline.
    expect($stored->process)->toBe(trim($bpmn))
        ->and($stored->rules)->toBe(trim($dmn))
        ->and($stored->board)->toBe($board)
        ->and($stored->schedule)->toBe($schedule)
        ->and($stored->notes)->toContain('```kanban');
});

it('refuses an invalid BPMN file with a 422 and the field error', function (): void {
    $record = TestEditorRecord::create(['title' => 'Order', 'process' => null]);

    $response = $this->postJson('/api/admin/test-editor-fields/update', [
        'id' => $record->id,
        'title' => 'Order',
        'process' => '<definitions xmlns="http://example.com/not-bpmn"/>',
    ]);

    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('validation')
        ->and($response->json('payload.messages.process.0'))->toContain('http://example.com/not-bpmn');

    expect($record->fresh()->process)->toBeNull();
});

it('refuses a kanban markup over the field\'s length cap', function (): void {
    $record = TestEditorRecord::create(['title' => 'Order']);

    $response = $this->postJson('/api/admin/test-editor-fields/update', [
        'id' => $record->id,
        'title' => 'Order',
        'board' => str_repeat('x', 2001),
    ]);

    $response->assertStatus(422);
    expect($response->json('payload.messages'))->toHaveKey('board');
});
