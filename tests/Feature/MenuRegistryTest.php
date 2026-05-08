<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Menu\MenuNode;
use Dskripchenko\LaravelAdmin\Menu\MenuRegistry;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;

beforeEach(function (): void {
    /** @var MenuRegistry $registry */
    $registry = app(MenuRegistry::class);
    $registry->clear();

    /** @var ScreenRegistry $screens */
    $screens = app(ScreenRegistry::class);
    $screens->clear();

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);
    $resources->clear();

    AdminApi::clearCache();

    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'S', 'slug' => 's-'.uniqid(),
        'permissions' => ['*'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('MenuNode::make creates a manual node', function (): void {
    $node = MenuNode::make('shop', 'Магазин')->icon('store')->url('/shop');
    $arr = $node->toArray(app(ResourceRegistry::class), app(ScreenRegistry::class));
    expect($arr['key'])->toBe('shop');
    expect($arr['label'])->toBe('Магазин');
    expect($arr['icon'])->toBe('store');
    expect($arr['url'])->toBe('/shop');
    expect($arr['children'])->toBe([]);
});

it('MenuNode::screen auto-resolves label/url/permissions from ScreenRegistry', function (): void {
    /** @var ScreenRegistry $screens */
    $screens = app(ScreenRegistry::class);
    $screens->add(TestContactScreen::class);

    $node = MenuNode::screen('test-contact');
    $arr = $node->toArray(app(ResourceRegistry::class), $screens);

    expect($arr['key'])->toBe('screen.test-contact');
    expect($arr['label'])->toBe('Contact');
    expect($arr['url'])->toBe('/screens/test-contact');
    expect($arr['routeName'])->toBe('admin.screen.test-contact');
});

it('MenuNode::children supports nested hierarchy serialization', function (): void {
    $tree = MenuNode::make('tools', 'Инструменты')->children([
        MenuNode::make('lvl1', 'Уровень 1')->children([
            MenuNode::make('lvl2', 'Уровень 2')->url('/lvl2'),
        ]),
    ]);

    $arr = $tree->toArray(app(ResourceRegistry::class), app(ScreenRegistry::class));
    expect($arr['children'])->toHaveCount(1);
    expect($arr['children'][0]['key'])->toBe('lvl1');
    expect($arr['children'][0]['children'][0]['key'])->toBe('lvl2');
    expect($arr['children'][0]['children'][0]['url'])->toBe('/lvl2');
});

it('MenuRegistry::under inserts children into parent by key (recursive search)', function (): void {
    /** @var MenuRegistry $registry */
    $registry = app(MenuRegistry::class);
    $registry->add(
        MenuNode::make('shop', 'Магазин')->children([
            MenuNode::make('catalog', 'Каталог'),
        ]),
    );
    // catalog — глубоко вложенный — найдётся через recursive search.
    $registry->under('catalog', [
        MenuNode::make('products', 'Товары')->url('/r/products'),
    ]);

    $roots = $registry->roots();
    $shopArr = $roots[0]->toArray(app(ResourceRegistry::class), app(ScreenRegistry::class));
    $catalog = $shopArr['children'][0];
    expect($catalog['children'])->toHaveCount(1);
    expect($catalog['children'][0]['key'])->toBe('products');
});

it('MenuRegistry::under creates stub-parent if not found', function (): void {
    /** @var MenuRegistry $registry */
    $registry = app(MenuRegistry::class);
    $registry->under('fresh', [MenuNode::make('child', 'Child')]);

    expect($registry->roots())->toHaveCount(1);
    expect($registry->roots()[0]->key())->toBe('fresh');
});

it('GET /system/menu returns custom tree from MenuRegistry', function (): void {
    /** @var ScreenRegistry $screens */
    $screens = app(ScreenRegistry::class);
    $screens->add(TestContactScreen::class);
    AdminApi::clearCache();

    /** @var MenuRegistry $registry */
    $registry = app(MenuRegistry::class);
    $registry->withAuto(false);
    $registry->add(
        MenuNode::make('tools', 'Инструменты')->children([
            MenuNode::screen('test-contact'),
            MenuNode::make('nested', 'Вложенное')->children([
                MenuNode::make('deep', 'Глубоко')->url('/deep'),
            ]),
        ]),
    );

    $response = $this->getJson('/api/admin/system/menu');
    $response->assertOk();
    $items = $response->json('payload.items');
    expect($items)->toHaveCount(1);
    expect($items[0]['key'])->toBe('tools');
    expect($items[0]['children'])->toHaveCount(2);
    expect($items[0]['children'][0]['key'])->toBe('screen.test-contact');
    expect($items[0]['children'][0]['label'])->toBe('Contact');
    expect($items[0]['children'][1]['children'][0]['key'])->toBe('deep');
});

it('GET /system/menu auto-fills missing screens by default', function (): void {
    /** @var ScreenRegistry $screens */
    $screens = app(ScreenRegistry::class);
    $screens->add(TestContactScreen::class);
    AdminApi::clearCache();

    /** @var MenuRegistry $registry */
    $registry = app(MenuRegistry::class);
    $registry->add(MenuNode::make('shop', 'Магазин'));
    // Auto-fill ON: TestContactScreen должен попасть в результат как
    // отдельный root-item, потому что в кастомном дереве его нет.

    $response = $this->getJson('/api/admin/system/menu');
    $response->assertOk();
    $items = $response->json('payload.items');
    $keys = array_column($items, 'key');
    expect($keys)->toContain('shop');
    expect($keys)->toContain('screen.test-contact');
});

it('GET /system/menu skips auto-fill when withAuto(false)', function (): void {
    /** @var ScreenRegistry $screens */
    $screens = app(ScreenRegistry::class);
    $screens->add(TestContactScreen::class);
    AdminApi::clearCache();

    /** @var MenuRegistry $registry */
    $registry = app(MenuRegistry::class);
    $registry->withAuto(false);
    $registry->add(MenuNode::make('shop', 'Магазин'));

    $response = $this->getJson('/api/admin/system/menu');
    $response->assertOk();
    $items = $response->json('payload.items');
    expect($items)->toHaveCount(1);
    expect($items[0]['key'])->toBe('shop');
});

it('GET /system/menu fallbacks to auto-only when registry is empty', function (): void {
    /** @var ScreenRegistry $screens */
    $screens = app(ScreenRegistry::class);
    $screens->add(TestContactScreen::class);
    AdminApi::clearCache();

    $response = $this->getJson('/api/admin/system/menu');
    $response->assertOk();
    $items = $response->json('payload.items');
    expect($items)->toHaveCount(1);
    expect($items[0]['key'])->toBe('screen.test-contact');
});
