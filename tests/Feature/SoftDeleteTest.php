<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Filter\TrashedFilter;
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
    $rr->add(TestSoftDeleteResource::class);
    AdminApi::clearCache();

    Schema::create('soft_users', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->softDeletes();
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

it('Resource::supportsSoftDeletes detects via trait_uses', function (): void {
    expect(TestSoftDeleteResource::supportsSoftDeletes())->toBeTrue();
    expect(TestUserResource::supportsSoftDeletes())->toBeFalse();
});

it('meta.features.softDeletes reflects detection', function (): void {
    $resource = new TestSoftDeleteResource;
    expect($resource->meta()['features']['softDeletes'])->toBeTrue();
    expect((new TestUserResource)->meta()['features']['softDeletes'])->toBeFalse();
});

it('meta.permissions includes restore/force_delete/replicate/reorder keys', function (): void {
    $perms = (new TestSoftDeleteResource)->meta()['permissions'];
    expect($perms)->toHaveKeys(['restore', 'force_delete', 'replicate', 'reorder']);
    expect($perms['restore'])->toBe('admin.test-soft-deletes.restore');
    expect($perms['force_delete'])->toBe('admin.test-soft-deletes.force-delete');
});

it('delete on SoftDeletes model performs soft delete', function (): void {
    $r = TestSoftDeleteUserModel::create(['name' => 'A']);

    $this->postJson('/api/admin/test-soft-deletes/delete', ['id' => $r->id])
        ->assertOk();

    expect(TestSoftDeleteUserModel::find($r->id))->toBeNull();
    expect(TestSoftDeleteUserModel::withTrashed()->find($r->id))->not->toBeNull();
});

it('restore action restores soft-deleted record', function (): void {
    $r = TestSoftDeleteUserModel::create(['name' => 'A']);
    $r->delete();

    $response = $this->postJson('/api/admin/test-soft-deletes/restore', ['id' => $r->id]);
    $response->assertOk();
    expect(TestSoftDeleteUserModel::find($r->id))->not->toBeNull();
});

it('forceDelete action removes record permanently', function (): void {
    $r = TestSoftDeleteUserModel::create(['name' => 'A']);
    $r->delete();

    $this->postJson('/api/admin/test-soft-deletes/forceDelete', ['id' => $r->id])
        ->assertOk();

    expect(TestSoftDeleteUserModel::withTrashed()->find($r->id))->toBeNull();
});

it('restore returns 422 for resource without SoftDeletes', function (): void {
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

    $r = TestResourceUserModel::create(['name' => 'X']);
    $response = $this->postJson('/api/admin/test-users/restore', ['id' => $r->id]);
    $response->assertStatus(422);
    expect($response->json('payload.message'))->toContain('soft-delete');
});

it('restore returns 404 when record not found (even with withTrashed)', function (): void {
    $response = $this->postJson('/api/admin/test-soft-deletes/restore', ['id' => 99999]);
    $response->assertStatus(404);
});

it('TrashedFilter only returns trashed records', function (): void {
    $a = TestSoftDeleteUserModel::create(['name' => 'live']);
    $b = TestSoftDeleteUserModel::create(['name' => 'dead']);
    $b->delete();

    $filter = TrashedFilter::for();
    $query = $filter->apply(TestSoftDeleteUserModel::query(), 'only');
    expect($query->pluck('name')->all())->toBe(['dead']);
});

it('TrashedFilter::with returns trashed + alive', function (): void {
    TestSoftDeleteUserModel::create(['name' => 'live']);
    $b = TestSoftDeleteUserModel::create(['name' => 'dead']);
    $b->delete();

    $query = TrashedFilter::for()->apply(TestSoftDeleteUserModel::query(), 'with');
    expect($query->count())->toBe(2);
});

it('TrashedFilter without/empty returns alive only (default)', function (): void {
    TestSoftDeleteUserModel::create(['name' => 'live']);
    $b = TestSoftDeleteUserModel::create(['name' => 'dead']);
    $b->delete();

    $query = TrashedFilter::for()->apply(TestSoftDeleteUserModel::query(), '');
    expect($query->count())->toBe(1);
});

it('admin without restore permission gets 403', function (): void {
    $user = AdminUser::create([
        'name' => 'NP',
        'email' => 'np-'.uniqid().'@example.com',
        'password' => 'p',
    ]);
    // Только view permission.
    $role = Role::create([
        'name' => 'V', 'slug' => 'v-'.uniqid(),
        'permissions' => ['admin.test-soft-deletes.view'],
    ]);
    $user->assignRole($role);
    $this->actingAs($user->refresh(), 'admin');

    $r = TestSoftDeleteUserModel::create(['name' => 'X']);
    $r->delete();

    $this->postJson('/api/admin/test-soft-deletes/restore', ['id' => $r->id])
        ->assertStatus(403);
});
