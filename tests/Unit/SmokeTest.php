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

it('registers admin guard in dedicated strategy via ServiceProvider boot', function (): void {
    expect(config('admin.auth.strategy'))->toBe('dedicated');
    expect(config('auth.guards.admin'))->toBeArray();
    expect(config('auth.guards.admin.provider'))->toBe('admin_users');
    expect(config('auth.providers.admin_users.model'))
        ->toBe(Dskripchenko\LaravelAdmin\Models\AdminUser::class);
});

it('registers expected artisan commands', function (): void {
    $commands = array_keys(Illuminate\Support\Facades\Artisan::all());

    expect($commands)->toContain('admin:install');
    expect($commands)->toContain('admin:user');
    expect($commands)->toContain('admin:link');
});

it('declares OpenAPI response templates on AdminApi', function (): void {
    $templates = Dskripchenko\LaravelAdmin\Http\AdminApi::getOpenApiTemplates();

    expect($templates)->toBeArray();
    expect(count($templates))->toBeGreaterThan(100);
    expect($templates)->toHaveKey('SuccessResponse');
    expect($templates)->toHaveKey('ValidationErrorResponse');
    expect($templates)->toHaveKey('AdminUserSummary');
    expect($templates)->toHaveKey('LoginResponse');
    expect($templates)->toHaveKey('ResourceMetaResponse');
    expect($templates)->toHaveKey('DelayedResponse');
});

it('AdminApi has $useResponseTemplates enabled', function (): void {
    expect(Dskripchenko\LaravelAdmin\Http\AdminApi::$useResponseTemplates)->toBeTrue();
});
