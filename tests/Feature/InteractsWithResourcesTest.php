<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Testing\Concerns\ActsAsAdmin;
use Dskripchenko\LaravelAdmin\Testing\Concerns\InteractsWithAdminResources;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(ActsAsAdmin::class, InteractsWithAdminResources::class);

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

    $this->actingAsSuperAdmin();
});

it('getResourceMeta returns OK with fields/columns/permissions', function (): void {
    $response = $this->getResourceMeta('test-users');
    $response->assertOk();
    expect($response->json('payload.fields'))->toBeArray();
    expect($response->json('payload.columns'))->toBeArray();
    expect($response->json('payload.permissions'))->toBeArray();
});

it('postResourceCreate creates a record', function (): void {
    $response = $this->postResourceCreate('test-users', [
        'name' => 'Helper Test',
        'email' => 'helper@example.com',
        'password' => 'pass',
    ]);
    $response->assertStatus(201);
    expect(TestResourceUserModel::where('email', 'helper@example.com')->exists())->toBeTrue();
});

it('getResourceRead fetches by id', function (): void {
    $user = TestResourceUserModel::create([
        'name' => 'Read Me',
        'email' => 'r@example.com',
        'password' => 'p',
    ]);

    $response = $this->getResourceRead('test-users', $user->id);
    $response->assertOk();
    expect($response->json('payload.record.name'))->toBe('Read Me');
});

it('postResourceUpdate updates a record', function (): void {
    $user = TestResourceUserModel::create([
        'name' => 'Old', 'email' => 'u@example.com', 'password' => 'p',
    ]);

    $this->postResourceUpdate('test-users', $user->id, [
        'name' => 'New',
        'email' => 'u2@example.com',
    ])->assertOk();

    expect($user->fresh()->name)->toBe('New');
});

it('postResourceDelete removes a record', function (): void {
    $user = TestResourceUserModel::create([
        'name' => 'Del', 'email' => 'd@example.com', 'password' => 'p',
    ]);

    $this->postResourceDelete('test-users', $user->id)->assertOk();
    expect(TestResourceUserModel::find($user->id))->toBeNull();
});

it('postResourceSearch passes filters', function (): void {
    TestResourceUserModel::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'p']);
    TestResourceUserModel::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'p']);

    $response = $this->postResourceSearch('test-users', filters: ['email' => 'alice']);
    $response->assertOk();
    $rows = $response->json('payload.data');
    expect(count($rows))->toBe(1);
    expect($rows[0]['name'])->toBe('Alice');
});

it('assertResourceMetaOk asserts shape', function (): void {
    $this->assertResourceMetaOk('test-users');
});

it('assertResourceCount checks meta.total', function (): void {
    TestResourceUserModel::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'p']);
    TestResourceUserModel::create(['name' => 'B', 'email' => 'b@example.com', 'password' => 'p']);

    $this->assertResourceCount('test-users', 2);
});

it('postResourceAction routes to custom action', function (): void {
    $response = $this->postResourceAction('test-users', 'summary');
    $response->assertOk();
    expect($response->json('payload'))->toHaveKey('summary');
});

it('resourceUrl uses configured api_path', function (): void {
    config()->set('admin.api_path', 'api/admin');
    // Косвенно через фактический endpoint:
    $response = $this->getResourceMeta('test-users');
    $response->assertOk();
});
