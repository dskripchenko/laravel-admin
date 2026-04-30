<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Главный сервис-провайдер пакета.
 *
 * Состоит из двух фаз:
 * - register():  биндим Admin manager в контейнер, мерджим конфиг.
 * - boot():      публикация конфига/миграций/views, регистрация роутов и macro,
 *                подключение plugin'ов, авто-регистрация guard через AdminGuardRegistrar.
 *
 * На текущей фазе (P0 скаффолд) реализованы только публикация и регистрация
 * сервисного контейнера; функциональные слои (Resource/Screen/Layout/...)
 * подключаются по мере имплементации.
 */
final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/admin.php', 'admin');

        $this->app->singleton(Admin::class, fn (Application $app) => new Admin($app));
        $this->app->alias(Admin::class, 'admin');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/admin.php' => config_path('admin.php'),
        ], 'admin-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'admin-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/admin'),
        ], 'admin-views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'admin');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->registerRoutes();
        $this->registerCommands();
    }

    private function registerRoutes(): void
    {
        // SPA-shell под admin.path (например /admin/*).
        Route::group([
            'prefix'     => (string) config('admin.path'),
            'domain'     => config('admin.domain'),
            'as'         => 'admin.',
        ], function (): void {
            $this->loadRoutesFrom(__DIR__ . '/../routes/admin.php');
        });

        // API живёт отдельно, под admin.api_path (например /api/admin/*).
        // Регистрация эндпоинтов через AdminApiModule + laravel-api делается на фазе P1.
        // На фазе P0 — только префикс зарезервирован, роуты добавляются позже.
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Регистрация artisan-команд по мере имплементации (P0/P2/...)
        // $this->commands([
        //     Console\InstallCommand::class,
        //     Console\MakeResourceCommand::class,
        //     Console\MakeAdminCommand::class,
        //     Console\LinkCommand::class,
        // ]);
    }
}
