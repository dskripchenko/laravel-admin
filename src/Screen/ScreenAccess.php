<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Screen;

use Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;

/**
 * The one reading of a screen's permission() and permissionMode(), shared by
 * everything that decides whether a user may open the screen: the endpoints'
 * middleware (ScreenCompiler), the manifest, the menu and the dashboards.
 *
 *   - no keys        → authentication alone is enough;
 *   - mode `all`     → every key is required (the default);
 *   - mode `any`     → one key is enough.
 */
final class ScreenAccess
{
    /**
     * The screen's mode; anything but `any` reads as `all`.
     *
     * @return 'all'|'any'
     */
    public static function mode(Screen $screen): string
    {
        return $screen->permissionMode() === Screen::PERMISSION_ANY
            ? Screen::PERMISSION_ANY
            : Screen::PERMISSION_ALL;
    }

    /**
     * The screen's keys, trimmed, without the empty ones.
     *
     * @return list<string>
     */
    public static function keys(Screen $screen): array
    {
        return PermissionCheck::normalize($screen->permission());
    }

    /**
     * Whether the user — the one signed in to the current panel by default —
     * may open the screen.
     */
    public static function allows(Screen $screen, ?object $user = null): bool
    {
        $keys = self::keys($screen);

        return self::mode($screen) === Screen::PERMISSION_ANY
            ? PermissionCheck::allowsAny($keys, $user)
            : PermissionCheck::allows($keys, $user);
    }

    /**
     * The AdminAccess middleware guarding the screen's endpoints; empty when
     * the screen requires no permission.
     *
     * @return list<string>
     */
    public static function middleware(Screen $screen): array
    {
        $keys = self::keys($screen);
        if ($keys === []) {
            return [];
        }

        $middleware = AdminAccess::class.':'.implode(';', $keys);
        if (self::mode($screen) === Screen::PERMISSION_ANY && count($keys) > 1) {
            $middleware .= ','.AdminAccess::MODE_ANY;
        }

        return [$middleware];
    }
}
