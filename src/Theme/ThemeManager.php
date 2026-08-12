<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Theme;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * Resolves the admin shell's theme.
 *
 * In order of precedence:
 *   1. user.theme, when someone is logged in and has chosen a theme.
 *   2. the 'admin_theme' cookie, for an anonymous session before the login.
 *   3. config('admin.ui.default_theme'), the fallback.
 *
 * available() returns the themes offered by the UI's switcher, from
 * config('admin.ui.available_themes'); ['light', 'dark'] by default.
 */
final class ThemeManager
{
    public const COOKIE_NAME = 'admin_theme';

    public function current(?Request $request = null): string
    {
        $request ??= request();

        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();
        if ($user instanceof Model) {
            $stored = $user->getAttribute('theme');
            if (is_string($stored) && $this->isAvailable($stored)) {
                return $stored;
            }
        }

        $cookie = $request->cookie(self::COOKIE_NAME);
        if (is_string($cookie) && $this->isAvailable($cookie)) {
            return $cookie;
        }

        return $this->default();
    }

    public function default(): string
    {
        return (string) config('admin.ui.default_theme', 'light');
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        $configured = config('admin.ui.available_themes');
        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter($configured, 'is_string'));
        }

        return ['light', 'dark'];
    }

    public function isAvailable(string $theme): bool
    {
        return in_array($theme, $this->available(), true);
    }

    /**
     * Saves the theme: into user.theme and a cookie for whoever is logged in,
     * into a cookie alone for an anonymous visitor.
     *
     * It returns the cookie the controller is expected to attach to the
     * response.
     */
    public function persist(string $theme): \Symfony\Component\HttpFoundation\Cookie
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();
        if ($user instanceof Model) {
            $user->forceFill(['theme' => $theme])->save();
        }

        // The cookie lasts a year, so the browser remembers it past a logout.
        return Cookie::make(self::COOKIE_NAME, $theme, 60 * 24 * 365);
    }
}
