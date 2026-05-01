<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs;

use Dskripchenko\LaravelAdmin\Plugin\Concerns\RegistersAdminPlugin;
use Dskripchenko\LaravelAdminJobs\Services\JobOperations;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider пакета. Auto-discovery через `extra.laravel.providers`.
 */
final class AdminJobsServiceProvider extends ServiceProvider
{
    use RegistersAdminPlugin;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-jobs.php', 'admin-jobs');

        $this->app->singleton(JobOperations::class);

        $this->registerAdminPlugin(AdminJobsPlugin::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-jobs.php' => config_path('admin-jobs.php'),
        ], 'admin-jobs-config');

        $this->loadRoutesFrom(__DIR__.'/../routes/admin-jobs.php');
    }
}
