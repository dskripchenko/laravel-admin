<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Theme;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * Resolver темы оформления для admin-shell'а.
 *
 * Приоритезация:
 *   1. user.theme — если пользователь залогинен и установил тему.
 *   2. cookie 'admin_theme' — для anonymous-сессии до логина.
 *   3. config('admin.ui.default_theme') — fallback.
 *
 * available() — список доступных тем для UI-switcher'а из
 * config('admin.ui.available_themes'). Default: ['light', 'dark'].
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
     * Сохранить тему: для залогиненных — в user.theme + cookie; для анонимов
     * — только cookie.
     *
     * Возвращает cookie-instance, который контроллер должен прикрепить к
     * response.
     */
    public function persist(string $theme): \Symfony\Component\HttpFoundation\Cookie
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();
        if ($user instanceof Model) {
            $user->forceFill(['theme' => $theme])->save();
        }

        // Cookie на 1 год — браузер будет помнить даже после logout.
        return Cookie::make(self::COOKIE_NAME, $theme, 60 * 24 * 365);
    }
}
