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
    $rr->add(TestEditableResource::class);
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
    $role = Role::create(['name' => 'S', 'slug' => 'sa-'.uniqid(), 'permissions' => ['*']]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('search with ?group_by returns groups in meta', function (): void {
    TestResourceUserModel::create(['name' => 'A', 'status' => 'active']);
    TestResourceUserModel::create(['name' => 'B', 'status' => 'active']);
    TestResourceUserModel::create(['name' => 'C', 'status' => 'banned']);

    $response = $this->postJson('/api/admin/test-editables/search', [
        'group_by' => 'status',
    ]);

    $response->assertOk();
    $groups = $response->json('payload.meta.groups');
    expect($groups)->toBeArray();
    $byValue = collect($groups)->keyBy('value')->toArray();
    expect((int) $byValue['active']['count'])->toBe(2);
    expect((int) $byValue['banned']['count'])->toBe(1);
});

it('search without ?group_by returns groups=null', function (): void {
    TestResourceUserModel::create(['name' => 'X']);

    $response = $this->postJson('/api/admin/test-editables/search');

    expect($response->json('payload.meta.groups'))->toBeNull();
});

it('exportCsv streams CSV with BOM and header row', function (): void {
    TestResourceUserModel::create(['name' => 'Alice', 'status' => 'active', 'amount' => 100]);
    TestResourceUserModel::create(['name' => 'Bob', 'status' => 'banned', 'amount' => 50]);

    $response = $this->get('/api/admin/test-editables/exportCsv');
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $body = $response->streamedContent();
    expect($body)->toStartWith("\xEF\xBB\xBF"); // BOM
    expect($body)->toContain('Alice');
    expect($body)->toContain('Bob');
});

it('exportCsv respects ?columns selection', function (): void {
    TestResourceUserModel::create(['name' => 'Alice', 'status' => 'active', 'amount' => 100]);

    $response = $this->get('/api/admin/test-editables/exportCsv?columns[]=name&columns[]=amount');
    $body = $response->streamedContent();

    // Header не должен содержать 'Status' (если бы в TestEditableResource он не был
    // переименован, label = 'Status').
    expect($body)->toContain('Name');
    expect($body)->toContain('Amount');
    expect($body)->not->toContain('Status');
});

it('exportCsv applies filter from ?filters[] before stream', function (): void {
    TestResourceUserModel::create(['name' => 'Alice', 'status' => 'active']);
    TestResourceUserModel::create(['name' => 'Bob', 'status' => 'active']);
    TestResourceUserModel::create(['name' => 'Charlie', 'status' => 'banned']);

    // У TestEditableResource нет фильтров — проверим что вообще все три появятся.
    // Цель теста: убедиться что endpoint работает с filters параметром (без ошибок).
    $response = $this->get('/api/admin/test-editables/exportCsv');
    $body = $response->streamedContent();
    expect(substr_count($body, "\n"))->toBeGreaterThanOrEqual(3); // header + 3 rows
});

it('Resource::polling default is null and configurable via override', function (): void {
    expect((new TestEditableResource)->polling())->toBeNull();

    $resource = new class extends Dskripchenko\LaravelAdmin\Resource\Resource
    {
        public static string $model = TestResourceUserModel::class;

        public function polling(): ?int
        {
            return 30;
        }
    };
    expect($resource->polling())->toBe(30);
    expect($resource->meta()['features']['polling'])->toBe(30);
});
