<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Permission\Concerns\HasAdminAccess;
use Dskripchenko\LaravelAdmin\Theme\LocaleResolver;
use Dskripchenko\LaravelAdmin\Theme\ThemeManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Сборщик payload'а bootstrap'а SPA.
 *
 * Один источник истины для двух стратегий:
 *   - inline (default) — ShellController инжектит payload через `<script>`
 *     с CSP-nonce.
 *   - xhr — SPA fetch'ит /api/admin/system/bootstrap.
 *
 * Структура payload'а: csrf, baseUrl, apiUrl, locale (current), availableLocales,
 * theme (current), availableThemes, brand, user (или null), permissions[],
 * manifestVersion, plugins[], unread_notifications_count, config (manifest etag,
 * bootstrap strategy).
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
            'brand' => (array) config('admin.brand', []),
            'user' => $user,
            'permissions' => $this->userPermissions(),
            // Гостю манифест не считаем: login-странице он не нужен, а его
            // сборка выполняет resource-код хоста (options-запросы к данным,
            // которые до аутентификации могут быть недоступны/не тот контекст).
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
     * Lang-bag для SPA: flat-объект `{key: translation}` из admin namespace.
     * Frontend useI18nStore использует через `t('admin.dashboard.add_widget')`.
     *
     * Загружает `resources/lang/{locale}/admin.php` (через `loadTranslationsFrom`
     * в AdminServiceProvider зарегистрирован namespace `admin`) и сплющивает
     * вложенные массивы в dot.notation. Host может публиковать override через
     * `php artisan vendor:publish --tag=admin-lang`.
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
            // trans('admin::*') → массив ключей или fallback. Используем
            // Lang::get('admin::admin.dashboard.title') и т.п.
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
                    // Префикс с namespace + 'admin.' даёт ключи вида
                    // 'admin.dashboard.title', что соответствует frontend t()-call'ам.
                    $result["{$ns}.{$key}"] = $value;
                }
            }
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
        if (! in_array(HasAdminAccess::class, class_uses_recursive($user::class), true)) {
            return [];
        }
        if (! method_exists($user, 'getAllPermissions')) {
            return [];
        }

        return array_values((array) $user->getAllPermissions());
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
