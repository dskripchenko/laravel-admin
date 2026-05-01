<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminQuill;

use Illuminate\Support\ServiceProvider;

final class AdminQuillServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-quill.php', 'admin-quill');
        $this->registerPluginInConfig();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-quill.php' => config_path('admin-quill.php'),
        ], 'admin-quill-config');
    }

    private function registerPluginInConfig(): void
    {
        $existing = (array) config('admin.plugins', []);
        if (in_array(AdminQuillPlugin::class, $existing, true)) {
            return;
        }
        config(['admin.plugins' => [...$existing, AdminQuillPlugin::class]]);
    }
}
