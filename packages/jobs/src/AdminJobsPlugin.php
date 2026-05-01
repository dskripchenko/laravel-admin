<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;
use Dskripchenko\LaravelAdminJobs\Resources\FailedJobResource;
use Dskripchenko\LaravelAdminJobs\Resources\JobBatchResource;

/**
 * AdminPlugin для регистрации failed-jobs / batches Resource'ов и
 * их permission-групп.
 *
 * Подключается host-проектом через `Admin::plugins([AdminJobsPlugin::class])`
 * либо ставится в `config/admin.php`'s `plugins[]`.
 */
final class AdminJobsPlugin implements AdminPlugin
{
    public function name(): string
    {
        return 'jobs';
    }

    public function version(): string
    {
        return '0.1.0';
    }

    public function register(): void
    {
        // No-op — service-bindings регистрируются service provider'ом.
    }

    public function boot(Admin $admin): void
    {
        $admin->resources([
            FailedJobResource::class,
            JobBatchResource::class,
        ]);

        $admin->permissions(
            ItemPermission::group('Системные')
                ->addPermission('admin.system.jobs.failed.view', 'Failed jobs: просмотр')
                ->addPermission('admin.system.jobs.failed.retry', 'Failed jobs: retry')
                ->addPermission('admin.system.jobs.failed.forget', 'Failed jobs: forget')
                ->addPermission('admin.system.jobs.batches.view', 'Batches: просмотр')
                ->addPermission('admin.system.jobs.batches.manage', 'Batches: cancel/retry'),
        );
    }
}
