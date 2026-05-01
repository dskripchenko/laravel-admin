<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminSearch;

use Dskripchenko\LaravelAdmin\Plugin\Concerns\RegistersAdminPlugin;
use Dskripchenko\LaravelAdminSearch\Drivers\EloquentSearchDriver;
use Dskripchenko\LaravelAdminSearch\Drivers\ScoutSearchDriver;
use Dskripchenko\LaravelAdminSearch\Drivers\SearchDriver;
use Illuminate\Support\ServiceProvider;

final class AdminSearchServiceProvider extends ServiceProvider
{
    use RegistersAdminPlugin;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-search.php', 'admin-search');

        $this->app->singleton(SearchDriver::class, function () {
            $driver = (string) config('admin-search.driver', 'eloquent');

            return $driver === 'scout' ? new ScoutSearchDriver : new EloquentSearchDriver;
        });

        $this->app->singleton(SearchService::class);

        $this->registerAdminPlugin(AdminSearchPlugin::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-search.php' => config_path('admin-search.php'),
        ], 'admin-search-config');

        $this->loadRoutesFrom(__DIR__.'/../routes/admin-search.php');
    }
}
