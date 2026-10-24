<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Audit\AuditLog;
use Dskripchenko\LaravelAdmin\Impersonation\ImpersonationManager;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Permission\RolePreview;
use Dskripchenko\LaravelAdmin\Support\BootstrapBuilder;
use Illuminate\Http\Request;

beforeEach(function (): void {
    $this->super = AdminUser::create(['name' => 'Super', 'email' => 'su-'.uniqid().'@example.com', 'password' => 'secret']);
    $this->super->assignRole(Role::create(['name' => 'Super Admin', 'slug' => 'super-'.uniqid(), 'permissions' => ['*']]));
    $this->viewer = Role::create(['name' => 'Viewer', 'slug' => 'viewer-'.uniqid(), 'permissions' => ['admin.posts.view']]);
    $this->empty = Role::create(['name' => 'Nobody', 'slug' => 'nobody-'.uniqid(), 'permissions' => []]);
    $this->actingAs($this->super->refresh(), 'admin');
});

it('lists the roles to a super admin', function (): void {
    $response = $this->getJson('/api/admin/auth/previewRoles');

    $response->assertOk();
    expect(collect($response->json('payload.roles'))->pluck('slug')->all())->toContain($this->viewer->slug, $this->empty->slug);
    expect($response->json('payload.current'))->toBeNull();
});

it('views the panel as a role and comes back', function (): void {
    expect($this->super->hasAccess('admin.users.delete'))->toBeTrue();

    $this->postJson('/api/admin/auth/startRolePreview', ['role' => $this->viewer->slug])
        ->assertOk()
        ->assertJsonPath('payload.rolePreview.slug', $this->viewer->slug);

    $user = $this->super->fresh();
    expect($user->hasAccess('admin.posts.view'))->toBeTrue()
        ->and($user->hasAccess('admin.users.delete'))->toBeFalse()
        ->and($user->getAllPermissions())->toBe(['admin.posts.view'])
        ->and($user->hasRealAccess('admin.users.delete'))->toBeTrue();

    // The backend refuses what the role may not do.
    $this->getJson('/api/admin/system/me')
        ->assertOk()
        ->assertJsonPath('payload.rolePreview.name', 'Viewer')
        ->assertJsonPath('payload.canPreviewRoles', true);
    $payload = app(BootstrapBuilder::class)->build(Request::create('/'));
    expect($payload['permissions'])->toBe(['admin.posts.view'])
        ->and($payload['user']['rolePreview']['slug'])->toBe($this->viewer->slug);

    $this->postJson('/api/admin/auth/stopRolePreview')->assertOk()->assertJsonPath('payload.rolePreview', null);
    expect($this->super->fresh()->hasAccess('admin.users.delete'))->toBeTrue();
    expect(session()->has(RolePreview::SESSION_KEY))->toBeFalse();
});

it('comes back from a role with no permissions at all', function (): void {
    $this->postJson('/api/admin/auth/startRolePreview', ['role' => $this->empty->slug])->assertOk();
    expect($this->super->fresh()->hasAccess('admin.users.view'))->toBeFalse();

    // Still allowed to switch: judged by the user's own roles.
    $this->getJson('/api/admin/auth/previewRoles')->assertOk()->assertJsonPath('payload.current.slug', $this->empty->slug);
    $this->postJson('/api/admin/auth/stopRolePreview')->assertOk();
    expect($this->super->fresh()->hasAccess('admin.users.view'))->toBeTrue();
});

it('does not touch other users checked in the same session', function (): void {
    $other = AdminUser::create(['name' => 'Other', 'email' => 'o-'.uniqid().'@example.com', 'password' => 'secret']);
    $other->assignRole(Role::create(['name' => 'Editor', 'slug' => 'editor-'.uniqid(), 'permissions' => ['admin.users.view']]));

    $this->postJson('/api/admin/auth/startRolePreview', ['role' => $this->empty->slug])->assertOk();

    expect($other->fresh()->hasAccess('admin.users.view'))->toBeTrue();
});

it('is refused to a user without the permission in their own roles', function (): void {
    $plain = AdminUser::create(['name' => 'Plain', 'email' => 'p-'.uniqid().'@example.com', 'password' => 'secret']);
    $plain->assignRole($this->viewer);
    $this->actingAs($plain->refresh(), 'admin');

    $this->getJson('/api/admin/auth/previewRoles')->assertStatus(403);
    $this->postJson('/api/admin/auth/startRolePreview', ['role' => $this->empty->slug])
        ->assertStatus(403)
        ->assertJsonPath('payload.errorKey', 'forbidden');
    $this->getJson('/api/admin/system/me')->assertJsonPath('payload.canPreviewRoles', false);
});

it('is refused for an unknown role, while impersonating, and when switched off', function (): void {
    $this->postJson('/api/admin/auth/startRolePreview', ['role' => 'no-such-role'])->assertStatus(404);

    session()->put(ImpersonationManager::SESSION_KEY, 999);
    $this->postJson('/api/admin/auth/startRolePreview', ['role' => $this->viewer->slug])
        ->assertStatus(403)
        ->assertJsonPath('payload.errorKey', 'impersonating');
    session()->forget(ImpersonationManager::SESSION_KEY);

    config()->set('admin.auth.role_preview.enabled', false);
    $this->postJson('/api/admin/auth/startRolePreview', ['role' => $this->viewer->slug])->assertStatus(403);
});

it('stop without a preview is a 400', function (): void {
    $this->postJson('/api/admin/auth/stopRolePreview')
        ->assertStatus(400)
        ->assertJsonPath('payload.errorKey', 'no_active_role_preview');
});

it('ends when the previewed role is deleted', function (): void {
    $this->postJson('/api/admin/auth/startRolePreview', ['role' => $this->viewer->slug])->assertOk();
    $this->viewer->delete();
    app()->forgetScopedInstances();

    expect($this->super->fresh()->hasAccess('admin.users.delete'))->toBeTrue();
});

it('writes the start and the end into the audit', function (): void {
    $this->postJson('/api/admin/auth/startRolePreview', ['role' => $this->viewer->slug])->assertOk();
    $this->postJson('/api/admin/auth/stopRolePreview')->assertOk();

    $events = AuditLog::query()->where('actor_id', $this->super->id)->where('event', 'like', 'role_preview.%')->orderBy('id')->get();
    expect($events->pluck('event')->all())->toBe(['role_preview.started', 'role_preview.stopped'])
        ->and($events->first()->changes['payload']['role'])->toBe($this->viewer->slug);
});

it('is ignored by a Bearer request', function (): void {
    session()->put(RolePreview::SESSION_KEY, ['type' => $this->super->getMorphClass(), 'id' => $this->super->id, 'role' => $this->empty->slug]);
    $request = Request::create('/');
    $request->setLaravelSession(session()->driver());
    $request->headers->set('Authorization', 'Bearer abc');
    app()->instance('request', $request);
    app()->forgetScopedInstances();

    expect($this->super->fresh()->hasAccess('admin.users.view'))->toBeTrue();
});
