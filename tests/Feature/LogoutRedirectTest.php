<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Auth\LogoutRedirectRegistry;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/*
 * Where the browser goes after a sign-out (Admin::logoutRedirect): a resolver
 * sees the session before it is cleared, its address reaches the response,
 * unsafe addresses and broken resolvers are skipped.
 */

function signedInAdmin(): AdminUser
{
    return AdminUser::create(['name' => 'Out', 'email' => 'out@example.com', 'password' => bin2hex(random_bytes(8))]);
}

it('answers with no redirect when nothing is registered', function (): void {
    $this->actingAs(signedInAdmin(), 'admin');

    $this->postJson('/api/admin/auth/logout')->assertOk()->assertJsonPath('payload.redirect', null);
});

it('reads the session before it is cleared and sends its address', function (): void {
    $admin = signedInAdmin();
    $seen = [];
    app(Admin::class)->logoutRedirect(function (Request $request, string $panel, ?Authenticatable $user) use (&$seen): ?string {
        $seen = [$panel, $user?->getAuthIdentifier(), $request->session()->get('sso.id_token')];

        return 'https://sso.example/logout?id_token_hint=abc';
    });

    $this->actingAs($admin, 'admin')->withSession(['sso.id_token' => 'abc']);
    $this->postJson('/api/admin/auth/logout')
        ->assertOk()
        ->assertJsonPath('payload.redirect', 'https://sso.example/logout?id_token_hint=abc');

    expect($seen)->toBe(['admin', $admin->getKey(), 'abc'])
        ->and($this->app['auth']->guard('admin')->check())->toBeFalse()
        ->and(session('sso.id_token'))->toBeNull();
});

it('skips a resolver that throws or answers with an unsafe address', function (): void {
    app(Admin::class)
        ->logoutRedirect(fn (): ?string => throw new RuntimeException('provider is down'))
        ->logoutRedirect(fn (): string => 'javascript:alert(1)')
        ->logoutRedirect(fn (): string => '//evil.example/x')
        ->logoutRedirect(fn (): ?string => null)
        ->logoutRedirect(fn (): string => '/admin/login?bye=1');

    $this->actingAs(signedInAdmin(), 'admin');
    $this->postJson('/api/admin/auth/logout')->assertOk()->assertJsonPath('payload.redirect', '/admin/login?bye=1');
});

it('keeps each panel to its own redirect', function (): void {
    $registry = app(LogoutRedirectRegistry::class);
    $registry->add('client', fn (): string => 'https://sso.example/client-logout');

    expect($registry->for(request(), 'admin', null))->toBeNull()
        ->and($registry->for(request(), 'client', null))->toBe('https://sso.example/client-logout');
});
