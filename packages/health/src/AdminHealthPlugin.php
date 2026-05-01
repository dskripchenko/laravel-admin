<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;
use Dskripchenko\LaravelAdminHealth\Resources\HealthResultResource;

final class AdminHealthPlugin implements AdminPlugin
{
    public function name(): string
    {
        return 'health';
    }

    public function version(): string
    {
        return '0.1.0';
    }

    public function register(): void
    {
        // No-op.
    }

    public function boot(Admin $admin): void
    {
        $admin->resources([HealthResultResource::class]);

        $admin->permissions(
            ItemPermission::group('Системные')
                ->addPermission('admin.system.health.view', 'Health-check: просмотр')
                ->addPermission('admin.system.health.run', 'Health-check: ручной запуск'),
        );
    }
}
