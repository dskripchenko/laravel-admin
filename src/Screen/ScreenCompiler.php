<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Screen;

use Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedScreen;
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;

/**
 * Compiles ScreenRegistry into the `controllers` array of
 * AdminApi::getMethods().
 *
 * Every registered screen becomes a controller entry with two actions:
 *   - state (GET)      — returns compile(): the state, layout, command bar and
 *                        meta
 *   - runMethod (POST) — the dispatch point of the screen's command methods
 *
 * The permission gate is the AdminAccess middleware, applied when the screen
 * declares a `permission()` — a string, or a list joined with `;`, which means
 * AND.
 *
 * Two kinds are left out of this generic pipeline:
 *   - the GeneratedScreen subclasses, served by ResourceController;
 *   - the DashboardScreen subclasses, served by DashboardController.
 *
 * The controller key is the screen's slug, and the URL
 * `/api/admin/{slug}/{action}`.
 */
final class ScreenCompiler
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function compile(ScreenRegistry $registry, ?string $panel = null): array
    {
        $controllers = [];
        foreach ($registry->all($panel) as $slug => $class) {
            if ($this->shouldSkip($class)) {
                continue;
            }

            /** @var Screen $instance */
            $instance = app($class);
            $middleware = self::buildPermissionMiddleware($instance->permission());

            $controllers[$slug] = [
                'controller' => ScreenController::class,
                'actions' => [
                    'state' => [
                        'method' => ['get'],
                        'middleware' => $middleware,
                    ],
                    'runMethod' => [
                        'method' => ['post'],
                        'middleware' => $middleware,
                    ],
                ],
            ];
        }

        return $controllers;
    }

    /**
     * @param  class-string<Screen>  $class
     */
    private function shouldSkip(string $class): bool
    {
        if (is_subclass_of($class, GeneratedScreen::class)) {
            return true;
        }
        if (is_subclass_of($class, DashboardScreen::class)) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<string>|string|null  $permission
     * @return list<string>
     */
    private static function buildPermissionMiddleware(array|string|null $permission): array
    {
        if ($permission === null) {
            return [];
        }
        $perms = is_string($permission) ? [$permission] : $permission;
        $perms = array_values(array_filter(array_map('trim', $perms)));
        if ($perms === []) {
            return [];
        }

        return [AdminAccess::class.':'.implode(';', $perms)];
    }
}
