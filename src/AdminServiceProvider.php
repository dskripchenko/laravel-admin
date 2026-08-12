<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin;

use Dskripchenko\LaravelAdmin\Auth\AdminGuardRegistrar;
use Dskripchenko\LaravelAdmin\Console\InstallCommand;
use Dskripchenko\LaravelAdmin\Console\LinkCommand;
use Dskripchenko\LaravelAdmin\Console\MakeAdminCommand;
use Dskripchenko\LaravelAdmin\Console\MakeResourceCommand;
use Dskripchenko\LaravelAdmin\Console\MakeScreenCommand;
use Dskripchenko\LaravelAdmin\Console\MakeSectionCommand;
use Dskripchenko\LaravelAdmin\Console\MakeWidgetCommand;
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
 * The package's main service provider.
 *
 * Two phases:
 * - register(): binds the Admin manager into the container and merges the config.
 * - boot():     publishes config, migrations and views, registers routes and
 *               macros, boots the plugins and auto-registers the guard through
 *               AdminGuardRegistrar.
 */
final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin.php', 'admin');

        // laravel-api has no auto-discovery, so it is registered explicitly.
        // app->register() is safe: a repeated registration is ignored.
        $this->app->register(ApiServiceProvider::class);

        $this->app->singleton(ScreenRegistry::class);
        $this->app->singleton(ResourceRegistry::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(Menu\MenuRegistry::class);
        $this->app->singleton(Settings\SettingsRegistry::class);
        $this->app->singleton(
            Settings\Storage\SettingsStorage::class,
            Settings\Storage\KeyValueSettingsStorage::class,
        );
        $this->app->singleton(Plugin\PluginRegistry::class);
        $this->app->singleton(Panel\PanelRegistry::class);

        // Tenancy is bound as `scoped()` — per request — rather than as a
        // singleton: in long-running runtimes (Octane, queue workers) the
        // current tenant of one request could otherwise leak into the next.
        // Laravel resets scoped bindings between requests by itself.
        $this->app->scoped(
            Tenancy\TenantResolver::class,
            Tenancy\SingleTenantResolver::class,
        );
        $this->app->scoped(Tenancy\TenantContext::class);

        $this->app->singleton(DelayedProcess\AllowlistRegistrar::class);

        $this->app->singleton(Theme\ThemeManager::class);
        $this->app->singleton(Theme\LocaleResolver::class);

        $this->app->singleton(Import\ImportPreviewService::class);

        $this->app->singleton(Export\ExporterRegistry::class, function (): Export\ExporterRegistry {
            $registry = new Export\ExporterRegistry;
            $registry->add(new Export\CsvExporter);
            // The JSON exporter needs no dependencies and is always available.
            $registry->add(new Export\JsonExporter);
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

        // Manifest memoises the payloads it assembles, so that bootstrap and
        // /manifest do not build the same thing twice within a request. That
        // memo was written for FPM, where a singleton IS one request; under
        // Octane the instance lives as long as the worker, and the memo
        // quietly turns from deduplication into a cross-request cache. Today
        // the content depends only on the locale and the panel, and both are
        // in the key — but Manifest itself records that permission filtering
        // is still ahead. On that day the key stops describing the content,
        // and a worker hands one person's manifest to another. Tenancy is
        // scoped right here for the same reason.
        //
        // BootstrapBuilder moves along with it, and not for company: it holds
        // Manifest in its constructor, so a singleton would capture the scoped
        // instance of the first request for good — worse than before.
        $this->app->scoped(Manifest::class);
        $this->app->scoped(Support\BootstrapBuilder::class);

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

        $this->publishes([
            __DIR__.'/../resources/stubs/admin' => resource_path('stubs/admin'),
        ], 'admin-stubs');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'admin');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'admin');
        // The JSON dictionary, keyed by the source string, feeds the
        // frontend's tr(); BootstrapBuilder puts it into the bag, and a host
        // may override it with its own lang/{locale}.json.
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');

        $this->registerAdminGuards();
        $this->registerCommands();
        $this->registerExceptionHandlers();
        $this->registerAuditListeners();
        // bootPlugins must come BEFORE registerRoutes: plugins register their
        // resources through Admin::resources(), and ResourceCompiler reads
        // ResourceRegistry while compiling the admin API routes.
        $this->bootPlugins();
        $this->registerRoutes();
        $this->registerScalarDoc();

        // laravel-api caches AdminApi::getPreparedMethods() in a static
        // property. If anything called getPreparedMethods BEFORE bootPlugins()
        // — laravel-api's own ApiServiceProvider might, since provider order
        // is not deterministic — that cache holds an empty registry. We reset
        // it so the first call at request time recomputes with the resources
        // actually registered.
        Http\AdminApi::clearCache();
    }

    /**
     * Takes over laravel-api's default `api/doc` route and serves Scalar UI
     * instead.
     *
     * laravel-api registers `api/doc` in its own boot() through
     * `Route::get('doc', ApiDocumentationController@index)` under the `api`
     * prefix. Our route has the same URI and registers later, so it wins.
     *
     * To switch it off: `config('admin.openapi.ui') = 'swagger'` or null.
     */
    private function registerScalarDoc(): void
    {
        $ui = (string) config('admin.openapi.ui', 'scalar');
        if ($ui !== 'scalar') {
            return;
        }

        $prefix = (string) config('admin.api_path', 'api/admin');
        $routePath = $prefix === '' ? 'doc' : "{$prefix}/doc";

        Route::get($routePath, Http\Controllers\ScalarDocController::class)
            ->name('admin.api-doc')
            ->middleware(config('admin.middleware.shell', ['web']));
    }

    /**
     * Resolves the PDF renderer from the config and the installed packages.
     *
     * Driver priority:
     *   1. config('admin.exports.pdf.driver') = 'mpdf' / 'dompdf' — taken as is.
     *   2. otherwise the first available of mpdf, dompdf.
     *   3. neither — null, and PdfExporter is not registered at all.
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

        // Fallback: one of them is installed but not configured — take what there is.
        if (class_exists(\Mpdf\Mpdf::class)) {
            return new Export\Pdf\MpdfRenderer;
        }
        if (class_exists(\Dompdf\Dompdf::class)) {
            return new Export\Pdf\DompdfRenderer;
        }

        return null;
    }

    /**
     * Registers and boots the plugins listed in config('admin.plugins').
     */
    private function bootPlugins(): void
    {
        /** @var Panel\PanelRegistry $panels */
        $panels = $this->app->make(Panel\PanelRegistry::class);

        /** @var Plugin\PluginRegistry $registry */
        $registry = $this->app->make(Plugin\PluginRegistry::class);

        $any = false;
        foreach ($panels->all() as $panel) {
            if ($panel->plugins === []) {
                continue;
            }
            /** @var list<class-string<Plugin\AdminPlugin>> $classes */
            $classes = $panel->plugins;
            $registry->addMany($classes, $panel->id);
            $any = true;
        }
        if (! $any) {
            return;
        }

        /** @var Admin $admin */
        $admin = $this->app->make(Admin::class);
        $registry->bootAll($admin);
    }

    /**
     * Registers listeners for the admin auth events, to record them in the audit log.
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
     * Registers exception handlers in laravel-api's ApiErrorHandler.
     *
     * Without this a ValidationException comes back as a 500, because
     * laravel-api has no built-in support for Laravel's ValidationException.
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

    private function registerAdminGuards(): void
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);
        /** @var Panel\PanelRegistry $panels */
        $panels = $this->app->make(Panel\PanelRegistry::class);

        $registrar = new AdminGuardRegistrar($config);
        foreach ($panels->all() as $panel) {
            // The default panel reads the legacy admin.auth.* for backward
            // compatibility; the others read their own auth block from
            // admin.panels.{id}.auth.
            $panel->id === 'admin'
                ? $registrar->register()
                : $registrar->registerFor($panel);
        }
    }

    private function registerRoutes(): void
    {
        /** @var Panel\PanelRegistry $panels */
        $panels = $this->app->make(Panel\PanelRegistry::class);

        // Shell routes of the panels go from the most specific prefix to the
        // root, so that a panel mounted at '' does not swallow another panel's
        // paths first.
        $ordered = $panels->all();
        uasort($ordered, static fn (Panel\Panel $a, Panel\Panel $b): int => strlen($b->path) <=> strlen($a->path));

        foreach ($ordered as $panel) {
            $anyPattern = '.*';
            if ($panel->excludePrefixes !== []) {
                // A catch-all that does not swallow other prefixes: api/, draft/, …
                $quoted = array_map(
                    static fn (string $prefix): string => preg_quote(trim($prefix, '/'), '#'),
                    $panel->excludePrefixes,
                );
                $anyPattern = '(?!(?:'.implode('|', $quoted).')(?:/|$)).*';
            }

            Route::group([
                'prefix' => $panel->path,
                'domain' => config('admin.domain'),
                'as' => $panel->id.'.',
            ], function () use ($panel, $anyPattern): void {
                Route::get('{any?}', Http\Controllers\ShellController::class)
                    ->where('any', $anyPattern)
                    ->middleware($panel->id === 'admin'
                        ? (array) config('admin.middleware.shell', [])
                        : $panel->shellMiddleware())
                    ->defaults('adminPanel', $panel->id)
                    ->name('shell');
            });
        }

        // The API lives separately: /api/{panel}/{controller}/{action} through
        // AdminApiModule, where laravel-api's version equals the panel id.
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
            MakeSectionCommand::class,
            MakeResourceCommand::class,
            MakeScreenCommand::class,
            MakeWidgetCommand::class,
        ]);
    }
}
