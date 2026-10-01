<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Panel\Panel;
use Dskripchenko\LaravelAdmin\Uploads\UploadController;

// The admin API is served by laravel-api at /{laravel-api.prefix}/admin. The
// URL handed to the SPA used to be a fixed `api/admin`, so a host that moved
// its laravel-api prefix got an admin whose every request was a 404.

it('derives the SPA API url from the laravel-api prefix', function (): void {
    config()->set('admin.api_path', null);
    config()->set('laravel-api.prefix', 'api/v1');

    expect(Panel::default()->apiPath)->toBe('api/v1/admin')
        ->and(Panel::apiPathFor('client'))->toBe('api/v1/client')
        ->and(Panel::fromConfig('client', [])->apiPath)->toBe('api/v1/client')
        ->and(UploadController::serveUrl('local', 'a.txt'))->toStartWith('/api/v1/admin/uploads/serve');
});

it('keeps /api/admin by default', function (): void {
    config()->set('admin.api_path', null);

    expect(Panel::default()->apiPath)->toBe('api/admin');
});

it('lets an explicit admin.api_path win', function (): void {
    config()->set('admin.api_path', '/backoffice/api/');

    expect(Panel::default()->apiPath)->toBe('backoffice/api');
});
