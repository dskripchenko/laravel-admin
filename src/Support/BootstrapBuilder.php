<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Theme\LocaleResolver;
use Dskripchenko\LaravelAdmin\Theme\ThemeManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Assembles the SPA's bootstrap payload.
 *
 * One source of truth for both strategies:
 *   - inline, the default, where ShellController injects the payload through a
 *     `<script>` with a CSP nonce.
 *   - xhr, where the SPA fetches /api/admin/system/bootstrap.
 *
 * The payload holds: csrf, baseUrl, apiUrl, the current locale,
 * availableLocales, the current theme, availableThemes, brand, user (or null),
 * permissions[], manifestVersion, plugins[], unread_notifications_count and
 * config — the manifest etag and the bootstrap strategy.
 */
final class BootstrapBuilder
{
    public function __construct(
        private readonly Manifest $manifest,
        private readonly Admin $admin,
        private readonly ThemeManager $theme,
        private readonly LocaleResolver $locales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?Request $request = null): array
    {
        $request ??= request();

        $panel = \Dskripchenko\LaravelAdmin\Panel\Panels::current();
        $locale = $this->locales->resolve($request);
        $user = $this->serializeUser();

        return [
            'csrf' => csrf_token(),
            'panel' => $panel->id,
            'baseUrl' => url($panel->path),
            'apiUrl' => url($panel->apiPath),
            'locale' => $locale,
            'availableLocales' => $this->locales->available(),
            'theme' => $this->theme->current($request),
            'availableThemes' => $this->theme->available(),
            'brand' => \Dskripchenko\LaravelAdmin\I18n\Localize::brand(),
            'user' => $user,
            'permissions' => $this->userPermissions(),
            // No manifest is computed for a guest: the login page does not
            // need it, and building it runs the host's resource code — the
            // options queries against data that may be unavailable, or in the
            // wrong context, before authentication.
            'manifestVersion' => $user === null ? null : $this->manifest->version($locale, $panel->id),
            'plugins' => $this->admin->getPlugins(),
            'unread_notifications_count' => $this->unreadNotificationsCount(),
            'translations' => $this->loadTranslations($locale),
            'config' => [
                'manifest' => ['etag' => (bool) config('admin.manifest.etag', true)],
                'bootstrap' => ['strategy' => (string) config('admin.bootstrap.strategy', 'inline')],
            ],
        ];
    }

    /**
     * The SPA's lang bag: a flat `{key: translation}` object from the admin
     * namespace, which the frontend's useI18nStore reaches through
     * `t('admin.dashboard.add_widget')`.
     *
     * It loads `resources/lang/{locale}/admin.php` — the `admin` namespace is
     * registered by `loadTranslationsFrom` in AdminServiceProvider — and
     * flattens the nested arrays into dot notation. A host can publish an
     * override with `php artisan vendor:publish --tag=admin-lang`.
     *
     * @return array<string, string>
     */
    private function loadTranslations(string $locale): array
    {
        $namespaces = (array) config('admin.translations.namespaces', ['admin']);
        $result = [];

        foreach ($namespaces as $ns) {
            if (! is_string($ns) || $ns === '') {
                continue;
            }
            // trans('admin::*') gives either an array of keys or the
            // fallback, so we use Lang::get('admin::admin.dashboard.title') and
            // the like.
            try {
                $bag = trans($ns.'::admin', [], $locale);
            } catch (\Throwable) {
                continue;
            }
            if (! is_array($bag)) {
                continue;
            }
            foreach (\Illuminate\Support\Arr::dot($bag) as $key => $value) {
                if (is_string($value)) {
                    // The namespace prefix plus 'admin.' gives keys like
                    // 'admin.dashboard.title', which is what the frontend's
                    // t() calls use.
                    $result["{$ns}.{$key}"] = $value;
                }
            }
        }

        // The host's JSON translations (lang/{locale}.json), keyed by the
        // source string. The frontend's tr() translates the components'
        // hard-coded strings through them; for the development locale there is
        // usually no such file, and the bag does not grow.
        try {
            $json = (array) app('translator')->getLoader()->load($locale, '*', '*');
            foreach ($json as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $result[$key] = $value;
                }
            }
        } catch (\Throwable) {
            // No JSON file for this locale, which is normal.
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeUser(): ?array
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();
        if (! $user instanceof Model) {
            return null;
        }

        return [
            'id' => $user->getKey(),
            'name' => (string) ($user->getAttribute('name') ?? ''),
            'email' => (string) ($user->getAttribute('email') ?? ''),
            'avatar' => $user->getAttribute('avatar'),
            'locale' => $user->getAttribute('locale'),
            'theme' => $user->getAttribute('theme'),
            'twoFactorEnabled' => method_exists($user, 'hasTwoFactorEnabled')
                ? $user->hasTwoFactorEnabled()
                : false,
        ];
    }

    /**
     * @return list<string>
     */
    private function userPermissions(): array
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();
        if (! $user instanceof Model) {
            return [];
        }

        return \Dskripchenko\LaravelAdmin\Permission\UserPermissions::resolve($user);
    }

    private function unreadNotificationsCount(): int
    {
        if (! Schema::hasTable('notifications')) {
            return 0;
        }

        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();
        if (! $user instanceof Model) {
            return 0;
        }

        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->whereNull('read_at')
            ->count();
    }
}
