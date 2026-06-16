<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Edit-screen с Tabs + ResourceTable layout (генерируется host-Resource'ом
 * через `formLayout('update')`).
 */
beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestEmbedDictionaryResource::class);
    $rr->add(TestEmbedDictionaryItemResource::class);
    AdminApi::clearCache();

    Schema::create('embed_dicts', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('embed_dict_items', function (Blueprint $t): void {
        $t->id();
        $t->foreignId('dictionary_id');
        $t->string('label');
        $t->integer('sort_order')->default(0);
        $t->timestamps();
    });

    $admin = AdminUser::create([
        'name' => 'Embed Admin',
        'email' => 'embed-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'Super', 'slug' => 'embed-super-'.uniqid(),
        'permissions' => ['*'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('editScreen returns Tabs layout with embedded admin.resource-table tab', function (): void {
    $dict = TestEmbedDictionaryModel::create(['name' => 'Order Statuses']);

    $response = $this->getJson('/api/admin/test-embed-dictionaries/editScreen?id='.$dict->id);

    $response->assertOk();
    $layout = $response->json('payload.layout');
    // layout[0] = Rows wrapping the custom formLayout array.
    expect($layout[0]['type'])->toBe('rows');
    $rowsChildren = $layout[0]['items'];
    expect($rowsChildren[0]['type'])->toBe('tabs');

    $tabs = $rowsChildren[0]['items'];
    expect($tabs)->toHaveCount(2);
    expect($tabs[0]['label'])->toBe('Основные');
    expect($tabs[1]['label'])->toBe('Элементы');

    // Внутри второй вкладки — наш ResourceTable layout-node.
    $itemsTab = $tabs[1]['items'];
    expect($itemsTab[0]['type'])->toBe('admin.resource-table');
    expect($itemsTab[0]['resource'])->toBe('test-embed-dictionary-items');
    expect($itemsTab[0]['foreign_key'])->toBe('dictionary_id');
    expect($itemsTab[0]['features']['create'])->toBeTrue();
    expect($itemsTab[0]['features']['delete'])->toBeTrue();
});
