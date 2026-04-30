<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AdminAccess middleware привязывается к каждому Resource-action автоматически.
 * Здесь проверяем, что без соответствующего permission'а endpoint отдаёт 403.
 */
beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestUserResource::class);
    AdminApi::clearCache();

    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password');
        $t->timestamps();
    });
});

it('returns 403 when admin lacks admin.test-users.view for meta action', function (): void {
    $admin = AdminUser::create([
        'name' => 'No Perm',
        'email' => 'np-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $response = $this->getJson('/api/admin/test-users/meta');
    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('forbidden');
});

it('returns 200 when admin has admin.test-users.view', function (): void {
    $admin = AdminUser::create([
        'name' => 'Viewer',
        'email' => 'v-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'V', 'slug' => 'v-'.uniqid(),
        'permissions' => ['admin.test-users.view'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');

    $this->getJson('/api/admin/test-users/meta')->assertOk();
});

it('returns 403 for create action without admin.test-users.create', function (): void {
    $admin = AdminUser::create([
        'name' => 'View Only',
        'email' => 'vo-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'VO', 'slug' => 'vo-'.uniqid(),
        'permissions' => ['admin.test-users.view'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');

    $response = $this->postJson('/api/admin/test-users/create', [
        'name' => 'X', 'email' => 'x@example.com', 'password' => 'pwd',
    ]);
    $response->assertStatus(403);
});

it('returns 403 for delete action without admin.test-users.delete', function (): void {
    $admin = AdminUser::create([
        'name' => 'NoDel',
        'email' => 'nd-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'ND', 'slug' => 'nd-'.uniqid(),
        'permissions' => ['admin.test-users.view', 'admin.test-users.create', 'admin.test-users.update'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');

    $r = TestResourceUserModel::create(['name' => 'D', 'email' => 'd@example.com', 'password' => 'p']);

    $response = $this->postJson('/api/admin/test-users/delete', ['id' => $r->id]);
    $response->assertStatus(403);
});

it('wildcard `*` permission grants all Resource actions', function (): void {
    $admin = AdminUser::create([
        'name' => 'Super',
        'email' => 'sp-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'Super', 'slug' => 'sp-'.uniqid(),
        'permissions' => ['*'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');

    $this->getJson('/api/admin/test-users/meta')->assertOk();
    $this->getJson('/api/admin/test-users/listScreen')->assertOk();
    $this->getJson('/api/admin/test-users/createScreen')->assertOk();
});
