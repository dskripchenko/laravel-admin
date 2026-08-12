<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Components\BaseModule;

/**
 * The admin API module — laravel-api's entry point.
 *
 * It overrides a few of BaseModule's methods so that the admin API:
 *   - lives under its own prefix, `api/admin` (config: admin.api_path);
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
     * that is /api/admin/{controller}/{action} — exactly what
     * `config('admin.api_path')` intends.
     *
     * When a host project uses its own laravel-api prefix ('api/v1', say), the
     * admin ends up underneath it: `/api/v1/admin/...`. To avoid the clash we
     * keep the global default of 'api'.
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
     * @return array<int, mixed>
     */
    public function getApiMiddleware(): array
    {
        // laravel-api registers the middleware GROUP once at boot: it is the
        // common base stack of every panel (config admin.middleware.api, whose
        // middleware are panel-aware through Panels::currentGuard()). A
        // panel's own ADDITIONS are declared in
        // admin.panels.{id}.middleware.api and merged into the global
        // middleware of PanelApi::getMethods().
        /** @var array<int, mixed> $middleware */
        $middleware = (array) config('admin.middleware.api', []);

        return $middleware;
    }
}
