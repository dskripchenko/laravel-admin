<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http;

use Dskripchenko\LaravelAdmin\Http\Schemas\AdminApiCommonSchemas;
use Dskripchenko\LaravelAdmin\Http\Schemas\AdminApiResourceSchemas;
use Dskripchenko\LaravelAdmin\Http\Schemas\AdminApiSisterPackSchemas;
use Dskripchenko\LaravelAdmin\Http\Schemas\AdminApiSystemSchemas;
use Dskripchenko\LaravelAdmin\Http\Schemas\AdminApiUiSchemas;
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
        return [
            'middleware' => [
                \Illuminate\Routing\Middleware\ThrottleRequests::class.':60,1',
            ],
            'controllers' => [
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
                // 'auth' / 'profile' — фаза P2
                // Resource/Screen/Settings controllers — динамическая регистрация (фаза P3+)
            ],
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
