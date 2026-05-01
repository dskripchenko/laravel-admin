<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminStarter;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;
use Dskripchenko\LaravelAdminStarter\Resources\AuditLogResource;
use Dskripchenko\LaravelAdminStarter\Resources\RoleResource;
use Dskripchenko\LaravelAdminStarter\Resources\UserResource;

/**
 * AdminStarterPlugin — готовый набор системных Resource'ов.
 *
 * Toggles в config('admin-starter.resources') управляют тем какие из
 * Resource'ов попадут в Admin manager. По умолчанию активны users / roles /
 * audit_log; settings / translations / content_blocks / sessions — не
 * реализованы в v0.1.
 */
final class AdminStarterPlugin implements AdminPlugin
{
    public function name(): string
    {
        return 'starter';
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
        $resources = $this->resolveActiveResources();
        if ($resources !== []) {
            $admin->resources($resources);
        }

        $admin->permissions($this->buildPermissions());
    }

    /**
     * @return list<class-string>
     */
    private function resolveActiveResources(): array
    {
        /** @var array<string, mixed> $toggles */
        $toggles = (array) config('admin-starter.resources', []);

        $resources = [];
        if ((bool) ($toggles['users'] ?? true)) {
            $resources[] = UserResource::class;
        }
        if ((bool) ($toggles['roles'] ?? true)) {
            $resources[] = RoleResource::class;
        }
        if ((bool) ($toggles['audit_log'] ?? true)) {
            $resources[] = AuditLogResource::class;
        }

        return $resources;
    }

    private function buildPermissions(): ItemPermission
    {
        return ItemPermission::group('Системные')
            ->addPermission('admin.system.users.view', 'Пользователи: просмотр')
            ->addPermission('admin.system.users.create', 'Пользователи: создание')
            ->addPermission('admin.system.users.update', 'Пользователи: редактирование')
            ->addPermission('admin.system.users.delete', 'Пользователи: удаление')
            ->addPermission('admin.system.roles.view', 'Роли: просмотр')
            ->addPermission('admin.system.roles.create', 'Роли: создание')
            ->addPermission('admin.system.roles.update', 'Роли: редактирование')
            ->addPermission('admin.system.roles.delete', 'Роли: удаление')
            ->addPermission('admin.system.audit.view', 'Журнал аудита: просмотр');
    }
}
