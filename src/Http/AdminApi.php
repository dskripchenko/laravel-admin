<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http;

use Dskripchenko\LaravelAdmin\Http\Schemas\AdminApiCommonSchemas;
use Dskripchenko\LaravelAdmin\Http\Schemas\AdminApiResourceSchemas;
use Dskripchenko\LaravelAdmin\Http\Schemas\AdminApiSisterPackSchemas;
use Dskripchenko\LaravelAdmin\Http\Schemas\AdminApiSystemSchemas;
use Dskripchenko\LaravelAdmin\Http\Schemas\AdminApiUiSchemas;
use Dskripchenko\LaravelAdmin\Resource\ResourceCompiler;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenCompiler;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Settings\SettingsCompiler;
use Dskripchenko\LaravelAdmin\Settings\SettingsRegistry;
use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * The admin API: every endpoint and the shared response templates.
 *
 * The templates are declared through `getOpenApiTemplates()` and picked up by
 * laravel-api when it generates the OpenAPI document. That method collects the
 * templates from the traits of each admin area: System, Resources, UI,
 * sister packs and Common.
 *
 * See also docs/api/registration.md and docs/api/schemas.md.
 */
class AdminApi extends BaseApi
{
    use AdminApiCommonSchemas;
    use AdminApiResourceSchemas;
    use AdminApiSisterPackSchemas;
    use AdminApiSystemSchemas;
    use AdminApiUiSchemas;

    /**
     * Overrides BaseApi's cache. AdminApi::getMethods() reads ResourceRegistry
     * dynamically, so adding or removing a resource calls for an invalidation
     * through `clearCache()` — which matters most in tests, between scenarios.
     *
     * @var array<string, mixed>
     */
    protected static $preparedMethods = [];

    /**
     * Clears laravel-api's `getPreparedMethods` cache for AdminApi. Used in
     * tests after Resources::add or Resources::clear.
     */
    public static function clearCache(): void
    {
        static::$preparedMethods = [];
    }

    /**
     * The panel this API version serves. Subclasses for additional panels
     * override it — see PanelApi.
     */
    public static function panelId(): string
    {
        return 'admin';
    }

    /**
     * Turns the named templates for @response on.
     *
     * The type is deliberately omitted: the parent (OpenApiTrait in BaseApi)
     * declares `public static $useResponseTemplates = false;` without one, and
     * PHP requires the signatures to match on inheritance.
     *
     * @var bool
     */
    public static $useResponseTemplates = true;

    /**
     * @return array<string, mixed>
     */
    public static function getMethods(): array
    {
        $controllers = [
            'system' => [
                'controller' => Controllers\SystemController::class,
                'actions' => [
                    'bootstrap' => ['method' => ['get'], 'exclude-middleware' => [Middleware\AdminAuth::class]],
                    'manifest' => ['method' => ['get']],
                    'me' => ['method' => ['get']],
                    'menu' => ['method' => ['get']],
                    'search' => ['method' => ['get']],
                    'locales' => ['method' => ['get'], 'exclude-middleware' => [Middleware\AdminAuth::class]],
                    'setLocale' => ['method' => ['post'], 'exclude-middleware' => [Middleware\AdminAuth::class]],
                    'permissions' => ['method' => ['get']],
                    'plugins' => ['method' => ['get']],
                    'status' => ['method' => ['get']],
                    'theme' => ['method' => ['get'], 'exclude-middleware' => [Middleware\AdminAuth::class]],
                    'setTheme' => ['method' => ['post'], 'exclude-middleware' => [Middleware\AdminAuth::class]],
                ],
            ],
            'auth' => [
                'controller' => \Dskripchenko\LaravelAdmin\Auth\Controllers\AuthController::class,
                'actions' => [
                    'login' => [
                        'method' => ['post'],
                        // The third parameter is the prefix: an unnamed
                        // throttle keys on sha1(domain|ip), so without one the
                        // counter would be shared with the global ':60,1' —
                        // and with any other throttle on the route — and would
                        // burn through on ordinary API requests.
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':'.(string) config('admin.auth.login_throttle', '5,1').',auth-'.static::panelId()],
                        'exclude-middleware' => [Middleware\AdminAuth::class],
                    ],
                    'logout' => ['method' => ['post']],
                    'forgotPassword' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':3,5,forgot-'.static::panelId()],
                        'exclude-middleware' => [Middleware\AdminAuth::class],
                    ],
                    'resetPassword' => [
                        'method' => ['post'],
                        'exclude-middleware' => [Middleware\AdminAuth::class],
                    ],
                    'verifyEmail' => [
                        'method' => ['post'],
                        'exclude-middleware' => [Middleware\AdminAuth::class],
                    ],
                    'resendEmailVerification' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':3,1,verify-'.static::panelId()],
                        'exclude-middleware' => [Middleware\AdminAuth::class],
                    ],
                    'twoFactorChallenge' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':'.(string) config('admin.auth.login_throttle', '5,1').',auth-'.static::panelId()],
                        'exclude-middleware' => [Middleware\AdminAuth::class],
                    ],
                    'twoFactorRecovery' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':'.(string) config('admin.auth.login_throttle', '5,1').',auth-'.static::panelId()],
                        'exclude-middleware' => [Middleware\AdminAuth::class],
                    ],
                    'startImpersonation' => ['method' => ['post']],
                    'stopImpersonation' => ['method' => ['post']],
                ],
            ],
            'profile' => [
                'controller' => \Dskripchenko\LaravelAdmin\Profile\Controllers\ProfileController::class,
                'actions' => [
                    'show' => ['method' => ['get']],
                    'update' => ['method' => ['post']],
                    'changePassword' => ['method' => ['post']],
                    'twoFactorStatus' => ['method' => ['get']],
                    'twoFactorEnable' => ['method' => ['post']],
                    'twoFactorConfirm' => ['method' => ['post']],
                    'twoFactorDisable' => ['method' => ['post']],
                    'twoFactorRegenerateCodes' => ['method' => ['post']],
                    'tokensList' => ['method' => ['get']],
                    'tokenCreate' => ['method' => ['post']],
                    'tokenRevoke' => ['method' => ['post']],
                ],
            ],
            'dashboard' => [
                'controller' => \Dskripchenko\LaravelAdmin\Widget\DashboardController::class,
                'actions' => [
                    'get' => ['method' => ['get']],
                    'save' => ['method' => ['post']],
                    'savePeriod' => ['method' => ['post']],
                    'reset' => ['method' => ['post']],
                    'widgets' => ['method' => ['get']],
                ],
            ],
            'audit' => [
                'controller' => \Dskripchenko\LaravelAdmin\Audit\AuditController::class,
                'actions' => [
                    'list' => ['method' => ['get']],
                    'timeline' => ['method' => ['get']],
                ],
            ],
            'delayed' => [
                'controller' => \Dskripchenko\LaravelAdmin\DelayedProcess\DelayedProcessController::class,
                'actions' => [
                    'run' => ['method' => ['post']],
                    'status' => ['method' => ['get']],
                ],
            ],
            'import' => [
                'controller' => \Dskripchenko\LaravelAdmin\Import\ImportController::class,
                'actions' => [
                    'upload' => ['method' => ['post']],
                    'preview' => ['method' => ['post']],
                    'start' => ['method' => ['post']],
                    'status' => ['method' => ['get']],
                ],
            ],
            'uploads' => [
                'controller' => \Dskripchenko\LaravelAdmin\Uploads\UploadController::class,
                'actions' => [
                    'upload' => ['method' => ['post']],
                    'image' => ['method' => ['post']],
                    'serve' => ['method' => ['get']],
                ],
            ],
            'notifications' => [
                'controller' => \Dskripchenko\LaravelAdmin\Notifications\NotificationController::class,
                'actions' => [
                    'list' => ['method' => ['get']],
                    'unread' => ['method' => ['get']],
                    'markAsRead' => ['method' => ['post']],
                    'markAllAsRead' => ['method' => ['post']],
                    'destroy' => ['method' => ['post']],
                ],
            ],
        ];

        // A controller is added dynamically for every registered resource.
        // ResourceController is the shared FQCN; the per-resource resolution
        // happens through ApiRequest::getApiControllerKey().
        $registry = app(ResourceRegistry::class);
        $controllers = array_merge($controllers, (new ResourceCompiler)->compile($registry, static::panelId()));

        // Settings: every SettingsResource gets its own controller key, 'settings.{slug}'.
        $settingsRegistry = app(SettingsRegistry::class);
        $controllers = array_merge($controllers, (new SettingsCompiler)->compile($settingsRegistry, static::panelId()));

        // Screens: arbitrary screen classes — custom forms, pages and
        // dashboards outside of CRUD. The GeneratedScreen and DashboardScreen
        // subclasses are excluded, having controllers of their own.
        $screenRegistry = app(ScreenRegistry::class);
        $controllers = array_merge($controllers, (new ScreenCompiler)->compile($screenRegistry, static::panelId()));

        return [
            'middleware' => [
                // The admin API's global per-user limit. 60 a minute is too
                // little for an SPA — one navigation is a handful of XHRs,
                // plus dashboard polling and e2e — so the default is 240.
                \Illuminate\Routing\Middleware\ThrottleRequests::class.':'
                    .(string) config('admin.api.throttle', '240,1'),
            ],
            'controllers' => $controllers,
        ];
    }

    /**
     * The combined set of response templates from every trait.
     *
     * @return array<string, array<string, string>>
     */
    public static function getOpenApiTemplates(): array
    {
        return array_merge(
            self::provideCommonSchemas(),
            self::provideSystemSchemas(),
            self::provideResourceSchemas(),
            self::provideUiSchemas(),
            self::provideSisterPackSchemas(),
        );
    }
}
