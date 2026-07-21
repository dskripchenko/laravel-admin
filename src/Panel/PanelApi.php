<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Panel;

use Dskripchenko\LaravelAdmin\Http\AdminApi;

/**
 * База API-версии дополнительной панели (v1.8 Panels).
 *
 * Host объявляет по subclass'у на панель и указывает его в
 * `admin.panels.{id}.api`:
 *
 *   final class ClientApi extends PanelApi {}
 *   // config: 'panels' => ['client' => ['api' => ClientApi::class, ...]]
 *
 * Наследует весь системный surface AdminApi (bootstrap/auth/profile/
 * resources/settings/screens/uploads/notifications) — но компиляция
 * ресурсов/настроек/экранов скоупится панелью, а auth-мидлвары работают
 * от guard'а панели (Panels::current()).
 *
 * panelId() резолвится поиском static::class в admin.panels.*.api —
 * один subclass обслуживает ровно одну панель.
 */
abstract class PanelApi extends AdminApi
{
    /**
     * BaseApi::getPreparedMethods() мержит методы РОДИТЕЛЬСКИХ Api-классов
     * (наследование версий laravel-api) — для панелей это недопустимо:
     * ClientApi затянул бы resource-контроллеры admin-панели. Берём только
     * собственные методы (late static binding — getMethods() скомпилирует
     * ресурсы панели static::panelId()).
     *
     * @return array<string, mixed>
     */
    public static function getPreparedMethods(): array
    {
        if (! isset(static::$preparedMethods[static::class])) {
            static::$preparedMethods[static::class] = static::getNormalizedMethods();
        }

        /** @var array<string, mixed> */
        return static::$preparedMethods[static::class];
    }

    /**
     * Панельные api-middleware (admin.panels.{id}.middleware.api) — это
     * ДОПОЛНЕНИЯ к общему базовому стеку (см. AdminApiModule::getApiMiddleware);
     * применяются через RunActionMiddleware как global-middleware методов.
     *
     * @return array<string, mixed>
     */
    public static function getMethods(): array
    {
        $methods = parent::getMethods();

        $panel = Panels::registry()->get(static::panelId());
        $extra = $panel?->apiMiddleware() ?? [];
        if ($extra !== []) {
            $methods['middleware'] = array_merge((array) ($methods['middleware'] ?? []), $extra);
        }

        return $methods;
    }

    public static function panelId(): string
    {
        /** @var array<array-key, mixed> $panels */
        $panels = (array) config('admin.panels', []);
        foreach ($panels as $id => $config) {
            if (is_array($config) && ($config['api'] ?? null) === static::class) {
                return (string) $id;
            }
        }

        throw new \RuntimeException(
            static::class.' не привязан ни к одной панели — укажите его в admin.panels.{id}.api',
        );
    }
}
