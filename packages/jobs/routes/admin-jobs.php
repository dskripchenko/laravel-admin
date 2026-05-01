<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess;
use Dskripchenko\LaravelAdminJobs\Http\Controllers\FailedJobController;
use Dskripchenko\LaravelAdminJobs\Http\Controllers\JobBatchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| admin-jobs custom routes
|--------------------------------------------------------------------------
| Все защищены AdminAccess middleware с конкретным permission-ключом.
| Подключаются service provider'ом в `boot()` под admin.api.prefix
| (по умолчанию `/api/admin`).
*/

$apiPrefix = (string) config('admin.api.prefix', 'api/admin');
$apiMiddleware = (array) config('admin.middleware.api', ['web']);

Route::prefix($apiPrefix)
    ->middleware($apiMiddleware)
    ->group(function () {
        // Failed jobs.
        Route::post('system/jobs/failed/retry', [FailedJobController::class, 'retry'])
            ->middleware(AdminAccess::class.':admin.system.jobs.failed.retry')
            ->name('admin.jobs.failed.retry');

        Route::post('system/jobs/failed/forget', [FailedJobController::class, 'forget'])
            ->middleware(AdminAccess::class.':admin.system.jobs.failed.forget')
            ->name('admin.jobs.failed.forget');

        Route::post('system/jobs/failed/retry-batch', [FailedJobController::class, 'retryBatch'])
            ->middleware(AdminAccess::class.':admin.system.jobs.failed.retry')
            ->name('admin.jobs.failed.retryBatch');

        Route::post('system/jobs/failed/forget-batch', [FailedJobController::class, 'forgetBatch'])
            ->middleware(AdminAccess::class.':admin.system.jobs.failed.forget')
            ->name('admin.jobs.failed.forgetBatch');

        // Batches.
        Route::post('system/jobs/batches/cancel', [JobBatchController::class, 'cancel'])
            ->middleware(AdminAccess::class.':admin.system.jobs.batches.manage')
            ->name('admin.jobs.batches.cancel');

        Route::post('system/jobs/batches/retry-failed', [JobBatchController::class, 'retryFailed'])
            ->middleware(AdminAccess::class.':admin.system.jobs.batches.manage')
            ->name('admin.jobs.batches.retryFailed');
    });
