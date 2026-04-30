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

    // Target имеет permission admin.users.delete, у impersonator'а нет.
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
    // Target тоже с правом impersonate, иначе следующий вызов упадёт на permission-check.
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
