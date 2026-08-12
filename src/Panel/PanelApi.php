<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Panel;

use Dskripchenko\LaravelAdmin\Http\AdminApi;

/**
 * The base API version of an additional panel.
 *
 * A host declares one subclass per panel and names it in
 * `admin.panels.{id}.api`:
 *
 *   final class ClientApi extends PanelApi {}
 *   // config: 'panels' => ['client' => ['api' => ClientApi::class, ...]]
 *
 * It inherits AdminApi's whole system surface — bootstrap, auth, profile,
 * resources, settings, screens, uploads, notifications — but the compilation
 * of the resources, settings and screens is scoped to the panel, and the auth
 * middleware works off the panel's guard, through Panels::current().
 *
 * panelId() is resolved by looking static::class up in admin.panels.*.api:
 * one subclass serves exactly one panel.
 */
abstract class PanelApi extends AdminApi
{
    /**
     * BaseApi::getPreparedMethods() merges in the methods of the PARENT API
     * classes — laravel-api's inheritance of versions — which will not do for
     * the panels: ClientApi would drag in the admin panel's resource
     * controllers. So we take our own methods alone; late static binding means
     * getMethods() compiles the resources of static::panelId().
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
     * A panel's api middleware (admin.panels.{id}.middleware.api) are
     * ADDITIONS to the shared base stack (see
     * AdminApiModule::getApiMiddleware); RunActionMiddleware applies them as
     * the methods' global middleware.
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
