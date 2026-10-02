<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Layout\ResourceIndex;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;

function resourceIndexActingAs(array $permissions): void
{
    $user = AdminUser::create([
        'name' => 'Viewer', 'email' => 'viewer-'.uniqid().'@example.com', 'password' => 'secret',
    ]);
    $user->assignRole(Role::create([
        'name' => 'Role', 'slug' => 'role-'.uniqid(), 'permissions' => $permissions,
    ]));
    test()->actingAs($user->refresh(), 'admin');
}

it('Layout::resourceIndex() serializes the admin.resource-index type with the resource slug', function (): void {
    $layout = Layout::resourceIndex(TestTreeNodeResource::class)->title('Live table');

    $arr = $layout->toArray();
    expect($layout)->toBeInstanceOf(ResourceIndex::class);
    expect($arr['type'])->toBe('admin.resource-index');
    expect($arr['kind'])->toBe('layout');
    expect($arr['resource'])->toBe('test-tree-nodes');
    expect($arr['title'])->toBe('Live table');
});

it('ResourceIndex rejects a class that is no resource', function (): void {
    ResourceIndex::for(stdClass::class);
})->throws(InvalidArgumentException::class);

it('ResourceIndex is shown only to a user who may view the resource', function (): void {
    $permission = TestTreeNodeResource::permission();

    resourceIndexActingAs([$permission.'.view']);
    expect(Layout::resourceIndex(TestTreeNodeResource::class)->isVisible())->toBeTrue();

    resourceIndexActingAs(['admin.something-else.view']);
    expect(Layout::resourceIndex(TestTreeNodeResource::class)->isVisible())->toBeFalse();
});
