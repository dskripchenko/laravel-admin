<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;
use Dskripchenko\LaravelAdminPulse\Resources\PulseSampleResource;

final class AdminPulsePlugin implements AdminPlugin
{
    public function name(): string
    {
        return 'pulse';
    }

    public function version(): string
    {
        return '0.1.0';
    }

    public function register(): void {}

    public function boot(Admin $admin): void
    {
        $admin->resources([PulseSampleResource::class]);

        $admin->permissions(
            ItemPermission::group('Системные')
                ->addPermission('admin.system.pulse.view', 'Телеметрия: просмотр'),
        );
    }
}
