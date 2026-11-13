<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Navigation;

use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedScreen;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Screen\ScreenAccess;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Settings\SettingsRegistry;
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;

/**
 * Tells whether a page of the panel, given by its SPA path (`/r/orders/15`,
 * `/screens/reports?tab=sales`), is open to the signed-in user right now.
 *
 * The favourites and the recent pages are kept as addresses; a role changes,
 * a resource goes away — and an address saved a month ago may lead to a 403.
 * Before they are shown they pass through here, by the same rules the routes
 * themselves apply:
 *   - `/r/{slug}` — the resource's `.view`; `/r/{slug}/create` — `.create`;
 *     `/r/{slug}/{id}/edit` — `.update`; an unknown resource is closed;
 *   - `/screens/{slug}` and `/dashboard/{slug}` — the screen's own rule
 *     (every key of permission(), or one with permissionMode('any'));
 *   - `/settings/{slug}` — the settings' `.view`;
 *   - anything else inside the panel (the home page, the profile) is open.
 *
 * An address that is not a path of the panel (`https://…`, `//…`) is closed.
 */
final class UrlAccess
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly ScreenRegistry $screens,
        private readonly SettingsRegistry $settings,
    ) {}

    public function allows(string $url, string $panel = 'admin'): bool
    {
        if (! self::isPanelPath($url)) {
            return false;
        }
        $path = self::pathOf($url);
        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));
        $head = $segments[0] ?? '';
        $slug = $segments[1] ?? '';

        try {
            return match ($head) {
                'r' => $this->allowsResource($slug, array_slice($segments, 2), $panel),
                'screens', 'dashboard' => $this->allowsScreen($slug, $panel),
                'settings' => $this->allowsSettings($slug, $panel),
                default => true,
            };
        } catch (\Throwable $e) {
            // A resource or screen that cannot be built is not offered.
            report($e);

            return false;
        }
    }

    /**
     * An SPA path: starts with one slash, carries no scheme and no host.
     */
    public static function isPanelPath(string $url): bool
    {
        return str_starts_with($url, '/')
            && ! str_starts_with($url, '//')
            && ! str_contains($url, '\\')
            && strlen($url) <= 2048;
    }

    /**
     * The path without the query and the fragment.
     */
    public static function pathOf(string $url): string
    {
        $path = (string) preg_replace('/[?#].*$/s', '', $url);

        return $path === '' ? '/' : $path;
    }

    /**
     * @param  list<string>  $rest
     */
    private function allowsResource(string $slug, array $rest, string $panel): bool
    {
        if ($slug === '' || ! array_key_exists($slug, $this->resources->all($panel))) {
            return false;
        }
        $resource = $this->resources->resolve($slug, $panel);
        if ($resource === null) {
            return false;
        }
        $base = $resource::permission();
        $ability = match (true) {
            ($rest[0] ?? null) === 'create' => 'create',
            ($rest[1] ?? null) === 'edit' => 'update',
            default => 'view',
        };

        return $base === '' || PermissionCheck::allows($base.'.'.$ability);
    }

    private function allowsScreen(string $slug, string $panel): bool
    {
        $class = $this->screens->all($panel)[$slug] ?? null;
        if ($class === null || is_subclass_of($class, GeneratedScreen::class)) {
            return false;
        }
        $screen = app($class);
        if ($screen instanceof DashboardScreen) {
            return $screen->canAccess();
        }

        return $screen instanceof Screen && ScreenAccess::allows($screen);
    }

    private function allowsSettings(string $slug, string $panel): bool
    {
        if ($slug === '' || ! array_key_exists($slug, $this->settings->all($panel))) {
            return false;
        }
        $settings = $this->settings->resolve($slug, $panel);

        return $settings !== null && PermissionCheck::allows($settings::permission().'.view');
    }
}
