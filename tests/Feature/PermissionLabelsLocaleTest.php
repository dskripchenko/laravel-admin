<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Permission\PermissionRegistry;
use Dskripchenko\LaravelAdmin\Theme\LocaleResolver;
use Illuminate\Support\Facades\Lang;

/**
 * Permission groups are registered once, at boot; their labels must follow
 * the locale of the request that serializes them, not the one the
 * application started with.
 */
beforeEach(function (): void {
    Lang::addLines([
        '*.Заказы' => 'Orders',
        '*.Заказы: просмотр' => 'Orders: view',
    ], 'en');

    /** @var PermissionRegistry $registry */
    $registry = app(PermissionRegistry::class);
    $registry->clear();
    // Registered while the application runs in ru, as a plugin's boot() would.
    app()->setLocale('ru');
    $registry->add(
        ItemPermission::group('Заказы')
            ->addPermission('admin.orders.view', 'Заказы: просмотр')
            ->addPermission('admin.orders.export', 'Выгрузка заказов'),
    );
});

afterEach(function (): void {
    app(PermissionRegistry::class)->clear();
});

it('translates the group name and labels in the current locale at serialization', function (): void {
    app()->setLocale('en');
    $arr = app(PermissionRegistry::class)->groups()[0]->toArray();

    expect($arr['name'])->toBe('Orders');
    expect($arr['items'][0])->toBe(['key' => 'admin.orders.view', 'label' => 'Orders: view']);
    // Without a translation the source string comes back unchanged.
    expect($arr['items'][1]['label'])->toBe('Выгрузка заказов');

    app()->setLocale('ru');
    expect(app(PermissionRegistry::class)->groups()[0]->toArray()['name'])->toBe('Заказы');
});

it('keeps an already-translated label as it is', function (): void {
    app()->setLocale('en');
    $arr = ItemPermission::group('Orders')->addPermission('a', 'Orders: view')->toArray();

    expect($arr['name'])->toBe('Orders');
    expect($arr['items'][0]['label'])->toBe('Orders: view');
});

it('returns permission labels in the request locale from /system/permissions', function (): void {
    $admin = AdminUser::create([
        'name' => 'Test Admin',
        'email' => 'perm-locale-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $en = $this->getJson('/api/admin/system/permissions', [LocaleResolver::HEADER => 'en']);
    $en->assertOk();
    expect($en->json('payload.groups.0.name'))->toBe('Orders');
    expect($en->json('payload.groups.0.items.0.label'))->toBe('Orders: view');

    $ru = $this->getJson('/api/admin/system/permissions', [LocaleResolver::HEADER => 'ru']);
    $ru->assertOk();
    expect($ru->json('payload.groups.0.name'))->toBe('Заказы');
    expect($ru->json('payload.groups.0.items.0.label'))->toBe('Заказы: просмотр');
});
