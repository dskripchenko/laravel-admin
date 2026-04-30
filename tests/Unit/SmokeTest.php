<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Facades\Admin as AdminFacade;

it('binds Admin manager to the container', function (): void {
    expect(app(Admin::class))->toBeInstanceOf(Admin::class);
});

it('exposes Admin facade', function (): void {
    expect(AdminFacade::version())->toStartWith('0.1.');
});

it('loads default config', function (): void {
    expect(config('admin.path'))->toBe('admin');
    expect(config('admin.auth.guard'))->toBe('admin');
    expect(config('admin.bootstrap.strategy'))->toBe('inline');
});

it('registers resources and exposes them', function (): void {
    AdminFacade::resources([
        'App\\Admin\\Resources\\UserResource',
        'App\\Admin\\Resources\\OrderResource',
    ]);

    expect(AdminFacade::getResources())
        ->toContain('App\\Admin\\Resources\\UserResource')
        ->toContain('App\\Admin\\Resources\\OrderResource');
});
