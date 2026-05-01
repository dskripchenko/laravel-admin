<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminStarter;

use Dskripchenko\LaravelAdmin\Plugin\Concerns\RegistersAdminPlugin;
use Illuminate\Support\ServiceProvider;

final class AdminStarterServiceProvider extends ServiceProvider
{
    use RegistersAdminPlugin;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-starter.php', 'admin-starter');

        $this->registerAdminPlugin(AdminStarterPlugin::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-starter.php' => config_path('admin-starter.php'),
        ], 'admin-starter-config');
    }
}
