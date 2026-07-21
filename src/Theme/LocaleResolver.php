<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Theme;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * Резолвер локали для admin-shell'а.
 *
 * Приоритезация (первое подходящее значение из available()):
 *   1. ?locale=xxx — query-param (для testing/preview).
 *   2. X-Admin-Locale — header (для SPA).
 *   3. user.locale — если пользователь залогинен.
 *   4. cookie 'admin_locale'.
 *   5. Accept-Language — первая принимаемая локаль из браузера.
 *   6. config('admin.ui.default_locale').
 */
final class LocaleResolver
{
    public const COOKIE_NAME = 'admin_locale';

    public const HEADER = 'X-Admin-Locale';

    public function resolve(?Request $request = null): string
    {
        $request ??= request();
        $available = $this->available();

        $candidates = [
            (string) $request->query('locale', ''),
            (string) $request->header(self::HEADER, ''),
            $this->userLocale(),
            (string) $request->cookie(self::COOKIE_NAME, ''),
            $this->fromAcceptLanguage((string) $request->header('Accept-Language', '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        return $this->default();
    }

    public function default(): string
    {
        $configured = (string) config('admin.ui.default_locale', 'ru');
        $available = $this->available();

        return in_array($configured, $available, true) ? $configured : ($available[0] ?? 'en');
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        $configured = config('admin.ui.available_locales');
        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter($configured, 'is_string'));
        }

        return ['ru', 'en'];
    }

    public function isAvailable(string $locale): bool
    {
        return in_array($locale, $this->available(), true);
    }

    /**
     * Persist locale: user.locale (если залогинен) + cookie.
     */
    public function persist(string $locale): \Symfony\Component\HttpFoundation\Cookie
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();
        if ($user instanceof Model) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return Cookie::make(self::COOKIE_NAME, $locale, 60 * 24 * 365);
    }

    private function userLocale(): string
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();
        if (! $user instanceof Model) {
            return '';
        }
        $stored = $user->getAttribute('locale');

        return is_string($stored) ? $stored : '';
    }

    /**
     * Парсит Accept-Language: 'ru-RU,ru;q=0.9,en;q=0.8' → первый match
     * из available().
     */
    private function fromAcceptLanguage(string $header): string
    {
        if ($header === '') {
            return '';
        }

        $available = $this->available();
        $parts = explode(',', $header);

        foreach ($parts as $part) {
            $piece = trim(explode(';', $part)[0]);
            if ($piece === '') {
                continue;
            }
            // 'ru-RU' → пробуем full-match, потом short-form 'ru'.
            if (in_array($piece, $available, true)) {
                return $piece;
            }
            $short = strtolower(explode('-', $piece)[0]);
            if (in_array($short, $available, true)) {
                return $short;
            }
        }

        return '';
    }
}
