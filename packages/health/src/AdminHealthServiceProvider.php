<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth;

use Dskripchenko\LaravelAdmin\Plugin\Concerns\RegistersAdminPlugin;
use Dskripchenko\LaravelAdminHealth\Console\CleanupHealthResultsCommand;
use Dskripchenko\LaravelAdminHealth\Console\RunHealthChecksCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider пакета.
 *
 * - mergeConfigFrom — admin-health.php
 * - bind HealthRegistry, HealthRunner singletons
 * - регистрирует checks из config('admin-health.checks') в HealthRegistry
 *   на boot()-фазе
 * - регистрирует AdminHealthPlugin в config('admin.plugins')
 * - подключает миграции, артизан-команды
 */
final class AdminHealthServiceProvider extends ServiceProvider
{
    use RegistersAdminPlugin;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-health.php', 'admin-health');

        $this->app->singleton(HealthRegistry::class);
        $this->app->singleton(HealthRunner::class);

        $this->registerAdminPlugin(AdminHealthPlugin::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-health.php' => config_path('admin-health.php'),
        ], 'admin-health-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunHealthChecksCommand::class,
                CleanupHealthResultsCommand::class,
            ]);
        }

        $this->registerHealthChecks();
    }

    /**
     * Считывает config('admin-health.checks') и регистрирует каждый class
     * в HealthRegistry с переданным config-array.
     */
    private function registerHealthChecks(): void
    {
        /** @var array<class-string<HealthCheck>, array<string, mixed>> $defs */
        $defs = (array) config('admin-health.checks', []);
        if ($defs === []) {
            return;
        }

        /** @var HealthRegistry $registry */
        $registry = $this->app->make(HealthRegistry::class);
        foreach ($defs as $class => $config) {
            if (! class_exists($class)) {
                continue;
            }
            $registry->register($class, $config);
        }
    }
}
