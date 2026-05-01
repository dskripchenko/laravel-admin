<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminMedia;

use Dskripchenko\LaravelAdminMedia\Services\ImageProcessor;
use Dskripchenko\LaravelAdminMedia\Services\MediaService;
use Illuminate\Support\ServiceProvider;

final class AdminMediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-media.php', 'admin-media');

        $this->app->singleton(ImageProcessor::class);
        $this->app->singleton(MediaService::class);

        $this->registerPluginInConfig();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-media.php' => config_path('admin-media.php'),
        ], 'admin-media-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/admin-media.php');
    }

    private function registerPluginInConfig(): void
    {
        $existing = (array) config('admin.plugins', []);
        if (in_array(AdminMediaPlugin::class, $existing, true)) {
            return;
        }
        config(['admin.plugins' => [...$existing, AdminMediaPlugin::class]]);
    }
}
