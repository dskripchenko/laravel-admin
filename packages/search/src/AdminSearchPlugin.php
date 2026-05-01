<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminSearch;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;

/**
 * AdminSearchPlugin — placeholder, поскольку search-pack не вводит свои
 * Resource'ы / Permissions. Регистрация плагина в config'е нужна в основном
 * для discovery (Admin::getPlugins() показывает что pack установлен).
 */
final class AdminSearchPlugin implements AdminPlugin
{
    public function name(): string
    {
        return 'search';
    }

    public function version(): string
    {
        return '0.1.0';
    }

    public function register(): void {}

    public function boot(Admin $admin): void
    {
        // No resources / no permissions — search использует существующие
        // <resource>.view permissions через SearchService.
    }
}
