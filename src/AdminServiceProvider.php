<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin;

use Dskripchenko\LaravelAdmin\Auth\AdminGuardRegistrar;
use Dskripchenko\LaravelAdmin\Console\InstallCommand;
use Dskripchenko\LaravelAdmin\Console\LinkCommand;
use Dskripchenko\LaravelAdmin\Console\MakeAdminCommand;
use Dskripchenko\LaravelAdmin\Http\AdminApiModule;
use Dskripchenko\LaravelAdmin\Permission\PermissionRegistry;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Support\Manifest;
use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;
use Dskripchenko\LaravelApi\Providers\ApiServiceProvider;
use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

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
        $this->mergeConfigFrom(__DIR__.'/../config/admin.php', 'admin');

        // laravel-api не имеет auto-discovery — регистрируем явно.
        // app->register() безопасно: повторная регистрация игнорируется.
        $this->app->register(ApiServiceProvider::class);

        $this->app->singleton(ScreenRegistry::class);
        $this->app->singleton(ResourceRegistry::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(Settings\SettingsRegistry::class);
        $this->app->singleton(
            Settings\Storage\SettingsStorage::class,
            Settings\Storage\KeyValueSettingsStorage::class,
        );
        $this->app->singleton(Plugin\PluginRegistry::class);

        $this->app->singleton(
            Tenancy\TenantResolver::class,
            Tenancy\SingleTenantResolver::class,
        );
        $this->app->singleton(Tenancy\TenantContext::class);

        $this->app->singleton(DelayedProcess\AllowlistRegistrar::class);

        $this->app->singleton(Export\ExporterRegistry::class, function (): Export\ExporterRegistry {
            $registry = new Export\ExporterRegistry;
            $registry->add(new Export\CsvExporter);
            if (class_exists(\OpenSpout\Writer\XLSX\Writer::class)) {
                $registry->add(new Export\XlsxExporter);
            }

            $renderer = $this->resolvePdfRenderer();
            if ($renderer !== null) {
                $registry->add(new Export\PdfExporter($renderer));
            }

            return $registry;
        });

        $this->app->singleton(Admin::class, fn (Application $app) => new Admin(
            $app,
            $app->make(ScreenRegistry::class),
            $app->make(ResourceRegistry::class),
            $app->make(PermissionRegistry::class),
        ));
        $this->app->alias(Admin::class, 'admin');

        $this->app->singleton(Manifest::class);

        // Override laravel-api's `api_module` to our AdminApiModule.
        // Pre-condition: laravel-api's ApiServiceProvider already ran register()
        // (Laravel auto-discovers it earlier alphabetically). Our singleton
        // replaces the default BaseModule binding so admin API is served by
        // our module with prefix 'api/admin' and uri-pattern '{controller}/{action}'.
        $this->app->singleton('api_module', AdminApiModule::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin.php' => config_path('admin.php'),
        ], 'admin-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'admin-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/admin'),
        ], 'admin-views');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'admin');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerAdminGuard();
        $this->registerRoutes();
        $this->registerCommands();
        $this->registerExceptionHandlers();
        $this->registerAuditListeners();
        $this->bootPlugins();
    }

    /**
     * Резолвит PDF-renderer на основе config + установленных пакетов.
     *
     * Driver приоритизация:
     *   1. config('admin.exports.pdf.driver') = 'mpdf' / 'dompdf' — берём напрямую.
     *   2. fallback: первый available из mpdf, dompdf.
     *   3. ни одного — возвращаем null (PdfExporter не регистрируется).
     */
    private function resolvePdfRenderer(): ?Export\Pdf\PdfRenderer
    {
        $configured = (string) config('admin.exports.pdf.driver', 'mpdf');

        if ($configured === 'mpdf' && class_exists(\Mpdf\Mpdf::class)) {
            return new Export\Pdf\MpdfRenderer;
        }
        if ($configured === 'dompdf' && class_exists(\Dompdf\Dompdf::class)) {
            return new Export\Pdf\DompdfRenderer;
        }

        // Fallback: что-то одно установлено, но не configured — берём что есть.
        if (class_exists(\Mpdf\Mpdf::class)) {
            return new Export\Pdf\MpdfRenderer;
        }
        if (class_exists(\Dompdf\Dompdf::class)) {
            return new Export\Pdf\DompdfRenderer;
        }

        return null;
    }

    /**
     * Регистрирует и boot'ит плагины из config('admin.plugins').
     */
    private function bootPlugins(): void
    {
        $configured = (array) config('admin.plugins', []);
        if ($configured === []) {
            return;
        }

        /** @var Plugin\PluginRegistry $registry */
        $registry = $this->app->make(Plugin\PluginRegistry::class);
        /** @var list<class-string<Plugin\AdminPlugin>> $classes */
        $classes = array_values(array_filter($configured, 'is_string'));
        $registry->addMany($classes);

        /** @var Admin $admin */
        $admin = $this->app->make(Admin::class);
        $registry->bootAll($admin);
    }

    /**
     * Регистрирует слушателей admin-auth событий для записи в audit-log.
     */
    private function registerAuditListeners(): void
    {
        if (! (bool) config('admin.audit.enabled', true)) {
            return;
        }
        if (! (bool) config('admin.audit.log_auth_events', true)) {
            return;
        }
        \Illuminate\Support\Facades\Event::subscribe(
            Audit\AuthAuditListener::class,
        );
    }

    /**
     * Регистрирует обработчики исключений в ApiErrorHandler из laravel-api.
     *
     * Без этого ValidationException возвращается как 500, потому что laravel-api
     * не имеет встроенной поддержки Laravel'овского ValidationException.
     */
    private function registerExceptionHandlers(): void
    {
        ApiErrorHandler::addErrorHandler(
            ValidationException::class,
            static function (ValidationException $e) {
                return ApiResponseHelper::sayError([
                    'errorKey' => 'validation',
                    'message' => $e->getMessage(),
                    'messages' => $e->errors(),
                ], 422);
            },
        );
    }

    private function registerAdminGuard(): void
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);
        (new AdminGuardRegistrar($config))->register();
    }

    private function registerRoutes(): void
    {
        // SPA-shell под admin.path (например /admin/*).
        Route::group([
            'prefix' => (string) config('admin.path'),
            'domain' => config('admin.domain'),
            'as' => 'admin.',
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
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

        $this->commands([
            InstallCommand::class,
            MakeAdminCommand::class,
            LinkCommand::class,
            // Make-команды для скаффолда (admin:make-resource / make-screen / ...) — фаза P3+.
        ]);
    }
}
