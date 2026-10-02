<?php

declare(strict_types=1);

// With the admin at the root of its host, the shell's catch-all used to match
// /api/admin/* as well and answered every API call with the SPA's HTML.

it('serves the shell at the root of the admin host', function (): void {
    $this->get('http://admin.example.test/login')->assertOk()->assertViewIs('admin::shell');
});

it('leaves the API to the API on the admin host', function (): void {
    $this->getJson('http://admin.example.test/api/admin/system/me')
        ->assertUnauthorized()
        ->assertJsonPath('payload.errorKey', 'unauthenticated');
});

it('does not serve the shell on other hosts', function (): void {
    $this->get('http://www.example.test/login')->assertNotFound();
});
