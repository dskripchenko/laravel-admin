<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Components\BaseModule;

/**
 * Admin API module — точка входа для laravel-api.
 *
 * Переопределяет несколько методов BaseModule, чтобы admin-API:
 *   - жил под собственным префиксом `api/admin` (config: admin.api_path);
 *   - не имел `{version}` сегмента в URL (паттерн `{controller}/{action}`);
 *   - имел собственный middleware-стек (config: admin.middleware.api).
 *
 * Версия одна — `admin` — и не экспонируется в URL (внутренний контракт
 * core↔SPA, см. ARCHITECTURE.md п.13.12). v1.8.1: класс открыт для
 * наследования — host-модули, сшивающие admin с собственными версиями,
 * мержат `parent::getApiVersionList()` (панели приходят автоматически).
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
     * Возвращает FQCN текущей API-версии. Для admin — единственная: `admin`.
     * Если `$version` не задана (например, до парсинга URL), резолвим через
     * ApiRequest, иначе fallback на 'admin'.
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
     * Префикс laravel-api маршрутов. Default 'api' — стандарт laravel-api.
     * Финальный URL получается `/{prefix}/{version}/{controller}/{action}`,
     * где version='admin' = /api/admin/{controller}/{action} — exactly то,
     * что задумано в `config('admin.api_path')`.
     *
     * Если host-проект использует свой префикс laravel-api (например, 'api/v1'),
     * админ окажется под ним: `/api/v1/admin/...`. Чтобы избежать конфликта,
     * сохраняем глобальный default 'api'.
     */
    public function getApiPrefix(): string
    {
        return (string) config('laravel-api.prefix', 'api');
    }

    /**
     * Стандартный URI-паттерн laravel-api с {version}.
     *
     * Версия `admin` фигурирует в URL как сегмент после prefix:
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
        // laravel-api регистрирует middleware-ГРУППУ один раз на boot'е —
        // это общий базовый стек для всех панелей (config admin.middleware.api;
        // его мидлвары panel-aware через Panels::currentGuard()). Панельные
        // ДОПОЛНЕНИЯ объявляются в admin.panels.{id}.middleware.api и
        // мержатся в global-middleware методов PanelApi::getMethods().
        /** @var array<int, mixed> $middleware */
        $middleware = (array) config('admin.middleware.api', []);

        return $middleware;
    }
}
