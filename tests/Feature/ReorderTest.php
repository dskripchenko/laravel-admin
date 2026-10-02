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

it('does not register reorder when resource is not reorderable', function (): void {
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
    // The action is not registered at all: the route is absent rather than answering with an error.
    $response->assertStatus(404);
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

it('accepts exactly the body the panel sends: ids in their new order', function (): void {
    // The SPA's own request, as resources/ts/components/resource/ResourceIndexPage.contract.test.ts asserts it.
    $body = json_decode((string) file_get_contents(__DIR__.'/../../resources/ts/__fixtures__/reorder-request.json'), true);

    $a = TestReorderableUserModel::create(['name' => 'A', 'position' => 0]);
    $b = TestReorderableUserModel::create(['name' => 'B', 'position' => 1]);
    $c = TestReorderableUserModel::create(['name' => 'C', 'position' => 2]);
    expect([$a->id, $b->id, $c->id])->toBe([1, 2, 3]);

    $response = $this->postJson('/api/admin/test-reorderables/reorder', $body);

    $response->assertOk();
    expect($response->json('payload.count'))->toBe(3);
    expect($response->json('payload.positions'))->toBe(['3' => 0, '1' => 1, '2' => 2]);
    expect($c->fresh()->position)->toBe(0);
    expect($a->fresh()->position)->toBe(1);
    expect($b->fresh()->position)->toBe(2);
});

it('reorders a page of a paginated list within its own slots', function (): void {
    // Positions with gaps; the second page holds 30, 40, 50.
    $rows = [];
    foreach ([0, 10, 20, 30, 40, 50] as $i => $position) {
        $rows[$i] = TestReorderableUserModel::create(['name' => "R{$i}", 'position' => $position]);
    }

    $this->postJson('/api/admin/test-reorderables/reorder', [
        'ids' => [$rows[5]->id, $rows[3]->id, $rows[4]->id],
        'offset' => 3,
    ])->assertOk();

    expect($rows[5]->fresh()->position)->toBe(30);
    expect($rows[3]->fresh()->position)->toBe(40);
    expect($rows[4]->fresh()->position)->toBe(50);
    // The first page is untouched.
    expect($rows[0]->fresh()->position)->toBe(0);
    expect($rows[2]->fresh()->position)->toBe(20);
});

it('numbers rows from the offset when their positions repeat', function (): void {
    $a = TestReorderableUserModel::create(['name' => 'A', 'position' => 0]);
    $b = TestReorderableUserModel::create(['name' => 'B', 'position' => 0]);

    $this->postJson('/api/admin/test-reorderables/reorder', [
        'ids' => [$b->id, $a->id],
        'offset' => 25,
    ])->assertOk();

    expect($b->fresh()->position)->toBe(25);
    expect($a->fresh()->position)->toBe(26);
});

it('validates the ids form', function (): void {
    $this->postJson('/api/admin/test-reorderables/reorder', ['ids' => []])->assertStatus(422);
    $this->postJson('/api/admin/test-reorderables/reorder', ['ids' => [1, 1]])->assertStatus(422);
    $this->postJson('/api/admin/test-reorderables/reorder', ['ids' => [1], 'offset' => -1])->assertStatus(422);
});
