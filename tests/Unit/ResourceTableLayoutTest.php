<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Layout\ResourceTable;

it('ResourceTable::for() returns layout with admin.resource-table type', function (): void {
    $layout = ResourceTable::for(TestTreeNodeResource::class);

    $arr = $layout->toArray();
    expect($arr['type'])->toBe('admin.resource-table');
    expect($arr['kind'])->toBe('layout');
});

it('ResourceTable serializes resource slug + foreign_key + parent_field defaults', function (): void {
    $layout = ResourceTable::for(TestTreeNodeResource::class);

    $arr = $layout->toArray();
    expect($arr['resource'])->toBe('test-tree-nodes');
    expect($arr['parent_field'])->toBe('id');
    expect($arr['hide_columns'])->toBe([]);
    expect($arr['features'])->toBe(['create' => false, 'delete' => false, 'bulkDelete' => false]);
});

it('ResourceTable::foreignKey() / hideColumns() / features() override defaults', function (): void {
    $layout = ResourceTable::for(TestTreeNodeResource::class)
        ->foreignKey('parent_id')
        ->parentField('uuid')
        ->hideColumns(['parent_id', 'tenant_id'])
        ->features(['create' => true, 'bulkDelete' => true]);

    $arr = $layout->toArray();
    expect($arr['foreign_key'])->toBe('parent_id');
    expect($arr['parent_field'])->toBe('uuid');
    expect($arr['hide_columns'])->toBe(['parent_id', 'tenant_id']);
    expect($arr['features'])->toBe(['create' => true, 'delete' => false, 'bulkDelete' => true]);
});

it('ResourceTable::for() rejects non-Resource class', function (): void {
    expect(fn () => ResourceTable::for(stdClass::class))
        ->toThrow(InvalidArgumentException::class);
});
