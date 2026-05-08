<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Screen;

use Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedScreen;
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;

/**
 * Компилирует ScreenRegistry в массив `controllers` для AdminApi::getMethods().
 *
 * Каждый зарегистрированный Screen → controller-entry с двумя actions:
 *   - state (GET)        — отдаёт compile() (state + layout + commandBar + meta)
 *   - runMethod (POST)   — диспатч-точка для command-методов Screen
 *
 * Permission gate — через AdminAccess middleware, если у Screen задан
 * `permission()` (string) либо list<string> — собираются через `;` (AND).
 *
 * Из generic-pipeline'а исключаются:
 *   - GeneratedScreen subclasses — обслуживаются ResourceController.
 *   - DashboardScreen subclasses — обслуживаются DashboardController.
 *
 * Controller key = slug Screen'а. URL: `/api/admin/{slug}/{action}`.
 */
final class ScreenCompiler
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function compile(ScreenRegistry $registry): array
    {
        $controllers = [];
        foreach ($registry->all() as $slug => $class) {
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
