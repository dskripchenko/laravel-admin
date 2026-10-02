<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Testing\Concerns\ActsAsAdmin;
use Dskripchenko\LaravelAdmin\Tests\Fixtures\Shared\TestSharedUser;

uses(ActsAsAdmin::class);

// ActsAsAdmin creates the host's own user in the shared strategy and signs
// them in on the host's guard.
it('creates a shared-strategy administrator', function (): void {
    $admin = $this->actingAsAdmin(permissions: ['admin.users.view']);

    expect($admin)->toBeInstanceOf(TestSharedUser::class)
        ->and(auth()->guard('web')->id())->toBe($admin->getKey())
        ->and($admin->hasAccess('admin.users.view'))->toBeTrue();

    $this->getJson('/api/admin/system/me')->assertOk()->assertJsonPath('payload.email', $admin->email);
});

it('gives a shared-strategy administrator a role even without permissions', function (): void {
    $admin = $this->actingAsAdmin();

    expect($admin->roles()->count())->toBe(1);
    $this->getJson('/api/admin/system/me')->assertOk();
});
