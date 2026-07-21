<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Panel;

/**
 * Статический доступ к панельному контексту (v1.8 Panels).
 *
 * `Panels::currentGuard()` — замена прямых чтений `config('admin.auth.guard')`:
 * для однопанельных хостов возвращает то же значение (BC), в многопанельных —
 * guard панели текущего запроса.
 */
final class Panels
{
    public static function registry(): PanelRegistry
    {
        return app(PanelRegistry::class);
    }

    public static function current(): Panel
    {
        return self::registry()->current();
    }

    public static function currentGuard(): string
    {
        return self::current()->guard;
    }

    public static function currentProvider(): string
    {
        return self::current()->authProvider();
    }

    public static function currentPasswordBroker(): string
    {
        return self::current()->passwordBroker();
    }

    public static function currentAuthModel(): string
    {
        return self::current()->authModel();
    }
}
