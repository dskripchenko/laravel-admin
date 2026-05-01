<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminStarter\Tests\Unit;

use Dskripchenko\LaravelAdminStarter\AdminStarterPlugin;
use Dskripchenko\LaravelAdminStarter\Resources\AuditLogResource;
use Dskripchenko\LaravelAdminStarter\Resources\RoleResource;
use Dskripchenko\LaravelAdminStarter\Resources\UserResource;
use Dskripchenko\LaravelAdminStarter\Tests\TestCase;
use ReflectionMethod;

final class PluginToggleTest extends TestCase
{
    private function callResolve(): array
    {
        $plugin = new AdminStarterPlugin;
        $method = new ReflectionMethod($plugin, 'resolveActiveResources');

        return $method->invoke($plugin);
    }

    public function test_default_includes_all_three_resources(): void
    {
        config([
            'admin-starter.resources.users' => true,
            'admin-starter.resources.roles' => true,
            'admin-starter.resources.audit_log' => true,
        ]);

        $resources = $this->callResolve();

        $this->assertContains(UserResource::class, $resources);
        $this->assertContains(RoleResource::class, $resources);
        $this->assertContains(AuditLogResource::class, $resources);
    }

    public function test_users_can_be_disabled(): void
    {
        config(['admin-starter.resources.users' => false]);

        $resources = $this->callResolve();

        $this->assertNotContains(UserResource::class, $resources);
    }

    public function test_audit_can_be_disabled(): void
    {
        config(['admin-starter.resources.audit_log' => false]);

        $resources = $this->callResolve();

        $this->assertNotContains(AuditLogResource::class, $resources);
    }
}
