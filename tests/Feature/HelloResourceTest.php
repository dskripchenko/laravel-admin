<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * End-to-end smoke-test «Hello, Resource».
 *
 * Регистрирует TestUserResource в ResourceRegistry, поднимает users-таблицу,
 * и проходит полный CRUD через JSON-API:
 *   POST /api/admin/test-users/create
 *   POST /api/admin/test-users/search
 *   GET  /api/admin/test-users/read?id=N
 *   POST /api/admin/test-users/update
 *   POST /api/admin/test-users/delete
 *
 * Это закрывает Phase P1 — backbone доказал, что от регистрации Resource'а
 * до работы CRUD-эндпоинтов через laravel-api всё цепляется автоматически.
 */
beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestUserResource::class);

    AdminApi::clearCache();

    // Поднимаем таблицу для TestResourceUserModel.
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    // Аутентификация. Даём super-роль с `*`, чтобы пройти AdminAccess
    // на всех Resource-actions (Permission gating проверяется отдельно).
    $admin = AdminUser::create([
        'name' => 'E2E Admin',
        'email' => 'e2e-admin-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'Super', 'slug' => 'e2e-super-'.uniqid(),
        'permissions' => ['*'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

afterEach(function (): void {
    Schema::dropIfExists('users');
});

it('serves /api/admin/test-users/meta', function (): void {
    $response = $this->getJson('/api/admin/test-users/meta');

    $response->assertOk();
    $payload = $response->json('payload');
    expect($payload['slug'])->toBe('test-users');
    expect($payload['fields'])->toHaveCount(3);
    expect($payload['columns'])->toHaveCount(3);
});

it('creates a record via /test-users/create', function (): void {
    $response = $this->postJson('/api/admin/test-users/create', [
        'name' => 'Иван Иванов',
        'email' => 'ivan@example.com',
        'password' => 'super-secret',
    ]);

    $response->assertStatus(201);
    $payload = $response->json('payload');
    expect($payload['record']['name'])->toBe('Иван Иванов');
    expect($payload['record']['email'])->toBe('ivan@example.com');
    expect($payload['redirect_url'])->toContain('/admin/resources/test-users/');

    expect(TestResourceUserModel::where('email', 'ivan@example.com')->exists())->toBeTrue();
});

it('rejects invalid create payload with 422 + messages', function (): void {
    $response = $this->postJson('/api/admin/test-users/create', [
        'name' => '',           // required
        'email' => 'not-email',  // we don't actually have email rule yet, but name is required
        'password' => 'secret',
    ]);

    $response->assertStatus(422);
    expect($response->json('success'))->toBeFalse();
    expect($response->json('payload.errorKey'))->toBe('validation');
});

it('reads a record via /test-users/read?id=...', function (): void {
    $record = TestResourceUserModel::create([
        'name' => 'Read Me',
        'email' => 'read@example.com',
        'password' => Hash::make('x'),
    ]);

    $response = $this->getJson('/api/admin/test-users/read?id='.$record->id);

    $response->assertOk();
    expect($response->json('payload.record.name'))->toBe('Read Me');
});

it('returns 404 on read when record does not exist', function (): void {
    $response = $this->getJson('/api/admin/test-users/read?id=999999');
    $response->assertStatus(404);
    expect($response->json('payload.errorKey'))->toBe('not_found');
});

it('lists records via /test-users/search with pagination', function (): void {
    foreach (['Alice', 'Bob', 'Carol'] as $name) {
        TestResourceUserModel::create([
            'name' => $name,
            'email' => strtolower($name).'@example.com',
            'password' => Hash::make('x'),
        ]);
    }

    $response = $this->postJson('/api/admin/test-users/search', [
        'page' => 1,
        'per_page' => 25,
    ]);

    $response->assertOk();
    expect($response->json('payload.data'))->toHaveCount(3);
    expect($response->json('payload.meta.total'))->toBe(3);
    expect($response->json('payload.meta.page'))->toBe(1);
});

it('search applies free-text q across searchable columns', function (): void {
    TestResourceUserModel::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'x']);
    TestResourceUserModel::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'x']);
    TestResourceUserModel::create(['name' => 'Charlie', 'email' => 'charlie@example.com', 'password' => 'x']);

    $response = $this->postJson('/api/admin/test-users/search', ['q' => 'ali']);

    $response->assertOk();
    expect($response->json('payload.data'))->toHaveCount(1);
    expect($response->json('payload.data.0.name'))->toBe('Alice');
});

it('search applies filters', function (): void {
    TestResourceUserModel::create(['name' => 'Active', 'email' => 'active@example.com', 'password' => 'x']);
    TestResourceUserModel::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'x']);

    $response = $this->postJson('/api/admin/test-users/search', [
        'filters' => ['email' => 'active'],
    ]);

    $response->assertOk();
    expect($response->json('payload.data'))->toHaveCount(1);
    expect($response->json('payload.data.0.email'))->toBe('active@example.com');
});

it('updates a record via /test-users/update', function (): void {
    $record = TestResourceUserModel::create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'password' => Hash::make('x'),
    ]);

    $response = $this->postJson('/api/admin/test-users/update', [
        'id' => $record->id,
        'name' => 'New Name',
        'email' => 'old@example.com',
    ]);

    $response->assertOk();
    expect($response->json('payload.record.name'))->toBe('New Name');
    expect($record->fresh()->name)->toBe('New Name');
});

it('deletes a record via /test-users/delete', function (): void {
    $record = TestResourceUserModel::create([
        'name' => 'Delete Me',
        'email' => 'delete@example.com',
        'password' => Hash::make('x'),
    ]);

    $response = $this->postJson('/api/admin/test-users/delete', ['id' => $record->id]);

    $response->assertOk();
    expect(TestResourceUserModel::find($record->id))->toBeNull();
});

it('manifest includes the registered resource', function (): void {
    $response = $this->getJson('/api/admin/system/manifest');
    $response->assertOk();

    $resources = $response->json('payload.resources');
    expect($resources)->toHaveCount(1);
    expect($resources[0]['slug'])->toBe('test-users');
});
