<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Support\Manifest;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `system` controller — actions для bootstrap, manifest, me, menu, locales,
 * permissions, plugins.
 *
 * См. docs/api/system.md и docs/api/registration.md.
 */
final class SystemController extends ApiController
{
    public function __construct(
        private readonly Admin $admin,
        private readonly Manifest $manifest,
        private readonly ResourceRegistry $resources,
        // Сохраняем для будущих фаз (P1.10+) — в menu для Screen-разделов.
        // @phpstan-ignore property.onlyWritten
        private readonly ScreenRegistry $screens,
    ) {}

    /**
     * Получить bootstrap-данные SPA (для xhr-стратегии).
     *
     * При стратегии `inline` (default) данные приходят через `<script>`-тег
     * shell.blade.php и этот action не вызывается.
     *
     * @output object  $payload
     * @output string  $payload.csrf
     * @output string  $payload.baseUrl
     * @output string  $payload.apiUrl
     * @output string  $payload.locale
     * @output array   $payload.availableLocales
     * @output string  $payload.theme
     * @output object  $payload.brand
     * @output object  ?$payload.user
     * @output array   $payload.permissions
     * @output string  $payload.manifestVersion
     * @output object  $payload.config
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {BootstrapResponse}
     * @response 401 {UnauthenticatedErrorResponse}
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $locale = (string) ($request->header('X-Admin-Locale') ?? config('admin.ui.default_locale', 'ru'));

        return $this->success([
            'csrf' => csrf_token(),
            'baseUrl' => url((string) config('admin.path', 'admin')),
            'apiUrl' => url((string) config('admin.api_path', 'api/admin')),
            'locale' => $locale,
            'availableLocales' => (array) config('admin.ui.available_locales', ['ru', 'en']),
            'theme' => (string) config('admin.ui.default_theme', 'light'),
            'brand' => (array) config('admin.brand', []),
            'user' => null,                         // будет заполнено в P2 после Auth
            'permissions' => [],                            // P2
            'manifestVersion' => $this->manifest->version($locale),
            'pluginVersions' => [],
            'config' => [
                'manifest' => ['etag' => (bool) config('admin.manifest.etag', true)],
                'bootstrap' => ['strategy' => (string) config('admin.bootstrap.strategy', 'inline')],
            ],
        ]);
    }

    /**
     * Получить полный JSON-манифест admin.
     *
     * @header string ?$If-None-Match Etag предыдущего ответа.
     *
     * @output object $payload Манифест.
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {ManifestResponse}
     * @response 304 {NotModifiedResponse}
     */
    public function manifest(Request $request): JsonResponse
    {
        $locale = (string) ($request->header('X-Admin-Locale') ?? config('admin.ui.default_locale', 'ru'));
        $payload = $this->manifest->build($locale);
        $etag = '"'.$payload['version'].'"';

        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch === $etag) {
            return new JsonResponse(null, Response::HTTP_NOT_MODIFIED, ['ETag' => $etag]);
        }

        return $this->success($payload)->header('ETag', $etag);
    }

    /**
     * Текущий администратор.
     *
     * На фазе P1 возвращает null (auth ещё не подключён). Заполнится в P2.
     *
     * @output object ?$payload AdminUserSummary либо null.
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {AdminUserSummaryResponse}
     */
    public function me(Request $request): JsonResponse
    {
        // ApiResponseHelper::say требует array (не null) — иначе TypeError на Arr::pull.
        return $this->success([]);
    }

    /**
     * Дерево меню сайдбара.
     *
     * На фазе P1 — заглушка с фиксированным «Resources» меню из ResourceRegistry.
     * Полная имплементация (с группами, иконками, badges) — фазы P2/P3.
     *
     * @output object $payload
     * @output array  $payload.items
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {MenuResponse}
     */
    public function menu(Request $request): JsonResponse
    {
        $items = [];
        foreach ($this->resources->all() as $slug => $class) {
            $resource = $this->resources->resolve($slug);
            if ($resource === null) {
                continue;
            }
            $items[] = [
                'key' => $slug,
                'label' => $resource::label(),
                'icon' => $resource::$icon,
                'url' => '/admin/resources/'.$slug,
                'badge' => null,
                'order' => 0,
            ];
        }

        return $this->success(['items' => $items]);
    }

    /**
     * Доступные локали admin.
     *
     * @output object $payload
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {LocalesResponse}
     */
    public function locales(Request $request): JsonResponse
    {
        return $this->success([
            'available' => (array) config('admin.ui.available_locales', ['ru', 'en']),
            'current' => (string) ($request->header('X-Admin-Locale') ?? config('admin.ui.default_locale', 'ru')),
            'fallback' => (string) config('admin.ui.fallback_locale', 'en'),
        ]);
    }

    /**
     * Группы permissions (для UI матрицы ролей). Заполнится в P2.
     *
     * @output object $payload
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {PermissionsResponse}
     */
    public function permissions(Request $request): JsonResponse
    {
        return $this->success(['groups' => []]);
    }

    /**
     * Список зарегистрированных AdminPlugin'ов.
     *
     * @output object $payload
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {PluginsResponse}
     */
    public function plugins(Request $request): JsonResponse
    {
        $plugins = [];
        foreach ($this->admin->getPlugins() as $class) {
            $plugins[] = [
                'id' => $class,
                'version' => '0.0.0-dev',
                'requires' => [],
            ];
        }

        return $this->success(['plugins' => $plugins]);
    }
}
