<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminTinymce;

use Illuminate\Support\ServiceProvider;

final class AdminTinymceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-tinymce.php', 'admin-tinymce');
        $this->registerPluginInConfig();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-tinymce.php' => config_path('admin-tinymce.php'),
        ], 'admin-tinymce-config');
    }

    private function registerPluginInConfig(): void
    {
        $existing = (array) config('admin.plugins', []);
        if (in_array(AdminTinymcePlugin::class, $existing, true)) {
            return;
        }
        config(['admin.plugins' => [...$existing, AdminTinymcePlugin::class]]);
    }
}
