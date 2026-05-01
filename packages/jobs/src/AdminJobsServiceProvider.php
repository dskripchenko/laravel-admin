<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs;

use Dskripchenko\LaravelAdminJobs\Services\JobOperations;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider пакета.
 *
 * - mergeConfigFrom — admin-jobs.php
 * - bind JobOperations как singleton
 * - регистрирует AdminJobsPlugin в `config('admin.plugins')`
 *
 * Auto-discovery через `extra.laravel.providers` в composer.json — host-проект
 * не должен ничего вручную добавлять в свой `config/app.php`.
 *
 * Плагин подцепляется core'овским AdminServiceProvider::bootPlugins(), который
 * читает `config('admin.plugins')` и instantiate'ит каждый класс через
 * PluginRegistry. Чтобы попасть туда вовремя, мы пушим в config на
 * register()-фазе (до boot всех SP).
 */
final class AdminJobsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-jobs.php', 'admin-jobs');

        $this->app->singleton(JobOperations::class);

        $this->registerPluginInConfig();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-jobs.php' => config_path('admin-jobs.php'),
        ], 'admin-jobs-config');

        $this->loadRoutesFrom(__DIR__.'/../routes/admin-jobs.php');
    }

    private function registerPluginInConfig(): void
    {
        $existing = (array) config('admin.plugins', []);
        if (in_array(AdminJobsPlugin::class, $existing, true)) {
            return;
        }
        config(['admin.plugins' => [...$existing, AdminJobsPlugin::class]]);
    }
}
