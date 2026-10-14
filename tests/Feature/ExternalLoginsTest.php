<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Auth\ExternalLogin;
use Dskripchenko\LaravelAdmin\Auth\ExternalLoginRegistry;
use Illuminate\Http\Request;

/*
 * The login page's external ways in (Admin::externalLogins): what a host or a
 * package registers for a panel reaches the bootstrap, a broken resolver does
 * not take the page down, malformed entries are dropped.
 */

it('sends no external ways in when nothing is registered', function (): void {
    $this->getJson('/api/admin/system/bootstrap')
        ->assertOk()
        ->assertJsonPath('payload.externalLogins', []);
});

it('puts what the panel registered into the bootstrap', function (): void {
    $seen = [];
    app(Admin::class)->externalLogins(function (Request $request, string $panel) use (&$seen): array {
        $seen[] = $panel;

        return [
            new ExternalLogin('Sign in with SSO', '/admin/sso/redirect', 'key-round', 'Company accounts'),
            ['label' => 'Partner login', 'url' => '/admin/partner'],
            ['label' => 'No URL'],
            ['label' => 'Duplicate', 'url' => '/admin/sso/redirect'],
        ];
    });

    $logins = $this->getJson('/api/admin/system/bootstrap')->assertOk()->json('payload.externalLogins');

    expect($logins)->toBe([
        ['label' => 'Sign in with SSO', 'url' => '/admin/sso/redirect', 'icon' => 'key-round', 'description' => 'Company accounts'],
        ['label' => 'Partner login', 'url' => '/admin/partner', 'icon' => null, 'description' => null],
    ])->and($seen)->toBe(['admin']);
});

it('skips a resolver that throws and keeps the others', function (): void {
    app(Admin::class)
        ->externalLogins(fn (): array => throw new RuntimeException('provider is down'))
        ->externalLogins(fn (): array => [new ExternalLogin('Still here', '/admin/ok')]);

    $this->getJson('/api/admin/system/bootstrap')
        ->assertOk()
        ->assertJsonPath('payload.externalLogins.0.label', 'Still here');
});

it('keeps each panel to its own ways in', function (): void {
    $registry = app(ExternalLoginRegistry::class);
    $registry->add('client', fn (): array => [new ExternalLogin('Client SSO', '/app/sso/redirect')]);

    expect($registry->for(request(), 'admin'))->toBe([])
        ->and($registry->for(request(), 'client'))->toHaveCount(1);
});

it('carries a login notice to the next bootstrap, once', function (): void {
    $this->withSession([Dskripchenko\LaravelAdmin\Auth\LoginNotice::SESSION_KEY => ['message' => 'No access: ask your administrator', 'tone' => 'danger']]);

    $this->getJson('/api/admin/system/bootstrap')
        ->assertOk()
        ->assertJsonPath('payload.loginNotice', ['message' => 'No access: ask your administrator', 'tone' => 'danger']);

    $this->getJson('/api/admin/system/bootstrap')
        ->assertOk()
        ->assertJsonPath('payload.loginNotice', null);
});

it('puts a notice with a known tone only', function (): void {
    $this->startSession();
    Dskripchenko\LaravelAdmin\Auth\LoginNotice::put('Provider unreachable', 'shouting');

    expect(session(Dskripchenko\LaravelAdmin\Auth\LoginNotice::SESSION_KEY))->toBe(['message' => 'Provider unreachable', 'tone' => 'danger']);
});
