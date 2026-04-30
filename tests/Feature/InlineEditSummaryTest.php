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
    $role = Role::create([
        'name' => 'Super', 'slug' => 'super-'.uniqid(),
        'permissions' => ['*'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('inlineUpdate updates a single editable column', function (): void {
    $r = TestResourceUserModel::create(['name' => 'Old', 'status' => 'pending']);

    $response = $this->postJson('/api/admin/test-editables/inlineUpdate', [
        'id' => $r->id,
        'column' => 'name',
        'value' => 'New Name',
    ]);

    $response->assertOk();
    expect($response->json('payload.value'))->toBe('New Name');
    expect($r->fresh()->name)->toBe('New Name');
});

it('inlineUpdate rejects non-editable column with 422', function (): void {
    $r = TestResourceUserModel::create(['name' => 'X']);

    $response = $this->postJson('/api/admin/test-editables/inlineUpdate', [
        'id' => $r->id,
        'column' => 'id',
        'value' => 999,
    ]);

    $response->assertStatus(422);
    expect($response->json('payload.message'))->toContain('not editable');
});

it('inlineUpdate validates against editable.validation rules', function (): void {
    $r = TestResourceUserModel::create(['name' => 'A']);

    // 'name' rules: required, string, max:255
    $longName = str_repeat('x', 300);
    $response = $this->postJson('/api/admin/test-editables/inlineUpdate', [
        'id' => $r->id,
        'column' => 'name',
        'value' => $longName,
    ]);

    $response->assertStatus(422);
});

it('inlineUpdate returns 404 if record not found', function (): void {
    $response = $this->postJson('/api/admin/test-editables/inlineUpdate', [
        'id' => 99999,
        'column' => 'name',
        'value' => 'Whatever',
    ]);

    $response->assertStatus(404);
});

it('summary action computes sum/avg/count/min/max for amount column', function (): void {
    TestResourceUserModel::create(['name' => 'A', 'amount' => 10]);
    TestResourceUserModel::create(['name' => 'B', 'amount' => 20]);
    TestResourceUserModel::create(['name' => 'C', 'amount' => 30]);

    $response = $this->postJson('/api/admin/test-editables/summary');

    $response->assertOk();
    $summary = $response->json('payload.summary');
    expect($summary)->toHaveKey('amount');
    expect((float) $summary['amount']['sum'])->toBe(60.0);
    expect((float) $summary['amount']['avg'])->toBe(20.0);
    expect((int) $summary['amount']['count'])->toBe(3);
    expect((int) $summary['amount']['min'])->toBe(10);
    expect((int) $summary['amount']['max'])->toBe(30);
});

it('summary skips columns without ->summary([...])', function (): void {
    TestResourceUserModel::create(['name' => 'A', 'amount' => 5]);

    $response = $this->postJson('/api/admin/test-editables/summary');

    $summary = $response->json('payload.summary');
    expect($summary)->not->toHaveKey('id');
    expect($summary)->not->toHaveKey('name');
    expect($summary)->toHaveKey('amount');
});
