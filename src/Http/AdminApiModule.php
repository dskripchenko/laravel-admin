<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Components\BaseModule;

/**
 * The admin API module — laravel-api's entry point.
 *
 * It overrides a few of BaseModule's methods so that the admin API:
 *   - lives at `/{laravel-api.prefix}/admin` — `/api/admin` by default;
 *   - has no `{version}` segment in the URL (the pattern is
 *     `{controller}/{action}`);
 *   - has a middleware stack of its own (config: admin.middleware.api).
 *
 * There is a single version — `admin` — and it is not exposed in the URL; it
 * is the internal contract between the core and the SPA, see ARCHITECTURE.md
 * §13.12. The class is open for extension: host modules that stitch the admin
 * together with versions of their own merge
 * `parent::getApiVersionList()`, and the panels come along automatically.
 */
class AdminApiModule extends BaseModule
{
    /**
     * @return array<string, class-string<BaseApi>>
     */
    public function getApiVersionList(): array
    {
        $versions = ['admin' => AdminApi::class];

        /** @var \Dskripchenko\LaravelAdmin\Panel\PanelRegistry $panels */
        $panels = app(\Dskripchenko\LaravelAdmin\Panel\PanelRegistry::class);
        foreach ($panels->all() as $panel) {
            if ($panel->id === 'admin') {
                continue;
            }
            /** @var class-string<BaseApi> $apiClass */
            $apiClass = $panel->apiClass;
            $versions[$panel->id] = $apiClass;
        }

        return $versions;
    }

    /**
     * Returns the FQCN of the current API version; for the admin there is
     * only one, `admin`. When `$version` is not set — before the URL is
     * parsed, say — it is resolved through ApiRequest, falling back to
     * 'admin'.
     */
    public function getApi(?string $version = null): ?string
    {
        if ($version === null) {
            /** @var string|null $resolved */
            $resolved = \Dskripchenko\LaravelApi\Facades\ApiRequest::getApiVersion();
            $version = $resolved ?? 'admin';
        }

        return $this->getApiVersionList()[$version] ?? null;
    }

    /**
     * The prefix of the laravel-api routes; 'api' by default, as laravel-api
     * has it. The final URL becomes
     * `/{prefix}/{version}/{controller}/{action}`, and with version='admin'
     * that is /api/admin/{controller}/{action}.
     *
     * When a host project sets its own laravel-api prefix ('api/v1', say), the
     * admin moves underneath it, `/api/v1/admin/...`, and the SPA follows:
     * Panel::defaultApiPath() derives its URL from the same prefix.
     */
    public function getApiPrefix(): string
    {
        return (string) config('laravel-api.prefix', 'api');
    }

    /**
     * laravel-api's standard URI pattern, {version} included.
     *
     * The `admin` version appears in the URL as the segment after the prefix:
     * `/api/admin/{controller}/{action}`.
     */
    public function getApiUriPattern(): string
    {
        return (string) config('laravel-api.uri_pattern', '{version}/{controller}/{action}');
    }

    /**
     * laravel-api registers this as ONE middleware group, once, at boot — so
     * it must not depend on the request: under Octane the worker boots once
     * and would keep the first request's choice for every version after it.
     *
     * The group therefore holds two things only: CaptureApiRequest, which
     * fills the BaseApiRequest the version is read from, and
     * RunVersionMiddleware, which picks the stack per request — the panel
     * stack (config admin.middleware.api) for `admin` and the panels, the
     * BaseApi class's own middleware for the versions a host module adds. A
     * panel's ADDITIONS are declared in admin.panels.{id}.middleware.api and
     * merged into the global middleware of PanelApi::getMethods().
     *
     * @return array<int, mixed>
     */
    /**
     * Every named action route carries the module group.
     *
     * Since laravel-api 6.2.2 a named action route carries only its own
     * cascade and the module group stays on the generic
     * `{version}/{controller}/{action}` route — the group a plain BaseModule
     * builds depends on the request it was registered in. This module's group
     * does not (CaptureApiRequest + RunVersionMiddleware, see
     * getApiMiddleware()), so putting it back is safe, and it is needed:
     *   - the panel stack — the session, AdminAuth, the locale — reaches a
     *     panel request only through RunVersionMiddleware;
     *   - CaptureApiRequest re-reads the version, controller and action of
     *     every request; without it a long-lived process (Octane, a test
     *     suite) served a host version with the previous request's action.
     * RunActionMiddleware skips the middleware the route already carries, so
     * nothing runs twice.
     *
     * @return list<array<string, mixed>>
     */
    public function getRouteDefinitions(): array
    {
        $panels = app(\Dskripchenko\LaravelAdmin\Panel\PanelRegistry::class);
        $skipPanels = ! $this->needsPanelRoutes($panels);

        $cached = \Dskripchenko\LaravelApi\Services\ApiCache::routeDefinitions(static::class);
        if ($cached !== null) {
            $definitions = $skipPanels
                ? array_values(array_filter($cached, fn (array $d): bool => ! $this->isPanelVersion($panels, (string) ($d['version'] ?? ''))))
                : $cached;
        } else {
            $definitions = [];
            $uriPattern = $this->getApiUriPattern();
            foreach ($this->getApiVersionList() as $version => $apiClass) {
                if (! is_subclass_of($apiClass, BaseApi::class)) {
                    continue;
                }
                // A site request needs none of the panel's ~15 routes per resource:
                // on a big panel they are the larger half of the application's routes.
                if ($skipPanels && $this->isPanelVersion($panels, (string) $version)) {
                    continue;
                }
                foreach ($apiClass::operations((string) $version)->operations() as $operation) {
                    $nameSuffix = $operation->name ?: "{$operation->controllerKey}.{$operation->actionKey}";
                    $definitions[] = [
                        'version' => $version,
                        'controller' => $operation->controllerKey,
                        'action' => $operation->actionKey,
                        'methods' => $operation->httpMethods,
                        'name' => "api.{$version}.{$nameSuffix}",
                        'uri' => str_replace(
                            ['{version}', '{controller}', '{action}'],
                            [$version, $operation->controllerKey, $operation->actionKey],
                            $uriPattern,
                        ),
                        'middleware' => $operation->middleware(),
                        'exclude-middleware' => $operation->excludedMiddleware(),
                    ];
                }
            }
        }

        return array_map(static function (array $definition): array {
            $middleware = (array) ($definition['middleware'] ?? []);
            if (! in_array('api-middleware-group', $middleware, true)) {
                array_unshift($middleware, 'api-middleware-group');
            }
            $definition['middleware'] = $middleware;

            return $definition;
        }, $definitions);
    }

    /**
     * Whether this request needs the named routes of the panels.
     *
     * The console always does — `route:cache`, `route:list`, the test suite —
     * and so does a request under a panel's API prefix. Any other HTTP request
     * (the host's site, its own API) skips them: the generic
     * `{version}/{controller}/{action}` route still answers a panel call that
     * somehow arrives, so nothing breaks, it only saves the memory and time of
     * building every panel route on every page of the site.
     */
    protected function needsPanelRoutes(\Dskripchenko\LaravelAdmin\Panel\PanelRegistry $panels): bool
    {
        if ($this->runningInConsole() || ! app()->bound('request')) {
            return true;
        }

        $path = trim((string) app('request')->path(), '/');
        $prefix = trim($this->getApiPrefix(), '/');
        foreach ($panels->all() as $panel) {
            foreach ([ltrim(($prefix === '' ? '' : $prefix.'/').$panel->id, '/'), trim($panel->apiPath, '/')] as $base) {
                if ($base !== '' && ($path === $base || str_starts_with($path, $base.'/'))) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isPanelVersion(\Dskripchenko\LaravelAdmin\Panel\PanelRegistry $panels, string $version): bool
    {
        return $version === 'admin' || $panels->has($version);
    }

    /**
     * Separate so that a test can play an HTTP request.
     */
    protected function runningInConsole(): bool
    {
        return app()->runningInConsole();
    }

    public function getApiMiddleware(): array
    {
        return [
            Middleware\CaptureApiRequest::class,
            Middleware\RunVersionMiddleware::class,
        ];
    }
}
