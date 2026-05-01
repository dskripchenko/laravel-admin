<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Tests\Feature;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdminHealth\AdminHealthPlugin;
use Dskripchenko\LaravelAdminHealth\Resources\HealthResultResource;
use Dskripchenko\LaravelAdminHealth\Tests\TestCase;

final class PluginRegistrationTest extends TestCase
{
    public function test_plugin_in_admin_plugins_config(): void
    {
        $plugins = (array) config('admin.plugins', []);
        $this->assertContains(AdminHealthPlugin::class, $plugins);
    }

    public function test_resource_registered(): void
    {
        /** @var Admin $admin */
        $admin = app(Admin::class);
        $this->assertContains(HealthResultResource::class, $admin->getResources());
    }

    public function test_permissions_registered(): void
    {
        /** @var Admin $admin */
        $admin = app(Admin::class);
        $registry = $admin->getPermissionRegistry();
        $this->assertTrue($registry->knows('admin.system.health.view'));
        $this->assertTrue($registry->knows('admin.system.health.run'));
    }

    public function test_run_command_exists(): void
    {
        $output = $this->artisan('admin:health:run');
        $output->assertSuccessful();
    }

    public function test_cleanup_command_exists(): void
    {
        $output = $this->artisan('admin:health:cleanup');
        $output->assertSuccessful();
    }
}
