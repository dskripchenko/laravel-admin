<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every way the server refuses an action carries a reason the panel can show:
 * the SPA reads `payload.message` (and the field messages of a validation
 * error) instead of a generic "could not run the action".
 */
function refusalActingAs(array $permissions): void
{
    $admin = AdminUser::create([
        'name' => 'U', 'email' => 'r-'.uniqid().'@example.com', 'password' => 'secret',
    ]);
    $admin->assignRole(Role::create([
        'name' => 'R', 'slug' => 'r-'.uniqid(), 'permissions' => $permissions,
    ]));
    test()->actingAs($admin->refresh(), 'admin');
}

beforeEach(function (): void {
    $resources = app(ResourceRegistry::class);
    $resources->clear();
    $resources->add(TestActionResource::class);
    $resources->add(TestGuardedActionResource::class);
    $screens = app(ScreenRegistry::class);
    $screens->clear();
    $screens->add(TestContactScreen::class);
    AdminApi::clearCache();

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

it('gives the reason of an ActionFailedException from a resource action', function (): void {
    refusalActingAs(['*']);
    $record = TestResourceUserModel::create(['name' => 'A']);

    $response = $this->postJson('/api/admin/test-actions/action', ['key' => 'check-link', 'ids' => [$record->id]]);

    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('action_failed')
        ->and($response->json('payload.message'))->toBe('Не удалось подключиться: хост не отвечает');
});

it('gives the reason of an ActionFailedException from a screen method', function (): void {
    refusalActingAs(['*']);

    $response = $this->postJson('/api/admin/test-contact/runMethod', ['method' => 'refuse', 'payload' => []]);

    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('action_failed')
        ->and($response->json('payload.message'))->toBe('SMTP-сервер недоступен');
});

it('gives the reason of a forbidden action', function (): void {
    refusalActingAs(['admin.test-guarded.view', 'admin.test-guarded.update']);
    $record = TestResourceUserModel::create(['name' => 'A']);

    $response = $this->postJson('/api/admin/test-guarded/action', ['key' => 'guarded-bulk', 'ids' => [$record->id]]);

    $response->assertStatus(403);
    expect($response->json('payload.message'))->toBeString()->not->toBe('')
        ->and($response->json('payload.message'))->toContain('admin.test-guarded.archive');
});

it('gives the field messages of a modal action payload that fails validation', function (): void {
    refusalActingAs(['*']);
    $record = TestResourceUserModel::create(['name' => 'A']);

    $response = $this->postJson('/api/admin/test-actions/action', [
        'key' => 'change-status', 'ids' => [$record->id], 'payload' => [],
    ]);

    $response->assertStatus(422);
    $messages = $response->json('payload.messages') ?? $response->json('payload.errors');
    expect($messages)->toBeArray()->toHaveKey('status')
        ->and($messages['status'][0])->toBeString()->not->toBe('');
});

it('gives the field messages of an action method that validates by itself', function (): void {
    refusalActingAs(['*']);
    $record = TestResourceUserModel::create(['name' => 'A']);

    $response = $this->postJson('/api/admin/test-actions/action', ['key' => 'self-validating', 'ids' => [$record->id]]);

    $response->assertStatus(422);
    $messages = $response->json('payload.messages') ?? $response->json('payload.errors');
    expect($messages)->toBeArray()->toHaveKey('note')
        ->and($messages['note'][0])->toBeString()->not->toBe('');
});

it('gives the reason when an async action is refused at its start', function (): void {
    refusalActingAs(['*']);

    $response = $this->postJson('/api/admin/delayed/run', [
        'entity' => TestResourceUserModel::class,
        'method' => 'save',
    ]);

    $response->assertStatus(403);
    expect($response->json('payload.message'))->toBeString()->not->toBe('');
});
