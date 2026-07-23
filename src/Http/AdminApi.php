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
 * Admin API — описание всех endpoint'ов и shared response templates.
 *
 * Templates объявляются через `getOpenApiTemplates()` и подхватываются
 * laravel-api при генерации OpenAPI. Метод собирает все темплейты из
 * traits по разделам admin (System / Resources / UI / Sister-packs / Common).
 *
 * См. также docs/api/registration.md и docs/api/schemas.md.
 */
class AdminApi extends BaseApi
{
    use AdminApiCommonSchemas;
    use AdminApiResourceSchemas;
    use AdminApiSisterPackSchemas;
    use AdminApiSystemSchemas;
    use AdminApiUiSchemas;

    /**
     * Override BaseApi cache. AdminApi::getMethods() читает ResourceRegistry
     * динамически — при добавлении/удалении Resource нужна инвалидация
     * через `clearCache()` (особенно важно в тестах между сценариями).
     *
     * @var array<string, mixed>
     */
    protected static $preparedMethods = [];

    /**
     * Сбрасывает laravel-api кеш `getPreparedMethods` для AdminApi.
     * Используется в тестах после Resources::add/clear.
     */
    public static function clearCache(): void
    {
        static::$preparedMethods = [];
    }

    /**
     * Панель, которую обслуживает эта API-версия (v1.8 Panels).
     * Subclasses для дополнительных панелей переопределяют (см. PanelApi).
     */
    public static function panelId(): string
    {
        return 'admin';
    }

    /**
     * Включить named-templates для @response.
     *
     * Тип не указан намеренно — родитель (OpenApiTrait в BaseApi) объявляет
     * `public static $useResponseTemplates = false;` без типа. PHP требует
     * совпадения сигнатур при наследовании.
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
                    'theme' => ['method' => ['get'], 'exclude-middleware' => [Middleware\AdminAuth::class]],
                    'setTheme' => ['method' => ['post'], 'exclude-middleware' => [Middleware\AdminAuth::class]],
                ],
            ],
            'auth' => [
                'controller' => \Dskripchenko\LaravelAdmin\Auth\Controllers\AuthController::class,
                'actions' => [
                    'login' => [
                        'method' => ['post'],
                        // Третий параметр — prefix: у безымянных throttle'ов ключ
                        // = sha1(domain|ip); без префикса счётчик делился бы с
                        // глобальным ':60,1' (и любыми другими throttle'ами роута)
                        // и сгорал от обычных API-запросов.
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

        // Динамически добавляем по controller'у на каждый зарегистрированный Resource.
        // ResourceController — общий FQCN; per-Resource резолв идёт по ApiRequest::getApiControllerKey().
        $registry = app(ResourceRegistry::class);
        $controllers = array_merge($controllers, (new ResourceCompiler)->compile($registry, static::panelId()));

        // Settings: каждый SettingsResource = отдельный controller key 'settings.{slug}'.
        $settingsRegistry = app(SettingsRegistry::class);
        $controllers = array_merge($controllers, (new SettingsCompiler)->compile($settingsRegistry, static::panelId()));

        // Screens: произвольные Screen-классы (custom forms / pages / dashboards вне CRUD).
        // GeneratedScreen и DashboardScreen subclasses исключаются (у них свои controllers).
        $screenRegistry = app(ScreenRegistry::class);
        $controllers = array_merge($controllers, (new ScreenCompiler)->compile($screenRegistry, static::panelId()));

        return [
            'middleware' => [
                // Глобальный лимит admin-API per-user. 60/мин мало для SPA
                // (навигация = пачка XHR, дашборд-поллинг, e2e): дефолт 240.
                \Illuminate\Routing\Middleware\ThrottleRequests::class.':'
                    .(string) config('admin.api.throttle', '240,1'),
            ],
            'controllers' => $controllers,
        ];
    }

    /**
     * Объединённый набор response-templates со всех traits.
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
