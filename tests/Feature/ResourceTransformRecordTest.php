<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    app(ResourceRegistry::class)->clear();
    app(ResourceRegistry::class)->add(TestTransformResource::class);
    AdminApi::clearCache();

    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('name')->nullable();
        $t->string('email')->nullable();
        $t->string('password')->nullable();
        $t->string('status')->nullable();
        $t->timestamps();
    });

    $admin = AdminUser::create(['name' => 'A', 'email' => 'a-'.uniqid().'@x.io', 'password' => 'secret']);
    $admin->assignRole(Role::create(['name' => 'S', 'slug' => 's-'.uniqid(), 'permissions' => ['*']]));
    $this->actingAs($admin->refresh(), 'admin');
});

it('read/update отдают record через transformRecord (virtual-поля)', function (): void {
    $row = TestResourceUserModel::create(['name' => 'alice']);

    $read = $this->getJson('/api/admin/transforms/read?id='.$row->id)->assertOk();
    expect($read->json('payload.record.virtual_upper'))->toBe('ALICE');
    expect($read->json('payload.state.virtual_upper'))->toBe('ALICE');

    $upd = $this->postJson('/api/admin/transforms/update', ['id' => $row->id, 'name' => 'bob'])->assertOk();
    expect($upd->json('payload.record.virtual_upper'))->toBe('BOB');
});
