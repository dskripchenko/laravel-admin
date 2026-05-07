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
    $rr->add(TestActionResource::class);
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

    $admin = AdminUser::create([
        'name' => 'Admin',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'Super', 'slug' => 'super-'.uniqid(),
        'permissions' => ['*'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('action endpoint applies BulkAction method to selected ids', function (): void {
    $a = TestResourceUserModel::create(['name' => 'A', 'status' => 'draft']);
    $b = TestResourceUserModel::create(['name' => 'B', 'status' => 'draft']);
    $c = TestResourceUserModel::create(['name' => 'C', 'status' => 'draft']);

    $response = $this->postJson('/api/admin/test-actions/action', [
        'key' => 'archive',
        'ids' => [$a->id, $b->id],
    ]);

    $response->assertOk();
    expect($response->json('payload.affected'))->toBe(2);
    expect($a->fresh()->status)->toBe('archived');
    expect($b->fresh()->status)->toBe('archived');
    expect($c->fresh()->status)->toBe('draft');
});

it('action endpoint returns 404 for unknown action key', function (): void {
    $a = TestResourceUserModel::create(['name' => 'A']);

    $response = $this->postJson('/api/admin/test-actions/action', [
        'key' => 'nonexistent',
        'ids' => [$a->id],
    ]);

    $response->assertStatus(404);
    expect($response->json('payload.errorKey'))->toBe('unknown_action');
});

it('action endpoint validates ids is non-empty array', function (): void {
    $response = $this->postJson('/api/admin/test-actions/action', [
        'key' => 'archive',
        'ids' => [],
    ]);

    $response->assertStatus(422);
});

it('action endpoint resolves second declared BulkAction independently', function (): void {
    $a = TestResourceUserModel::create(['name' => 'A', 'status' => 'draft']);

    $response = $this->postJson('/api/admin/test-actions/action', [
        'key' => 'publish',
        'ids' => [$a->id],
    ]);

    $response->assertOk();
    expect($a->fresh()->status)->toBe('published');
});
