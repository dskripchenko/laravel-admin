<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminTinymce;

use Dskripchenko\LaravelAdmin\Plugin\Concerns\RegistersAdminPlugin;
use Illuminate\Support\ServiceProvider;

final class AdminTinymceServiceProvider extends ServiceProvider
{
    use RegistersAdminPlugin;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-tinymce.php', 'admin-tinymce');
        $this->registerAdminPlugin(AdminTinymcePlugin::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-tinymce.php' => config_path('admin-tinymce.php'),
        ], 'admin-tinymce-config');
    }
}
