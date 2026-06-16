<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * treeScreen и tree endpoint'ы для иерархических Resource'ов.
 */
beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestTreeNodeResource::class);
    AdminApi::clearCache();

    Schema::create('tree_nodes', function (Blueprint $t): void {
        $t->id();
        $t->foreignId('parent_id')->nullable();
        $t->string('name');
        $t->timestamps();
    });

    $admin = AdminUser::create([
        'name' => 'Tree Admin',
        'email' => 'tree-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'Super', 'slug' => 'tree-super-'.uniqid(),
        'permissions' => ['*'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('treeScreen action returns generated.tree payload', function (): void {
    $response = $this->getJson('/api/admin/test-tree-nodes/treeScreen');

    $response->assertOk();
    expect($response->json('payload.type'))->toBe('generated.tree');
    expect($response->json('payload.resource_slug'))->toBe('test-tree-nodes');
    expect($response->json('payload.state.view_mode'))->toBe('tree');
    expect($response->json('payload.state.parent_key'))->toBe('parent_id');
    expect($response->json('payload.state.label_column'))->toBe('name');
    expect($response->json('payload.layout.0.props.component'))->toBe('admin.tree');
});

it('tree action returns nested TreeNode[] payload', function (): void {
    $root = TestTreeNodeModel::create(['name' => 'Root']);
    $child = TestTreeNodeModel::create(['name' => 'Child', 'parent_id' => $root->id]);
    $grandchild = TestTreeNodeModel::create(['name' => 'Grandchild', 'parent_id' => $child->id]);
    TestTreeNodeModel::create(['name' => 'OtherRoot']);

    $response = $this->postJson('/api/admin/test-tree-nodes/tree');

    $response->assertOk();
    expect($response->json('payload.meta.total'))->toBe(4);
    expect($response->json('payload.meta.max_depth'))->toBe(2);
    expect($response->json('payload.meta.parent_key'))->toBe('parent_id');

    $roots = $response->json('payload.data');
    expect($roots)->toHaveCount(2);
    expect($roots[0]['label'])->toBe('OtherRoot'); // ordered alphabetically by name
    expect($roots[1]['label'])->toBe('Root');
    expect($roots[1]['children'][0]['label'])->toBe('Child');
    expect($roots[1]['children'][0]['children'][0]['label'])->toBe('Grandchild');
});

it('tree action narrows by ?q= search but keeps roots without ancestors', function (): void {
    $root = TestTreeNodeModel::create(['name' => 'Root']);
    TestTreeNodeModel::create(['name' => 'Match-A', 'parent_id' => $root->id]);
    TestTreeNodeModel::create(['name' => 'Match-B']);
    TestTreeNodeModel::create(['name' => 'Skip']);

    $response = $this->postJson('/api/admin/test-tree-nodes/tree', ['q' => 'Match']);

    $response->assertOk();
    $roots = $response->json('payload.data');
    // Match-A's parent (Root) was filtered out, so Match-A bubbles up to root.
    $labels = array_column($roots, 'label');
    expect($labels)->toContain('Match-A');
    expect($labels)->toContain('Match-B');
    expect($labels)->not->toContain('Skip');
});

it('tree action returns 409 for non-hierarchical resources', function (): void {
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

    $response = $this->postJson('/api/admin/test-users/tree');

    $response->assertStatus(409);
    expect($response->json('payload.errorKey'))->toBe('not_hierarchical');
});

it('tree action returns empty data for table without rows', function (): void {
    $response = $this->postJson('/api/admin/test-tree-nodes/tree');

    $response->assertOk();
    expect($response->json('payload.data'))->toBe([]);
    expect($response->json('payload.meta.total'))->toBe(0);
    expect($response->json('payload.meta.max_depth'))->toBe(0);
});
