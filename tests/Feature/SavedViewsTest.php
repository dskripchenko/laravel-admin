<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Table\SavedView;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

    $admin = AdminUser::create([
        'name' => 'SV Admin',
        'email' => 'sv-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'V', 'slug' => 'v-'.uniqid(),
        'permissions' => ['admin.test-users.view'],
    ]);
    $admin->assignRole($role);
    $this->admin = $admin->refresh();
    $this->actingAs($this->admin, 'admin');
});

it('list endpoint returns empty initially', function (): void {
    $response = $this->getJson('/api/admin/test-users_views/list');
    $response->assertOk();
    expect($response->json('payload.data'))->toBe([]);
});

it('create stores SavedView with current user as owner', function (): void {
    $response = $this->postJson('/api/admin/test-users_views/create', [
        'name' => 'My active users',
        'state' => [
            'filters' => ['email' => 'gmail.com'],
            'order' => [['column' => 'name', 'direction' => 'asc']],
            'columns' => ['name', 'email'],
            'per_page' => 50,
        ],
        'is_default' => false,
    ]);

    $response->assertOk();
    expect($response->json('payload.view.name'))->toBe('My active users');
    expect($response->json('payload.view.owned'))->toBeTrue();

    $row = SavedView::first();
    expect($row->resource_slug)->toBe('test-users');
    expect((int) $row->owner_id)->toBe((int) $this->admin->id);
    expect($row->state['per_page'])->toBe(50);
});

it('list returns own + global views', function (): void {
    SavedView::create([
        'resource_slug' => 'test-users',
        'name' => 'Global',
        'state' => ['per_page' => 25],
        'owner_type' => null,
        'owner_id' => null,
    ]);
    SavedView::create([
        'resource_slug' => 'test-users',
        'name' => 'Own',
        'state' => ['per_page' => 10],
        'owner_type' => $this->admin->getMorphClass(),
        'owner_id' => $this->admin->id,
    ]);
    // Чужой view
    $other = AdminUser::create([
        'name' => 'Other',
        'email' => 'other-'.uniqid().'@example.com',
        'password' => 'x',
    ]);
    SavedView::create([
        'resource_slug' => 'test-users',
        'name' => 'OtherOwn',
        'state' => [],
        'owner_type' => $other->getMorphClass(),
        'owner_id' => $other->id,
    ]);

    $response = $this->getJson('/api/admin/test-users_views/list');
    $names = collect($response->json('payload.data'))->pluck('name')->all();
    expect($names)->toContain('Global', 'Own');
    expect($names)->not->toContain('OtherOwn');
});

it('update modifies own view', function (): void {
    $view = SavedView::create([
        'resource_slug' => 'test-users',
        'name' => 'Original',
        'state' => ['per_page' => 10],
        'owner_type' => $this->admin->getMorphClass(),
        'owner_id' => $this->admin->id,
    ]);

    $response = $this->postJson('/api/admin/test-users_views/update', [
        'id' => $view->id,
        'name' => 'Renamed',
        'state' => ['per_page' => 100],
    ]);

    $response->assertOk();
    $view->refresh();
    expect($view->name)->toBe('Renamed');
    expect($view->state['per_page'])->toBe(100);
});

it('update of someone elses view returns 403', function (): void {
    $other = AdminUser::create([
        'name' => 'Other',
        'email' => 'oo-'.uniqid().'@example.com',
        'password' => 'x',
    ]);
    $view = SavedView::create([
        'resource_slug' => 'test-users',
        'name' => 'Foreign',
        'state' => [],
        'owner_type' => $other->getMorphClass(),
        'owner_id' => $other->id,
    ]);

    $response = $this->postJson('/api/admin/test-users_views/update', [
        'id' => $view->id,
        'name' => 'Hacked',
    ]);

    $response->assertStatus(403);
});

it('delete removes own view', function (): void {
    $view = SavedView::create([
        'resource_slug' => 'test-users',
        'name' => 'ToDelete',
        'state' => [],
        'owner_type' => $this->admin->getMorphClass(),
        'owner_id' => $this->admin->id,
    ]);

    $this->postJson('/api/admin/test-users_views/delete', ['id' => $view->id])
        ->assertOk();
    expect(SavedView::find($view->id))->toBeNull();
});

it('delete of foreign view returns 403', function (): void {
    $other = AdminUser::create([
        'name' => 'Other',
        'email' => 'oo2-'.uniqid().'@example.com',
        'password' => 'x',
    ]);
    $view = SavedView::create([
        'resource_slug' => 'test-users',
        'name' => 'NotMine',
        'state' => [],
        'owner_type' => $other->getMorphClass(),
        'owner_id' => $other->id,
    ]);

    $this->postJson('/api/admin/test-users_views/delete', ['id' => $view->id])
        ->assertStatus(403);
});

it('admin without view permission gets 403 for SavedViews', function (): void {
    $no = AdminUser::create([
        'name' => 'NP',
        'email' => 'np-'.uniqid().'@example.com',
        'password' => 'x',
    ]);
    $this->actingAs($no, 'admin');

    $this->getJson('/api/admin/test-users_views/list')->assertStatus(403);
});
