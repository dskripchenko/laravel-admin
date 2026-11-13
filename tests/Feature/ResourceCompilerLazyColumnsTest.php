<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Resource\ResourceCompiler;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;

require_once __DIR__.'/../Fixtures/ColumnsNeedRoutesResource.php';

it('compiles a resource without calling its columns and keeps the reveal action', function (): void {
    $registry = app(ResourceRegistry::class);
    $registry->add(ColumnsNeedRoutesResource::class);

    $compiled = app(ResourceCompiler::class)->compile($registry);

    $entry = collect($compiled)->first(fn (array $entry, string $key): bool => str_contains($key, 'columns-need-routes'));
    expect($entry)->not->toBeNull()
        ->and($entry['actions'])->toHaveKey('reveal');
});
