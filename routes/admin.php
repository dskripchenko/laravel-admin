<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
|
| These routes are registered in AdminServiceProvider under the
| config('admin.path') prefix and the optional config('admin.domain') domain.
| Only the routes of the public part (login/forgot-password) and of the SPA
| shell are added here. The JSON API is registered separately, through
| laravel-api -> AdminApiModule.
|
*/

// The SPA shell — catches every remaining path under the prefix
Route::get('{any?}', Dskripchenko\LaravelAdmin\Http\Controllers\ShellController::class)
    ->where('any', '.*')
    ->middleware(config('admin.middleware.shell'))
    ->name('admin.shell');
