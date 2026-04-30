<?php

declare(strict_types=1);

use Dskripchenko\DelayedProcess\Models\DelayedProcess;
use Dskripchenko\LaravelAdmin\Action\AsyncAction;
use Dskripchenko\LaravelAdmin\DelayedProcess\AllowlistRegistrar;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;

/**
 * Простой handler для async-action тестов.
 */
final class TestAsyncHandler
{
    public function process(string $message): array
    {
        return ['ok' => true, 'message' => $message];
    }
}

beforeEach(function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'S', 'slug' => 's-'.uniqid(),
        'permissions' => ['*'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');

    /** @var AllowlistRegistrar $registry */
    $registry = app(AllowlistRegistrar::class);
    $registry->clear();
});

it('AsyncAction has type=async and serializes handler/params/callback', function (): void {
    $a = AsyncAction::make('Recompute stats')
        ->handler(TestAsyncHandler::class, 'process')
        ->withParams(['message' => 'hi'])
        ->callback('https://example.com/webhook')
        ->pollInterval(5);

    $arr = $a->toArray();
    expect($arr['type'])->toBe('async');
    expect($arr['attributes']['handler'])->toBe([
        'entity' => TestAsyncHandler::class,
        'method' => 'process',
    ]);
    expect($arr['attributes']['params'])->toBe(['message' => 'hi']);
    expect($arr['attributes']['callback'])->toBe('https://example.com/webhook');
    expect($arr['attributes']['pollInterval'])->toBe(5);
});

it('AsyncAction::pollInterval clamps to 1 minimum', function (): void {
    $a = AsyncAction::make('X')->pollInterval(0);
    expect($a->toArray()['attributes']['pollInterval'])->toBe(1);
});

it('AllowlistRegistrar accepts existing class + tracks methods', function (): void {
    $r = app(AllowlistRegistrar::class);
    $r->allow(TestAsyncHandler::class, 'process');
    $r->allow(TestAsyncHandler::class, 'process'); // дубль игнорируется
    $r->allow(TestAsyncHandler::class, 'other');

    expect($r->isAllowed(TestAsyncHandler::class, 'process'))->toBeTrue();
    expect($r->isAllowed(TestAsyncHandler::class, 'other'))->toBeTrue();
    expect($r->isAllowed(TestAsyncHandler::class, 'unknown'))->toBeFalse();
    expect($r->all()[TestAsyncHandler::class])->toBe(['process', 'other']);
});

it('AllowlistRegistrar rejects nonexistent class', function (): void {
    $r = app(AllowlistRegistrar::class);
    expect(fn () => $r->allow('NoSuch\\Class', 'method'))
        ->toThrow(InvalidArgumentException::class);
});

it('delayed.run returns 403 for not-allowlisted handler', function (): void {
    $response = $this->postJson('/api/admin/delayed/run', [
        'entity' => TestAsyncHandler::class,
        'method' => 'process',
    ]);
    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('forbidden');
});

it('delayed.run creates DelayedProcess for allowlisted handler', function (): void {
    /** @var AllowlistRegistrar $r */
    $r = app(AllowlistRegistrar::class);
    $r->allow(TestAsyncHandler::class, 'process');

    $response = $this->postJson('/api/admin/delayed/run', [
        'entity' => TestAsyncHandler::class,
        'method' => 'process',
        'params' => ['hello'],
        'callback' => 'https://example.com/cb',
    ]);

    $response->assertOk();
    expect($response->json('payload.uuid'))->toBeString();

    $process = DelayedProcess::where('uuid', $response->json('payload.uuid'))->first();
    expect($process)->not->toBeNull();
    expect($process->entity)->toBe(TestAsyncHandler::class);
    expect($process->method)->toBe('process');
    expect($process->callback_url)->toBe('https://example.com/cb');
});

it('delayed.run validates input', function (): void {
    $this->postJson('/api/admin/delayed/run', [])->assertStatus(422);
    $this->postJson('/api/admin/delayed/run', [
        'entity' => 'X',
        // method missing
    ])->assertStatus(422);
});

it('delayed.status returns process info', function (): void {
    /** @var AllowlistRegistrar $r */
    $r = app(AllowlistRegistrar::class);
    $r->allow(TestAsyncHandler::class, 'process');

    $created = $this->postJson('/api/admin/delayed/run', [
        'entity' => TestAsyncHandler::class,
        'method' => 'process',
    ])->json('payload');

    $response = $this->getJson('/api/admin/delayed/status?uuid='.$created['uuid']);
    $response->assertOk();
    expect($response->json('payload.uuid'))->toBe($created['uuid']);
    expect($response->json('payload.status'))->toBeString();
});

it('delayed.status returns 404 for unknown uuid', function (): void {
    $response = $this->getJson('/api/admin/delayed/status?uuid=nonexistent');
    $response->assertStatus(404);
});
