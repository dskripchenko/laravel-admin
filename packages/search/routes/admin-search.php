<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdminSearch\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

$apiPrefix = (string) config('admin.api.prefix', 'api/admin');
$apiMiddleware = (array) config('admin.middleware.api', ['web']);

Route::prefix($apiPrefix)
    ->middleware($apiMiddleware)
    ->group(function () {
        Route::get('system/search', [SearchController::class, 'search'])
            ->name('admin.search');
    });
