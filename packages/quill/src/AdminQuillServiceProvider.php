<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminQuill;

use Dskripchenko\LaravelAdmin\Plugin\Concerns\RegistersAdminPlugin;
use Illuminate\Support\ServiceProvider;

final class AdminQuillServiceProvider extends ServiceProvider
{
    use RegistersAdminPlugin;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-quill.php', 'admin-quill');
        $this->registerAdminPlugin(AdminQuillPlugin::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-quill.php' => config_path('admin-quill.php'),
        ], 'admin-quill-config');
    }
}
