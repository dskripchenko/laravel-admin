<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs\Tests\Feature;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdminJobs\AdminJobsPlugin;
use Dskripchenko\LaravelAdminJobs\Resources\FailedJobResource;
use Dskripchenko\LaravelAdminJobs\Resources\JobBatchResource;
use Dskripchenko\LaravelAdminJobs\Tests\TestCase;

final class PluginRegistrationTest extends TestCase
{
    public function test_plugin_class_is_added_to_admin_plugins_config(): void
    {
        $plugins = (array) config('admin.plugins', []);
        $this->assertContains(AdminJobsPlugin::class, $plugins);
    }

    public function test_plugin_registers_resources(): void
    {
        /** @var Admin $admin */
        $admin = app(Admin::class);
        $resources = $admin->getResources();

        $this->assertContains(FailedJobResource::class, $resources);
        $this->assertContains(JobBatchResource::class, $resources);
    }

    public function test_plugin_registers_permissions(): void
    {
        /** @var Admin $admin */
        $admin = app(Admin::class);
        $registry = $admin->getPermissionRegistry();

        foreach (
            [
                'admin.system.jobs.failed.view',
                'admin.system.jobs.failed.retry',
                'admin.system.jobs.failed.forget',
                'admin.system.jobs.batches.view',
                'admin.system.jobs.batches.manage',
            ] as $key
        ) {
            $this->assertTrue($registry->knows($key), "permission $key not registered");
        }
    }
}
