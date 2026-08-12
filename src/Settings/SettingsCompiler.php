<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Settings;

use Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess;

/**
 * Compiles SettingsRegistry into the `controllers` array of
 * AdminApi::getMethods().
 *
 * Every registered SettingsResource becomes the controller key
 * `settings_{slug}` — an underscore, because a `.` in Laravel's routing needs
 * an extra constraint — with three actions: meta, read and update.
 */
final class SettingsCompiler
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function compile(SettingsRegistry $registry, ?string $panel = null): array
    {
        $controllers = [];
        foreach ($registry->all($panel) as $slug => $class) {
            $resource = $registry->resolve($slug);
            if ($resource === null) {
                continue;
            }
            $controllers['settings_'.$slug] = self::buildEntry($resource::permission());
        }

        return $controllers;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildEntry(string $base): array
    {
        $view = AdminAccess::class.':'.$base.'.view';
        $update = AdminAccess::class.':'.$base.'.update';

        return [
            'controller' => SettingsController::class,
            'actions' => [
                'meta' => ['method' => ['get'], 'middleware' => [$view]],
                'read' => ['method' => ['get'], 'middleware' => [$view]],
                'update' => ['method' => ['post'], 'middleware' => [$update]],
            ],
        ];
    }
}
