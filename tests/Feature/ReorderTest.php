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
    $rr->add(TestReorderableResource::class);
    AdminApi::clearCache();

    Schema::create('reorderable_users', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->integer('position')->default(0);
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

it('reorder updates positions in transaction', function (): void {
    $a = TestReorderableUserModel::create(['name' => 'A', 'position' => 0]);
    $b = TestReorderableUserModel::create(['name' => 'B', 'position' => 1]);
    $c = TestReorderableUserModel::create(['name' => 'C', 'position' => 2]);

    $response = $this->postJson('/api/admin/test-reorderables/reorder', [
        'items' => [
            ['id' => $a->id, 'position' => 2],
            ['id' => $b->id, 'position' => 0],
            ['id' => $c->id, 'position' => 1],
        ],
    ]);

    $response->assertOk();
    expect($response->json('payload.count'))->toBe(3);
    expect($a->fresh()->position)->toBe(2);
    expect($b->fresh()->position)->toBe(0);
    expect($c->fresh()->position)->toBe(1);
});

it('reorder validates items array structure', function (): void {
    $this->postJson('/api/admin/test-reorderables/reorder', [])
        ->assertStatus(422);

    $this->postJson('/api/admin/test-reorderables/reorder', [
        'items' => [['id' => 1]], // нет position
    ])->assertStatus(422);

    $this->postJson('/api/admin/test-reorderables/reorder', [
        'items' => [['id' => 1, 'position' => -1]], // <0
    ])->assertStatus(422);
});

it('reorder returns 422 when resource is not reorderable', function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->add(TestUserResource::class);
    AdminApi::clearCache();

    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->string('email')->nullable();
        $t->string('password')->nullable();
        $t->timestamps();
    });

    $response = $this->postJson('/api/admin/test-users/reorder', [
        'items' => [['id' => 1, 'position' => 0]],
    ]);
    $response->assertStatus(422);
    expect($response->json('payload.message'))->toContain('not reorderable');
});

it('meta.features.reorderable=true exposes reorderColumn', function (): void {
    $meta = (new TestReorderableResource)->meta()['features'];
    expect($meta['reorderable'])->toBeTrue();
    expect($meta['reorderColumn'])->toBe('position');
});

it('meta.features.reorderColumn is null when reorderable=false', function (): void {
    $meta = (new TestUserResource)->meta()['features'];
    expect($meta['reorderable'])->toBeFalse();
    expect($meta['reorderColumn'])->toBeNull();
});

it('reorder requires admin.{slug}.reorder permission', function (): void {
    $user = AdminUser::create([
        'name' => 'X', 'email' => 'x-'.uniqid().'@example.com', 'password' => 'p',
    ]);
    $role = Role::create([
        'name' => 'V', 'slug' => 'v-'.uniqid(),
        'permissions' => ['admin.test-reorderables.view'],
    ]);
    $user->assignRole($role);
    $this->actingAs($user->refresh(), 'admin');

    $r = TestReorderableUserModel::create(['name' => 'A', 'position' => 0]);
    $this->postJson('/api/admin/test-reorderables/reorder', [
        'items' => [['id' => $r->id, 'position' => 5]],
    ])->assertStatus(403);
});
