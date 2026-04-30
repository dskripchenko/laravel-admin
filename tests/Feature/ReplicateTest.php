<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    AdminApi::clearCache();

    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('name')->nullable();
        $t->string('email')->nullable();
        $t->string('password')->nullable();
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

it('replicate action duplicates record with default name suffix', function (): void {
    app(ResourceRegistry::class)->add(TestReplicableResource::class);
    AdminApi::clearCache();

    $r = TestResourceUserModel::create([
        'name' => 'Original',
        'email' => 'orig@example.com',
        'password' => 'p',
    ]);

    $response = $this->postJson('/api/admin/test-replicables/replicate', ['id' => $r->id]);
    $response->assertOk();
    expect($response->json('payload.record.name'))->toBe('Original (копия)');
    // email регенерирован hook'ом подкласса.
    expect($response->json('payload.record.email'))->not->toBe('orig@example.com');
    expect(TestResourceUserModel::count())->toBe(2);
});

it('replicate returns 422 when resource is not replicable', function (): void {
    app(ResourceRegistry::class)->add(TestUserResource::class);
    AdminApi::clearCache();
    $r = TestResourceUserModel::create([
        'name' => 'X',
        'email' => 'x@example.com',
        'password' => 'p',
    ]);

    $response = $this->postJson('/api/admin/test-users/replicate', ['id' => $r->id]);
    $response->assertStatus(422);
    expect($response->json('payload.message'))->toContain('not replicable');
});

it('replicate returns 404 if record missing', function (): void {
    app(ResourceRegistry::class)->add(TestReplicableResource::class);
    AdminApi::clearCache();

    $response = $this->postJson('/api/admin/test-replicables/replicate', ['id' => 99999]);
    $response->assertStatus(404);
});

it('replicate requires admin.{slug}.replicate permission', function (): void {
    app(ResourceRegistry::class)->add(TestReplicableResource::class);
    AdminApi::clearCache();

    $user = AdminUser::create([
        'name' => 'X', 'email' => 'x-'.uniqid().'@example.com', 'password' => 'p',
    ]);
    $role = Role::create([
        'name' => 'V', 'slug' => 'v-'.uniqid(),
        'permissions' => ['admin.test-replicables.view'],
    ]);
    $user->assignRole($role);
    $this->actingAs($user->refresh(), 'admin');

    $r = TestResourceUserModel::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'p']);
    $this->postJson('/api/admin/test-replicables/replicate', ['id' => $r->id])
        ->assertStatus(403);
});

it('Resource::replicate hook applies (копия) suffix to name/title/slug by default', function (): void {
    $original = TestResourceUserModel::create(['name' => 'Foo', 'email' => 'f@example.com', 'password' => 'p']);
    $copy = (new TestUserResource)->replicate($original);
    expect($copy->name)->toBe('Foo (копия)');
});

it('meta.features.replicable=true when replicable() returns true', function (): void {
    expect((new TestReplicableResource)->meta()['features']['replicable'])->toBeTrue();
    expect((new TestUserResource)->meta()['features']['replicable'])->toBeFalse();
});
