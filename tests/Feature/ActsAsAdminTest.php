<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Testing\Concerns\ActsAsAdmin;

uses(ActsAsAdmin::class);

it('actingAsAdmin creates user and logs them in', function (): void {
    $admin = $this->actingAsAdmin();

    expect($admin)->toBeInstanceOf(AdminUser::class);
    expect($admin->email)->toContain('@example.com');
    expect($this->app['auth']->guard('admin')->id())->toBe($admin->id);
});

it('actingAsAdmin allows attribute override', function (): void {
    $admin = $this->actingAsAdmin([
        'name' => 'Custom Name',
        'email' => 'custom@example.com',
    ]);

    expect($admin->name)->toBe('Custom Name');
    expect($admin->email)->toBe('custom@example.com');
});

it('actingAsAdmin with permissions creates role and assigns', function (): void {
    $admin = $this->actingAsAdmin(permissions: ['admin.users.view', 'admin.users.update']);

    expect($admin->hasAccess('admin.users.view'))->toBeTrue();
    expect($admin->hasAccess('admin.users.update'))->toBeTrue();
    expect($admin->hasAccess('admin.unknown'))->toBeFalse();
});

it('actingAsAdmin without permissions creates no role', function (): void {
    $admin = $this->actingAsAdmin();
    expect($admin->roles()->count())->toBe(0);
    expect(Role::count())->toBe(0);
});

it('actingAsSuperAdmin gives wildcard permission', function (): void {
    $admin = $this->actingAsSuperAdmin();

    expect($admin->hasAccess('admin.anything'))->toBeTrue();
    expect($admin->hasAccess('admin.users.delete'))->toBeTrue();
    expect($admin->hasAccess('admin.system.settings'))->toBeTrue();
});

it('multiple actingAsAdmin calls create distinct users', function (): void {
    $a = $this->actingAsAdmin();
    $b = $this->actingAsAdmin();

    expect($a->id)->not->toBe($b->id);
    expect($a->email)->not->toBe($b->email);
});
