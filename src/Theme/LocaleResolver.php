<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Theme;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * Resolves the admin shell's locale.
 *
 * In order of precedence, taking the first value that available() allows:
 *   1. ?locale=xxx, the query parameter, for testing and previews.
 *   2. X-Admin-Locale, the header the SPA sends.
 *   3. user.locale, when someone is logged in.
 *   4. the 'admin_locale' cookie.
 *   5. Accept-Language — the browser's first acceptable locale.
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
     * Persists the locale: into user.locale, when logged in, and into a cookie.
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
     * Parses Accept-Language — 'ru-RU,ru;q=0.9,en;q=0.8' — into the first
     * match among available().
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
            // For 'ru-RU' we try the full match first, then the short 'ru'.
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
