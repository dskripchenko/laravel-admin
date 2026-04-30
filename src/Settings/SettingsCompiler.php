<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Settings;

use Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess;

/**
 * Компилирует SettingsRegistry в массив `controllers` для AdminApi::getMethods().
 *
 * Каждый зарегистрированный SettingsResource превращается в controller key
 * `settings_{slug}` (underscore — `.` в Laravel routing'е требует
 * дополнительного constraint'а) с тремя actions: meta/read/update.
 */
final class SettingsCompiler
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function compile(SettingsRegistry $registry): array
    {
        $controllers = [];
        foreach ($registry->all() as $slug => $class) {
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
