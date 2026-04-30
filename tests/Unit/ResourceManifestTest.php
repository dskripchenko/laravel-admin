<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Resource\ResourceManifest;

it('ResourceManifest: enriches meta with screens block (list/create/edit)', function (): void {
    $description = ResourceManifest::describe(new TestUserResource);

    expect($description['slug'])->toBe('test-users');
    expect($description['screens'])->toHaveKeys(['list', 'create', 'edit']);

    expect($description['screens']['list']['slug'])->toBe('test-users.list');
    expect($description['screens']['list']['type'])->toBe('generated.list');
    expect($description['screens']['list']['permission'])->toBe('admin.test-users.view');

    expect($description['screens']['create']['slug'])->toBe('test-users.create');
    expect($description['screens']['create']['permission'])->toBe('admin.test-users.create');

    expect($description['screens']['edit']['slug'])->toBe('test-users.edit');
    expect($description['screens']['edit']['permission'])->toBe('admin.test-users.update');
});

it('ResourceManifest: keeps original Resource::meta fields intact', function (): void {
    $description = ResourceManifest::describe(new TestUserResource);

    expect($description)->toHaveKeys(['fields', 'columns', 'filters', 'actions', 'searchable', 'with', 'features', 'permissions']);
});

it('Manifest::build now exposes resources with screens block', function (): void {
    $manifest = $this->app->make(Dskripchenko\LaravelAdmin\Support\Manifest::class);
    $registry = $this->app->make(Dskripchenko\LaravelAdmin\Resource\ResourceRegistry::class);
    $registry->add(TestUserResource::class);

    $built = $manifest->build('ru');
    $resourceEntry = collect($built['resources'])->firstWhere('slug', 'test-users');

    expect($resourceEntry)->not->toBeNull();
    expect($resourceEntry['screens']['list']['type'])->toBe('generated.list');
});
