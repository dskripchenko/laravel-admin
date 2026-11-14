<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess;
use Dskripchenko\LaravelAdmin\Permission\PermissionRegistry;
use Illuminate\Support\Facades\Route;

/**
 * A refusal names the permission by its label from the registry, not by its
 * key; a key no group declares is named as it is.
 */
beforeEach(function (): void {
    app(PermissionRegistry::class)->add(
        ItemPermission::group('Тестовый раздел')
            ->addPermission('test.labels.view', 'Отчёты: просмотр')
            ->addPermission('test.labels.export', 'Отчёты: выгрузка'),
    );

    Route::group(['middleware' => ['web']], function (): void {
        Route::get('/test/labels/one', fn () => response()->json(['ok' => true]))
            ->middleware(AdminAccess::class.':test.labels.view');
        Route::get('/test/labels/unknown', fn () => response()->json(['ok' => true]))
            ->middleware(AdminAccess::class.':test.labels.unknown');
        Route::get('/test/labels/any', fn () => response()->json(['ok' => true]))
            ->middleware(AdminAccess::class.':test.labels.view;test.labels.export,any');
    });

    $this->actingAs(AdminUser::create([
        'name' => 'No Perms',
        'email' => 'labels-'.uniqid().'@example.com',
        'password' => 'secret',
    ]), 'admin');
});

it('PermissionRegistry::label() gives the label of a key, the key when unknown', function (): void {
    $registry = app(PermissionRegistry::class);

    expect($registry->label('test.labels.view'))->toBe('Отчёты: просмотр');
    expect($registry->label('test.labels.missing'))->toBe('test.labels.missing');
    expect(PermissionRegistry::labelOf('test.labels.export'))->toBe('Отчёты: выгрузка');
    expect(PermissionRegistry::labelOf(''))->toBe('');
});

it('PermissionRegistry::label() translates the label in the current locale', function (): void {
    app('translator')->addJsonPath(__DIR__.'/../Fixtures/permission-labels-lang');
    app()->setLocale('en');

    expect(PermissionRegistry::labelOf('test.labels.view'))->toBe('Reports: view');
});

it('a 403 of AdminAccess names the permission by its label and keeps the key apart', function (): void {
    $response = $this->getJson('/test/labels/one');

    $response->assertStatus(403);
    expect($response->json('payload.message'))->toContain('Отчёты: просмотр')
        ->not->toContain('test.labels.view');
    // The key stays in its own field: the "no access" state offers to copy it.
    expect($response->json('payload.permission'))->toBe('test.labels.view');
});

it('a 403 of AdminAccess names an undeclared permission by its key', function (): void {
    $response = $this->getJson('/test/labels/unknown');

    $response->assertStatus(403);
    expect($response->json('payload.message'))->toContain('test.labels.unknown');
});

it('a 403 of AdminAccess in the any mode names every permission by its label', function (): void {
    $response = $this->getJson('/test/labels/any');

    $response->assertStatus(403);
    expect($response->json('payload.message'))->toContain('Отчёты: просмотр | Отчёты: выгрузка');
    expect($response->json('payload.permission'))->toBe('test.labels.view | test.labels.export');
});

it('«Нет права» of an impersonation names the permission by its label', function (): void {
    config()->set('admin.auth.impersonation.permission', 'test.labels.view');
    $target = AdminUser::create([
        'name' => 'Target',
        'email' => 'labels-target-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $response = $this->postJson('/api/admin/auth/startImpersonation', ['user_id' => $target->id]);

    $response->assertStatus(403);
    expect($response->json('payload.message'))->toContain('Отчёты: просмотр')->not->toContain('test.labels.view');
});
