<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Проверка state-actions для GeneratedScreens — listScreen/createScreen/editScreen.
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

    $admin = AdminUser::create([
        'name' => 'GS Admin',
        'email' => 'gs-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'Super', 'slug' => 'gs-super-'.uniqid(),
        'permissions' => ['*'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('listScreen action returns generated.list payload', function (): void {
    $response = $this->getJson('/api/admin/test-users/listScreen');

    $response->assertOk();
    expect($response->json('payload.type'))->toBe('generated.list');
    expect($response->json('payload.resource_slug'))->toBe('test-users');
    expect($response->json('payload.command_bar'))->not->toBeEmpty();
});

it('createScreen action returns generated.create payload', function (): void {
    $response = $this->getJson('/api/admin/test-users/createScreen');

    $response->assertOk();
    expect($response->json('payload.type'))->toBe('generated.create');
    expect($response->json('payload.state.record'))->toBeArray();
});

it('editScreen action loads record by id', function (): void {
    $record = TestResourceUserModel::create([
        'name' => 'Existing',
        'email' => 'ex@example.com',
        'password' => 'x',
    ]);

    $response = $this->getJson('/api/admin/test-users/editScreen?id='.$record->id);

    $response->assertOk();
    expect($response->json('payload.type'))->toBe('generated.edit');
    expect($response->json('payload.state.record.name'))->toBe('Existing');
});

it('editScreen 404 when record missing', function (): void {
    $response = $this->getJson('/api/admin/test-users/editScreen?id=99999');

    $response->assertStatus(404);
});

it('editScreen 422 when no id provided', function (): void {
    $response = $this->getJson('/api/admin/test-users/editScreen');

    $response->assertStatus(422);
});
