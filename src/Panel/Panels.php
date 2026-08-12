<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Panel;

/**
 * Static access to the panel context.
 *
 * `Panels::currentGuard()` replaces reading `config('admin.auth.guard')`
 * directly: for a single-panel host it returns the same value, and for a
 * multi-panel one the guard of the current request's panel.
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
