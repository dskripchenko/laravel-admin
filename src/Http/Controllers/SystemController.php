<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Impersonation\ImpersonationManager;
use Dskripchenko\LaravelAdmin\Permission\PermissionRegistry;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Support\Manifest;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        private readonly PermissionRegistry $permissions,
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
    public function bootstrap(Request $request, \Dskripchenko\LaravelAdmin\Support\BootstrapBuilder $builder): JsonResponse
    {
        return $this->success($builder->build($request));
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
    public function me(Request $request, ImpersonationManager $impersonation): JsonResponse
    {
        $guard = (string) config('admin.auth.guard', 'admin');
        $user = Auth::guard($guard)->user();

        if (! $user instanceof Model) {
            return $this->success([]);
        }

        $impersonator = null;
        if ($impersonation->isActive()) {
            $provider = Auth::createUserProvider(
                (string) config('admin.auth.provider', 'admin_users'),
            );
            $original = $provider?->retrieveById($impersonation->impersonatorId());
            if ($original instanceof Model) {
                $impersonator = [
                    'id' => $original->getKey(),
                    'name' => $original->getAttribute('name'),
                ];
            }
        }

        // notifications-table может отсутствовать в host-проекте (default
        // Laravel-миграция не запущена) — сводим к 0 чтобы shell не падал.
        $unreadNotifications = \Illuminate\Support\Facades\Schema::hasTable('notifications')
            ? \Illuminate\Notifications\DatabaseNotification::query()
                ->where('notifiable_type', $user->getMorphClass())
                ->where('notifiable_id', $user->getKey())
                ->whereNull('read_at')
                ->count()
            : 0;

        return $this->success([
            'id' => $user->getKey(),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'locale' => $user->getAttribute('locale') ?? config('admin.ui.default_locale', 'ru'),
            'theme' => $user->getAttribute('theme') ?? config('admin.ui.default_theme', 'light'),
            'twoFactorEnabled' => method_exists($user, 'hasTwoFactorEnabled')
                ? $user->hasTwoFactorEnabled()
                : false,
            'impersonator' => $impersonator,
            'unread_notifications_count' => $unreadNotifications,
        ]);
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
                // SPA frontend Vue Router использует path '/r/{slug}' (см.
                // resources/ts/router/builder.ts buildResourceRoutes).
                // Префикс admin-shell (config('admin.path')) добавляется
                // SPA-router'ом автоматически.
                'url' => '/r/'.$slug,
                'routeName' => 'admin.resource.'.$slug.'.index',
                'group' => $resource::$group,
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
    public function locales(Request $request, \Dskripchenko\LaravelAdmin\Theme\LocaleResolver $resolver): JsonResponse
    {
        return $this->success([
            'available' => $resolver->available(),
            'current' => $resolver->resolve($request),
            'default' => $resolver->default(),
            'fallback' => (string) config('admin.ui.fallback_locale', 'en'),
        ]);
    }

    /**
     * Установить локаль (user.locale + cookie).
     *
     * @input string $locale
     *
     * @output object $payload
     *
     * @security Public
     *
     * @response 200 {LocaleUpdatedResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function setLocale(Request $request, \Dskripchenko\LaravelAdmin\Theme\LocaleResolver $resolver): JsonResponse
    {
        $data = $request->validate(['locale' => ['required', 'string']]);

        if (! $resolver->isAvailable($data['locale'])) {
            return $this->error([
                'errorKey' => 'unsupported_locale',
                'message' => 'Locale `'.$data['locale'].'` is not in available list',
            ], 422);
        }

        $cookie = $resolver->persist($data['locale']);
        app()->setLocale($data['locale']);

        $response = $this->success(['locale' => $data['locale']]);
        $response->withCookie($cookie);

        return $response;
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
        return $this->success(['groups' => $this->permissions->toArray()]);
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

    /**
     * Получить текущую тему + список доступных.
     *
     * @output object $payload
     *
     * @security Public
     *
     * @response 200 {ThemeStateResponse}
     */
    public function theme(Request $request, \Dskripchenko\LaravelAdmin\Theme\ThemeManager $themes): JsonResponse
    {
        return $this->success([
            'current' => $themes->current($request),
            'default' => $themes->default(),
            'available' => $themes->available(),
        ]);
    }

    /**
     * Установить тему (cookie для anon + user.theme для залогиненных).
     *
     * @input string $theme
     *
     * @output object $payload
     *
     * @security Public
     *
     * @response 200 {ThemeUpdatedResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function setTheme(Request $request, \Dskripchenko\LaravelAdmin\Theme\ThemeManager $themes): JsonResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'string'],
        ]);

        if (! $themes->isAvailable($data['theme'])) {
            return $this->error([
                'errorKey' => 'unsupported_theme',
                'message' => 'Theme `'.$data['theme'].'` is not in available list',
            ], 422);
        }

        $cookie = $themes->persist($data['theme']);

        $response = $this->success([
            'theme' => $data['theme'],
        ]);
        $response->withCookie($cookie);

        return $response;
    }
}
