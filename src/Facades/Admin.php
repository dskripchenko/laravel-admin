<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Facades;

use Dskripchenko\LaravelAdmin\Admin as AdminManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Dskripchenko\LaravelAdmin\Admin resources(array $classes)
 * @method static \Dskripchenko\LaravelAdmin\Admin widgets(array $classes)
 * @method static \Dskripchenko\LaravelAdmin\Admin plugin(string $class)
 * @method static \Dskripchenko\LaravelAdmin\Admin screen(string|array $class)
 * @method static array getResources()
 * @method static array getWidgets()
 * @method static array getPlugins()
 * @method static array getScreens()
 * @method static \Dskripchenko\LaravelAdmin\Screen\Screen|null resolveScreen(string $slug)
 * @method static string version()
 *
 * @see AdminManager
 */
final class Admin extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AdminManager::class;
    }
}
