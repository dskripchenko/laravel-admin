<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Resource\Resource;

/**
 * Тестирует автодетект Resource::hierarchyParentKey() через self-ref
 * Eloquent relations.
 */
it('detects parent_id via parent() BelongsTo on self', function (): void {
    $resource = new TestTreeNodeResource;
    expect($resource->hierarchyParentKey())->toBe('parent_id');
    expect($resource->viewMode())->toBe('tree');
});

it('returns null for non-hierarchical model', function (): void {
    $resource = new TestUserResource;
    expect($resource->hierarchyParentKey())->toBeNull();
    expect($resource->viewMode())->toBe('list');
});

it('explicit override forces list mode on hierarchical model', function (): void {
    $resource = new class extends Resource
    {
        public static string $model = TestTreeNodeModel::class;

        public function viewMode(): string
        {
            return 'list';
        }

        public function fields(): array
        {
            return [];
        }
    };

    expect($resource->hierarchyParentKey())->toBe('parent_id');
    expect($resource->viewMode())->toBe('list');
});

it('explicit override returns null even with relations present', function (): void {
    $resource = new class extends Resource
    {
        public static string $model = TestTreeNodeModel::class;

        public function hierarchyParentKey(): ?string
        {
            return null;
        }

        public function fields(): array
        {
            return [];
        }
    };

    expect($resource->hierarchyParentKey())->toBeNull();
    expect($resource->viewMode())->toBe('list');
});

it('respects custom FK column from BelongsTo', function (): void {
    $resource = new class extends Resource
    {
        public static string $model = TestCustomFkTreeModel::class;

        public function fields(): array
        {
            return [];
        }
    };

    expect($resource->hierarchyParentKey())->toBe('category_id');
});
