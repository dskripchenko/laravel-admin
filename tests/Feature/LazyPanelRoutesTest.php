<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApiModule;
use Illuminate\Http\Request;

function lazyRoutesModule(): AdminApiModule
{
    return new class extends AdminApiModule
    {
        protected function runningInConsole(): bool
        {
            return false;
        }
    };
}

function panelVersions(array $definitions): array
{
    return array_values(array_unique(array_column($definitions, 'version')));
}

it('builds every panel route in the console', function (): void {
    $definitions = (new AdminApiModule)->getRouteDefinitions();

    expect(panelVersions($definitions))->toContain('admin')
        ->and($definitions[0]['middleware'])->toContain('api-middleware-group');
});

it('skips panel routes on a request outside the panel API', function (): void {
    app()->instance('request', Request::create('/catalog/page'));

    expect(lazyRoutesModule()->getRouteDefinitions())->toBe([]);
});

it('builds panel routes on a request to the panel API', function (): void {
    $prefix = trim((string) config('laravel-api.prefix', 'api'), '/');
    app()->instance('request', Request::create("/{$prefix}/admin/resource/index"));

    $definitions = lazyRoutesModule()->getRouteDefinitions();

    expect(panelVersions($definitions))->toBe(['admin'])
        ->and(count($definitions))->toBe(count((new AdminApiModule)->getRouteDefinitions()));
});
