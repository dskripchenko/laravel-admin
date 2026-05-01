<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse;

use Dskripchenko\LaravelAdmin\Plugin\Concerns\RegistersAdminPlugin;
use Dskripchenko\LaravelAdminPulse\Console\AggregateCommand;
use Dskripchenko\LaravelAdminPulse\Console\RotateCommand;
use Dskripchenko\LaravelAdminPulse\Http\Middleware\PulseMiddleware;
use Dskripchenko\LaravelAdminPulse\Services\Aggregator;
use Dskripchenko\LaravelAdminPulse\Services\Sampler;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class AdminPulseServiceProvider extends ServiceProvider
{
    use RegistersAdminPlugin;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-pulse.php', 'admin-pulse');

        $this->app->singleton(Sampler::class);
        $this->app->singleton(Aggregator::class);

        $this->registerAdminPlugin(AdminPulsePlugin::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-pulse.php' => config_path('admin-pulse.php'),
        ], 'admin-pulse-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AggregateCommand::class,
                RotateCommand::class,
            ]);
        }

        $this->registerMiddlewareAlias();
    }

    /**
     * Регистрируем middleware-alias 'pulse' для использования
     * в host-роутах:
     *
     *     Route::middleware('pulse')->group(...);
     */
    private function registerMiddlewareAlias(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('pulse', PulseMiddleware::class);
    }
}
