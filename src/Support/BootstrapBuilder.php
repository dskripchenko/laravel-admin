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

        $locale = $this->locales->resolve($request);

        return [
            'csrf' => csrf_token(),
            'baseUrl' => url((string) config('admin.path', 'admin')),
            'apiUrl' => url((string) config('admin.api_path', 'api/admin')),
            'locale' => $locale,
            'availableLocales' => $this->locales->available(),
            'theme' => $this->theme->current($request),
            'availableThemes' => $this->theme->available(),
            'brand' => (array) config('admin.brand', []),
            'user' => $this->serializeUser(),
            'permissions' => $this->userPermissions(),
            'manifestVersion' => $this->manifest->version($locale),
            'plugins' => $this->admin->getPlugins(),
            'unread_notifications_count' => $this->unreadNotificationsCount(),
            'config' => [
                'manifest' => ['etag' => (bool) config('admin.manifest.etag', true)],
                'bootstrap' => ['strategy' => (string) config('admin.bootstrap.strategy', 'inline')],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeUser(): ?array
    {
        $guard = (string) config('admin.auth.guard', 'admin');
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
        $guard = (string) config('admin.auth.guard', 'admin');
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

        $guard = (string) config('admin.auth.guard', 'admin');
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
