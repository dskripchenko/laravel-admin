<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    // Таблицы admin_roles + admin_role_assignments уже созданы миграциями
    // через AdminServiceProvider::loadMigrationsFrom().

    Route::group(['middleware' => ['web']], function (): void {
        Route::get('/test/protected', fn () => response()->json(['ok' => true]))
            ->middleware(AdminAccess::class.':admin.users.view')
            ->name('test.protected');

        Route::get('/test/multi', fn () => response()->json(['ok' => true]))
            ->middleware(AdminAccess::class.':admin.users.view;admin.users.update')
            ->name('test.multi');
    });
});

it('AdminAccess: 401 when not authenticated', function (): void {
    $response = $this->getJson('/test/protected');
    $response->assertStatus(401);
    expect($response->json('payload.errorKey'))->toBe('unauthenticated');
});

it('AdminAccess: 403 when authenticated but lacks permission', function (): void {
    $admin = AdminUser::create([
        'name' => 'No Perms',
        'email' => 'no-perms-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $response = $this->getJson('/test/protected');
    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('forbidden');
});

it('AdminAccess: 200 when permission is granted via role', function (): void {
    $role = Role::create([
        'name' => 'Viewer',
        'slug' => 'viewer',
        'permissions' => ['admin.users.view'],
    ]);

    $admin = AdminUser::create([
        'name' => 'Viewer User',
        'email' => 'viewer-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');

    $response = $this->getJson('/test/protected');
    $response->assertOk();
});

it('AdminAccess: 403 when only one of multiple required permissions present', function (): void {
    $role = Role::create([
        'name' => 'Partial',
        'slug' => 'partial',
        'permissions' => ['admin.users.view'],
    ]);

    $admin = AdminUser::create([
        'name' => 'Partial User',
        'email' => 'partial-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');

    $response = $this->getJson('/test/multi');
    $response->assertStatus(403);
});

it('AdminAccess: 200 when all required permissions present', function (): void {
    $role = Role::create([
        'name' => 'Full',
        'slug' => 'full',
        'permissions' => ['admin.users.view', 'admin.users.update'],
    ]);

    $admin = AdminUser::create([
        'name' => 'Full User',
        'email' => 'full-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');

    $response = $this->getJson('/test/multi');
    $response->assertOk();
});

it('AdminAccess: passes through when permission string is empty', function (): void {
    Route::get('/test/empty', fn () => response()->json(['ok' => true]))
        ->middleware([AdminAccess::class])
        ->name('test.empty');

    // Без авторизации
    $response = $this->getJson('/test/empty');
    $response->assertOk();
});
