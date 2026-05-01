<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminStarter\Tests\Feature;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdminStarter\AdminStarterPlugin;
use Dskripchenko\LaravelAdminStarter\Resources\AuditLogResource;
use Dskripchenko\LaravelAdminStarter\Resources\RoleResource;
use Dskripchenko\LaravelAdminStarter\Resources\UserResource;
use Dskripchenko\LaravelAdminStarter\Tests\TestCase;

final class PluginRegistrationTest extends TestCase
{
    public function test_plugin_in_admin_plugins_config(): void
    {
        $this->assertContains(AdminStarterPlugin::class, (array) config('admin.plugins', []));
    }

    public function test_default_resources_registered(): void
    {
        /** @var Admin $admin */
        $admin = app(Admin::class);
        $resources = $admin->getResources();

        $this->assertContains(UserResource::class, $resources);
        $this->assertContains(RoleResource::class, $resources);
        $this->assertContains(AuditLogResource::class, $resources);
    }

    public function test_permissions_registered(): void
    {
        /** @var Admin $admin */
        $admin = app(Admin::class);
        $registry = $admin->getPermissionRegistry();

        foreach (
            [
                'admin.system.users.view',
                'admin.system.users.create',
                'admin.system.users.update',
                'admin.system.users.delete',
                'admin.system.roles.view',
                'admin.system.audit.view',
            ] as $key
        ) {
            $this->assertTrue($registry->knows($key), "permission $key not registered");
        }
    }
}
