<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\Middleware\CaptureApiRequest;
use Dskripchenko\LaravelAdmin\Http\Middleware\RunVersionMiddleware;
use Dskripchenko\LaravelApi\Facades\ApiModule;

// The report: a host module inherits AdminApiModule and adds versions of its
// own; the admin's stack — sessions, CSRF, AdminAuth — landed on all of them,
// and a stateless public API answered 401 before its own middleware ran.

it('serves the host version without the admin session auth', function (): void {
    $response = $this->getJson('/api/v1/ping/show');

    $response->assertOk();
    $response->assertJsonPath('payload.pong', 'ok');
});

it('runs the middleware the host version declares for itself', function (): void {
    $response = $this->getJson('/api/v1/ping/show');

    $response->assertOk();
    $response->assertHeader('X-Host-Version-Middleware', 'ran');
});

it('keeps the host version stateless: no session, no CSRF', function (): void {
    // With the `web` group on the route a POST without a token is a 419.
    $response = $this->postJson('/api/v1/ping/store', ['value' => 'x']);

    $response->assertOk();
    $response->assertJsonPath('payload.value', 'x');

    expect($this->getJson('/api/v1/ping/show')->json('payload.session'))->toBeFalse();
});

it('still guards the admin version with the panel stack', function (): void {
    $response = $this->getJson('/api/admin/system/status');

    $response->assertStatus(401);
    $response->assertJsonPath('payload.errorKey', 'unauthenticated');
});

it('registers a group that does not depend on the request', function (): void {
    // What makes the choice per request rather than at boot — the group must
    // be the same whatever URL the worker happened to boot on (Octane).
    expect(ApiModule::getApiMiddleware())->toBe([CaptureApiRequest::class, RunVersionMiddleware::class]);
});
