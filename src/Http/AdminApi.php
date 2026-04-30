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
                    'bootstrap' => ['method' => ['get']],
                    'manifest' => ['method' => ['get']],
                    'me' => ['method' => ['get']],
                    'menu' => ['method' => ['get']],
                    'locales' => ['method' => ['get']],
                    'permissions' => ['method' => ['get']],
                    'plugins' => ['method' => ['get']],
                ],
            ],
            'auth' => [
                'controller' => \Dskripchenko\LaravelAdmin\Auth\Controllers\AuthController::class,
                'actions' => [
                    'login' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':5,1'],
                        'exclude-middleware' => [Middleware\AdminAuth::class],
                    ],
                    'logout' => ['method' => ['post']],
                    'forgotPassword' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':3,5'],
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
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':3,1'],
                        'exclude-middleware' => [Middleware\AdminAuth::class],
                    ],
                    'twoFactorChallenge' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':5,1'],
                        'exclude-middleware' => [Middleware\AdminAuth::class],
                    ],
                    'twoFactorRecovery' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':5,1'],
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
                ],
            ],
            'dashboard' => [
                'controller' => \Dskripchenko\LaravelAdmin\Widget\DashboardController::class,
                'actions' => [
                    'get' => ['method' => ['get']],
                    'save' => ['method' => ['post']],
                    'reset' => ['method' => ['post']],
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
        $controllers = array_merge($controllers, (new ResourceCompiler)->compile($registry));

        // Settings: каждый SettingsResource = отдельный controller key 'settings.{slug}'.
        $settingsRegistry = app(SettingsRegistry::class);
        $controllers = array_merge($controllers, (new SettingsCompiler)->compile($settingsRegistry));

        return [
            'middleware' => [
                \Illuminate\Routing\Middleware\ThrottleRequests::class.':60,1',
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
