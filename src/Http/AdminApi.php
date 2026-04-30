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
                'exclude-middleware' => [Middleware\AdminAuth::class],
                'actions' => [
                    'login' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':5,1'],
                    ],
                    'logout' => ['method' => ['post']],
                    'forgotPassword' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':3,5'],
                    ],
                    'resetPassword' => ['method' => ['post']],
                    'verifyEmail' => ['method' => ['post']],
                    'resendEmailVerification' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':3,1'],
                    ],
                    'twoFactorChallenge' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':5,1'],
                    ],
                    'twoFactorRecovery' => [
                        'method' => ['post'],
                        'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class.':5,1'],
                    ],
                ],
            ],
            // 'profile' — фаза P2.4
        ];

        // Динамически добавляем по controller'у на каждый зарегистрированный Resource.
        // ResourceController — общий FQCN; per-Resource резолв идёт по ApiRequest::getApiControllerKey().
        $registry = app(ResourceRegistry::class);
        $controllers = array_merge($controllers, (new ResourceCompiler)->compile($registry));

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
