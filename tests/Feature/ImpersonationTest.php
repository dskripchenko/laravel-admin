<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Impersonation\ImpersonationManager;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;

beforeEach(function (): void {
    $this->impersonator = AdminUser::create([
        'name' => 'Impersonator',
        'email' => 'imp-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->target = AdminUser::create([
        'name' => 'Target User',
        'email' => 'tgt-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'Imp Role',
        'slug' => 'imp-role-'.uniqid(),
        'permissions' => ['admin.impersonate'],
    ]);
    $this->impersonator->assignRole($role);
    $this->actingAs($this->impersonator->refresh(), 'admin');
});

it('startImpersonation: switches to target and records impersonator in session', function (): void {
    $response = $this->postJson('/api/admin/auth/startImpersonation', [
        'user_id' => $this->target->id,
    ]);

    $response->assertOk();
    expect($response->json('payload.user.id'))->toBe($this->target->id);
    expect($response->json('payload.impersonator.id'))->toBe($this->impersonator->id);
    expect($response->json('payload.impersonator.name'))->toBe('Impersonator');

    expect($this->app['auth']->guard('admin')->id())->toBe($this->target->id);
    expect(session()->get(ImpersonationManager::SESSION_KEY))->toBe($this->impersonator->id);
});

it('startImpersonation: 403 when caller lacks admin.impersonate', function (): void {
    $no = AdminUser::create([
        'name' => 'No Perm',
        'email' => 'np-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($no, 'admin');

    $response = $this->postJson('/api/admin/auth/startImpersonation', [
        'user_id' => $this->target->id,
    ]);

    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('forbidden');
});

it('startImpersonation: 404 when target does not exist', function (): void {
    $response = $this->postJson('/api/admin/auth/startImpersonation', [
        'user_id' => 99999,
    ]);

    $response->assertStatus(404);
    expect($response->json('payload.errorKey'))->toBe('not_found');
});

it('startImpersonation: 403 when impersonating yourself', function (): void {
    $response = $this->postJson('/api/admin/auth/startImpersonation', [
        'user_id' => $this->impersonator->id,
    ]);

    $response->assertStatus(403);
});

it('startImpersonation: blocks higher-powered target when option enabled', function (): void {
    config()->set('admin.auth.impersonation.block_higher_powered', true);

    // The target has the admin.users.delete permission, the impersonator does not.
    $bigRole = Role::create([
        'name' => 'Big',
        'slug' => 'big-'.uniqid(),
        'permissions' => ['admin.impersonate', 'admin.users.delete', 'admin.users.create'],
    ]);
    $this->target->assignRole($bigRole);

    $response = $this->postJson('/api/admin/auth/startImpersonation', [
        'user_id' => $this->target->id,
    ]);

    $response->assertStatus(403);
});

it('startImpersonation: 403 when already impersonating (no nesting)', function (): void {
    // The target also holds the impersonate right, otherwise the next call fails the permission check.
    $impRole = Role::firstWhere('slug', 'like', 'imp-role-%')
        ?? Role::create(['name' => 'I', 'slug' => 'i', 'permissions' => ['admin.impersonate']]);
    $this->target->assignRole($impRole);

    $other = AdminUser::create([
        'name' => 'Other',
        'email' => 'oth-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $this->postJson('/api/admin/auth/startImpersonation', [
        'user_id' => $this->target->id,
    ])->assertOk();

    $response = $this->postJson('/api/admin/auth/startImpersonation', [
        'user_id' => $other->id,
    ]);

    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('already_impersonating');
});

it('stopImpersonation: returns to original user', function (): void {
    $this->postJson('/api/admin/auth/startImpersonation', [
        'user_id' => $this->target->id,
    ])->assertOk();

    $response = $this->postJson('/api/admin/auth/stopImpersonation');

    $response->assertOk();
    expect($response->json('payload.user.id'))->toBe($this->impersonator->id);
    expect($response->json('payload.impersonator'))->toBeNull();
    expect($this->app['auth']->guard('admin')->id())->toBe($this->impersonator->id);
    expect(session()->has(ImpersonationManager::SESSION_KEY))->toBeFalse();
});

it('stopImpersonation: 400 when no active impersonation', function (): void {
    $response = $this->postJson('/api/admin/auth/stopImpersonation');

    $response->assertStatus(400);
    expect($response->json('payload.errorKey'))->toBe('no_active_impersonation');
});

it('startImpersonation: 403 when feature disabled in config', function (): void {
    config()->set('admin.auth.impersonation.enabled', false);

    $response = $this->postJson('/api/admin/auth/startImpersonation', [
        'user_id' => $this->target->id,
    ]);

    $response->assertStatus(403);
    expect($response->json('payload.errorKey'))->toBe('impersonation_disabled');
});

it('system.me reflects impersonator while impersonating', function (): void {
    $this->postJson('/api/admin/auth/startImpersonation', [
        'user_id' => $this->target->id,
    ])->assertOk();

    $response = $this->getJson('/api/admin/system/me');

    $response->assertOk();
    expect($response->json('payload.id'))->toBe($this->target->id);
    expect($response->json('payload.impersonator.id'))->toBe($this->impersonator->id);
    expect($response->json('payload.impersonator.name'))->toBe('Impersonator');
});

it('system.me has impersonator=null when not impersonating', function (): void {
    $response = $this->getJson('/api/admin/system/me');

    $response->assertOk();
    expect($response->json('payload.id'))->toBe($this->impersonator->id);
    expect($response->json('payload.impersonator'))->toBeNull();
});

it('writes the start and the end of an impersonation to the audit log, not as logins', function (): void {
    Dskripchenko\LaravelAdmin\Audit\AuditLog::query()->delete();

    $this->postJson('/api/admin/auth/startImpersonation', ['user_id' => $this->target->id])->assertOk();
    $this->postJson('/api/admin/auth/stopImpersonation')->assertOk();

    $logs = Dskripchenko\LaravelAdmin\Audit\AuditLog::query()->orderBy('id')->get();
    expect($logs->pluck('event')->all())->toBe(['impersonation.started', 'impersonation.stopped']);
    foreach ($logs as $log) {
        expect((int) $log->actor_id)->toBe($this->impersonator->id)
            ->and((int) $log->subject_id)->toBe($this->target->id)
            ->and((int) $log->impersonator_id)->toBe($this->impersonator->id)
            ->and($log->impersonator_type)->toBe($this->impersonator->getMorphClass());
    }
});

it('names the real administrator on every change made while impersonating', function (): void {
    $this->postJson('/api/admin/auth/startImpersonation', ['user_id' => $this->target->id])->assertOk();
    Dskripchenko\LaravelAdmin\Audit\AuditLog::query()->delete();

    $this->postJson('/api/admin/profile/update', ['name' => 'Renamed'])->assertOk();

    $log = Dskripchenko\LaravelAdmin\Audit\AuditLog::query()->where('event', 'updated')->firstOrFail();
    expect((int) $log->actor_id)->toBe($this->target->id)
        ->and((int) $log->impersonator_id)->toBe($this->impersonator->id);

    // After the way back, the administrator's own changes carry no impersonator.
    $this->postJson('/api/admin/auth/stopImpersonation')->assertOk();
    Dskripchenko\LaravelAdmin\Audit\AuditLog::query()->delete();
    $this->postJson('/api/admin/profile/update', ['name' => 'Self'])->assertOk();
    expect(Dskripchenko\LaravelAdmin\Audit\AuditLog::query()->where('event', 'updated')->firstOrFail()->impersonator_id)->toBeNull();
});

it('blocks a target whose permissions the impersonator cannot list or match', function (): void {
    config()->set('admin.auth.impersonation.block_higher_powered', true);
    $manager = app(ImpersonationManager::class);

    // A target model without getAllPermissions(): it cannot be compared, so it is refused.
    $bare = new class extends Illuminate\Foundation\Auth\User
    {
        protected $table = 'admin_users';

        public function hasAccess(string $permission): bool
        {
            return true;
        }
    };
    $bare->forceFill(['id' => $this->target->id]);
    expect($manager->isHigherPowered($this->impersonator, $bare))->toBeTrue();

    // A wildcard of the impersonator's own covers the target's keys.
    $wide = Role::create(['name' => 'W', 'slug' => 'w-'.uniqid(), 'permissions' => ['admin.users.*']]);
    $this->impersonator->assignRole($wide);
    $narrow = Role::create(['name' => 'N', 'slug' => 'n-'.uniqid(), 'permissions' => ['admin.users.view']]);
    $this->target->assignRole($narrow);
    expect($manager->isHigherPowered($this->impersonator->refresh(), $this->target->refresh()))->toBeFalse();

    // A wildcard the impersonator does not hold is higher.
    $broad = Role::create(['name' => 'B', 'slug' => 'b-'.uniqid(), 'permissions' => ['admin.*']]);
    $this->target->assignRole($broad);
    expect($manager->isHigherPowered($this->impersonator->refresh(), $this->target->refresh()))->toBeTrue();
});

it('refuses to enter a user who cannot use the panel', function (): void {
    $this->target->forceFill(['is_active' => false])->save();

    $response = $this->postJson('/api/admin/auth/startImpersonation', ['user_id' => $this->target->id]);

    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('target_cannot_sign_in');
    expect($this->app['auth']->guard('admin')->id())->toBe($this->impersonator->id);
});

it('lets the administrator leave even when the user entered has lost the panel', function (): void {
    $this->postJson('/api/admin/auth/startImpersonation', ['user_id' => $this->target->id])->assertOk();

    // The account is switched off meanwhile: every other request is refused…
    $this->target->forceFill(['is_active' => false])->save();
    // The next request reads the user afresh, as a real one would.
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/admin/system/me')->assertStatus(403);
    $this->app['auth']->forgetGuards();
    $this->postJson('/api/admin/auth/stopImpersonation')->assertOk()
        ->assertJsonPath('payload.user.id', $this->impersonator->id);
    expect($this->app['auth']->guard('admin')->id())->toBe($this->impersonator->id);
});

it('does not bring back an administrator whose password changed meanwhile', function (): void {
    $this->postJson('/api/admin/auth/startImpersonation', ['user_id' => $this->target->id])->assertOk();

    AdminUser::query()->whereKey($this->impersonator->id)->update(['password' => bcrypt('changed')]);

    $this->postJson('/api/admin/auth/stopImpersonation')->assertStatus(400)
        ->assertJsonPath('payload.errorKey', 'impersonator_not_found');
    expect($this->app['auth']->guard('admin')->check())->toBeFalse();
});
