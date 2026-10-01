<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Auth\TwoFactor\RecoveryCodes;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Support\BootstrapBuilder;
use Dskripchenko\LaravelAdmin\Tests\Fixtures\Shared\TestSharedStaffUser;
use Dskripchenko\LaravelAdmin\Tests\Fixtures\Shared\TestSharedUser;
use Illuminate\Support\Facades\Schema;

// The shared strategy end to end: the site's users table, the site's `web`
// guard, and the admin on top. A site user is not an administrator until
// they hold an admin role.

function sharedUser(string $email = 'alice@example.com', bool $admin = true): TestSharedUser
{
    $user = TestSharedUser::create(['name' => 'Alice', 'email' => $email, 'password' => 'secret-pass']);
    if ($admin) {
        $user->assignRole(Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'permissions' => ['*'], 'is_system' => true],
        ));
    }

    return $user;
}

it('adds the admin columns to the host users table, once', function (): void {
    foreach (['locale', 'theme', 'is_active', 'last_login_at', 'last_login_ip', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'] as $column) {
        expect(Schema::hasColumn('users', $column))->toBeTrue();
    }

    // Running it again over the same table is a no-op, not an error.
    $this->runSharedMigration('up');

    $this->runSharedMigration('down');
    expect(Schema::hasColumn('users', 'locale'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'name'))->toBeTrue();
});

it('logs an administrator in through the host guard', function (): void {
    $user = sharedUser();

    $this->postJson('/api/admin/auth/login', ['email' => 'alice@example.com', 'password' => 'secret-pass'])
        ->assertOk()
        ->assertJsonPath('payload.user.id', $user->id)
        ->assertJsonPath('payload.permissions', ['*']);

    expect(auth()->guard('web')->id())->toBe($user->id);
    expect($user->fresh()->last_login_at)->not->toBeNull();

    $this->getJson('/api/admin/system/me')->assertOk()->assertJsonPath('payload.email', 'alice@example.com');
});

it('refuses a site user without an admin role at the login', function (): void {
    sharedUser('bob@example.com', admin: false);

    $this->postJson('/api/admin/auth/login', ['email' => 'bob@example.com', 'password' => 'secret-pass'])
        ->assertForbidden()
        ->assertJsonPath('payload.errorKey', 'forbidden');

    expect(auth()->guard('web')->check())->toBeFalse();
});

it('does not let a site session into the admin API, and keeps that session', function (): void {
    $bob = sharedUser('bob@example.com', admin: false);
    $this->actingAs($bob, 'web');

    $this->getJson('/api/admin/system/me')
        ->assertForbidden()
        ->assertJsonPath('payload.errorKey', 'forbidden');

    expect(auth()->guard('web')->id())->toBe($bob->id);
});

it('treats a site user without an admin role as a guest of the shell', function (): void {
    $this->actingAs(sharedUser('bob@example.com', admin: false), 'web');

    $bag = app(BootstrapBuilder::class)->build();

    expect($bag['user'])->toBeNull()->and($bag['permissions'])->toBe([]);
});

it('lets the model decide through canAccessAdmin()', function (): void {
    config()->set('auth.providers.users.model', TestSharedStaffUser::class);
    config()->set('admin.auth.model', TestSharedStaffUser::class);

    TestSharedStaffUser::create(['name' => 'Staff', 'email' => 'eve@staff.test', 'password' => 'secret-pass']);
    TestSharedStaffUser::create(['name' => 'Client', 'email' => 'carl@example.com', 'password' => 'secret-pass']);

    // No roles at all: the hook alone lets the staff member in.
    $this->postJson('/api/admin/auth/login', ['email' => 'eve@staff.test', 'password' => 'secret-pass'])->assertOk();
    $this->postJson('/api/admin/auth/logout')->assertOk();
    $this->postJson('/api/admin/auth/login', ['email' => 'carl@example.com', 'password' => 'secret-pass'])->assertForbidden();
});

it('asks for the second factor when HasAdminTwoFactor says it is on', function (): void {
    $user = sharedUser();
    $user->forceFill([
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_recovery_codes' => RecoveryCodes::generate(2),
        'two_factor_confirmed_at' => now(),
    ])->save();

    // Stored encrypted, read back as is — the trait's casts.
    expect($user->fresh()->two_factor_secret)->toBe('JBSWY3DPEHPK3PXP')
        ->and(DB::table('users')->value('two_factor_secret'))->not->toBe('JBSWY3DPEHPK3PXP');

    $this->postJson('/api/admin/auth/login', ['email' => 'alice@example.com', 'password' => 'secret-pass'])
        ->assertOk()
        ->assertJsonPath('payload.errorKey', 'two_factor_required');

    expect(auth()->guard('web')->check())->toBeFalse();
});

it('saves the theme and the locale on the host users table', function (): void {
    $this->actingAs(sharedUser(), 'web');

    $this->postJson('/api/admin/system/setTheme', ['theme' => 'dark'])->assertOk();
    $this->postJson('/api/admin/system/setLocale', ['locale' => 'en'])->assertOk();

    $user = TestSharedUser::query()->firstOrFail();
    expect($user->theme)->toBe('dark')->and($user->locale)->toBe('en');
});

it('refuses a switched-off account', function (): void {
    sharedUser()->forceFill(['is_active' => false])->save();

    $this->postJson('/api/admin/auth/login', ['email' => 'alice@example.com', 'password' => 'secret-pass'])
        ->assertForbidden()
        ->assertJsonPath('payload.errorKey', 'account_inactive');
});
