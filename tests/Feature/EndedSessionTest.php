<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Auth\EndedSessions;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/*
 * A signed-out session stays signed out: a request that was still running
 * with it saves it back under the old id, the old cookie returns with it —
 * and the next request with that id is a guest again.
 */

/** A signed-in session as the session store holds it, and its id. */
function storedSession(AdminUser $admin): string
{
    $id = Str::random(40);
    app('session')->driver()->getHandler()->write($id, serialize([
        '_token' => Str::random(40),
        Auth::guard('admin')->getName() => $admin->getKey(),
    ]));

    return $id;
}

function sessionAdmin(): AdminUser
{
    return AdminUser::create(['name' => 'Out', 'email' => 'out-'.uniqid().'@example.com', 'password' => bin2hex(random_bytes(8))]);
}

it('treats a session as signed in until it is ended', function (): void {
    $admin = sessionAdmin();
    $id = storedSession($admin);

    $this->withCredentials()->withCookie(config('session.cookie'), $id)
        ->getJson('/api/admin/system/bootstrap')->assertOk()->assertJsonPath('payload.user.id', $admin->getKey());
});

it('gives an ended session that came back a guest bootstrap', function (): void {
    $id = storedSession(sessionAdmin());
    EndedSessions::end($id);

    $this->withCredentials()->withCookie(config('session.cookie'), $id)
        ->getJson('/api/admin/system/bootstrap')->assertOk()->assertJsonPath('payload.user', null);
});

it('turns an ended session that came back away from the API', function (): void {
    $id = storedSession(sessionAdmin());
    EndedSessions::end($id);

    $this->withCredentials()->withCookie(config('session.cookie'), $id)
        ->getJson('/api/admin/system/manifest')->assertUnauthorized();
    // And the stored session is gone, not just skipped.
    expect(app('session')->driver()->getHandler()->read($id))->toBe('');
});

it('remembers the session a sign-out ended', function (): void {
    $this->actingAs(sessionAdmin(), 'admin');
    $this->startSession();
    $id = session()->getId();
    $this->withCredentials()->withCookie(config('session.cookie'), $id)->postJson('/api/admin/auth/logout')->assertOk();

    expect(Cache::has('admin:ended-session:'.hash('sha256', $id)))->toBeTrue();
});

it('goes on as before when the cache is down', function (): void {
    $admin = sessionAdmin();
    $id = storedSession($admin);
    Cache::shouldReceive('has')->andThrow(new RuntimeException('cache is down'));
    Cache::shouldReceive('put')->andThrow(new RuntimeException('cache is down'));

    EndedSessions::end($id);
    $this->withCredentials()->withCookie(config('session.cookie'), $id)
        ->getJson('/api/admin/system/bootstrap')->assertOk()->assertJsonPath('payload.user.id', $admin->getKey());
});
