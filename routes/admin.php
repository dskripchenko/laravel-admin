<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
|
| Эти роуты регистрируются в AdminServiceProvider под префиксом config('admin.path')
| и опциональным доменом config('admin.domain'). Тут добавляются только роуты
| публичной части (login/forgot-password) и SPA-оболочки. JSON API регистрируется
| отдельно через laravel-api -> AdminApiModule.
|
*/

// The SPA shell — catches every remaining path under the prefix
Route::get('{any?}', Dskripchenko\LaravelAdmin\Http\Controllers\ShellController::class)
    ->where('any', '.*')
    ->middleware(config('admin.middleware.shell'))
    ->name('admin.shell');
