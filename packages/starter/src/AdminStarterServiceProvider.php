<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminStarter;

use Illuminate\Support\ServiceProvider;

final class AdminStarterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-starter.php', 'admin-starter');

        $this->registerPluginInConfig();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-starter.php' => config_path('admin-starter.php'),
        ], 'admin-starter-config');
    }

    private function registerPluginInConfig(): void
    {
        $existing = (array) config('admin.plugins', []);
        if (in_array(AdminStarterPlugin::class, $existing, true)) {
            return;
        }
        config(['admin.plugins' => [...$existing, AdminStarterPlugin::class]]);
    }
}
