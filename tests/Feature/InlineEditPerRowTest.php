<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resource::editableForRow + ResourceController::search per-row override.
 */
beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestPerRowEditableResource::class);
    AdminApi::clearCache();

    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password');
        $t->timestamps();
    });

    $admin = AdminUser::create([
        'name' => 'PRE Admin',
        'email' => 'pre-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'Super', 'slug' => 'pre-super-'.uniqid(),
        'permissions' => ['*'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('search injects _editable map for rows where editableForRow returns false', function (): void {
    TestResourceUserModel::create([
        'name' => 'Alice', 'email' => 'alice@e.com', 'password' => 'x',
    ]);
    $bob = TestResourceUserModel::create([
        'name' => 'Bob (locked)', 'email' => 'bob@e.com', 'password' => 'x',
    ]);

    $response = $this->postJson('/api/admin/test-per-row-editables/search');

    $response->assertOk();
    $rows = $response->json('payload.data');
    $alice = collect($rows)->firstWhere('email', 'alice@e.com');
    $bobRow = collect($rows)->firstWhere('email', 'bob@e.com');

    expect($alice)->not->toHaveKey('_editable');
    expect($bobRow['_editable'])->toBe(['name' => false]);
});

it('non-editable resources do not get _editable injected', function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestUserResource::class);
    AdminApi::clearCache();

    TestResourceUserModel::create([
        'name' => 'Plain', 'email' => 'plain@e.com', 'password' => 'x',
    ]);

    $response = $this->postJson('/api/admin/test-users/search');
    $response->assertOk();
    $rows = $response->json('payload.data');
    expect($rows[0])->not->toHaveKey('_editable');
});
