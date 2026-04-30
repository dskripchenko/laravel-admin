<?php

declare(strict_types=1);

it('serves the SPA shell on the admin path', function (): void {
    $response = $this->get('/admin');

    $response->assertOk();
    $response->assertViewIs('admin::shell');
});

it('serves the SPA shell on any sub-path', function (): void {
    $response = $this->get('/admin/resources/users/42');

    $response->assertOk();
});
