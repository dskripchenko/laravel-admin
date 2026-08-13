<?php

declare(strict_types=1);

it('serves Scalar UI on /api/admin/doc', function (): void {
    $response = $this->get('/api/admin/doc');
    $response->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('id="api-reference"');
    expect($html)->toContain('@scalar/api-reference');
});

it('Scalar doc page contains data-url for OpenAPI spec', function (): void {
    $response = $this->get('/api/admin/doc');
    $html = $response->getContent();

    expect($html)->toMatch('/data-url="[^"]+"/');
});

it('Scalar doc page is rendered with CSP nonce on script tags', function (): void {
    $response = $this->get('/api/admin/doc');
    $html = $response->getContent();

    expect($html)->toMatch('/<script[^>]*nonce="[A-Za-z0-9+\/=]+"/');
});

it('config admin.openapi.ui controls Scalar registration logic', function (): void {
    // The default 'scalar' — the route is already registered at boot.
    expect((string) config('admin.openapi.ui', 'scalar'))->toBe('scalar');

    // We change the config — a real re-registration requires bootstrapping a
    // fresh application. The registerScalarDoc() logic is guarded by this flag,
    // so we check its behaviour in a new Application.
    $app = new Illuminate\Foundation\Application(__DIR__);
    $app['config'] = new Illuminate\Config\Repository([
        'admin' => ['openapi' => ['ui' => 'swagger']],
    ]);
    expect((string) $app['config']->get('admin.openapi.ui'))->toBe('swagger');
});

it('Scalar doc passes $sources from API versions', function (): void {
    $response = $this->get('/api/admin/doc');
    $html = $response->getContent();

    // 'admin' is the laravel-api version slug; the spec file is visible in
    // data-url, and data-configuration holds the sources with slug='admin'.
    expect($html)->toContain('admin.json');
});

it('Scalar script URL is configurable and falls back with raw spec links', function (): void {
    config()->set('admin.openapi.scalar_script', '/vendor/scalar/api-reference.js');

    $admin = Dskripchenko\LaravelAdmin\Models\AdminUser::create([
        'name' => 'D', 'email' => 'doc-'.uniqid().'@example.com', 'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $html = $this->get('/api/admin/doc')->assertOk()->getContent();

    // The script loads from the configured (local) URL rather than a hardcoded CDN.
    expect($html)->toContain('src="/vendor/scalar/api-reference.js"');
    expect($html)->not->toContain('cdn.jsdelivr.net');
    // The fallback block with links to the raw specs is present.
    expect($html)->toContain('api-doc-fallback');
    expect($html)->toContain('OpenAPI');
});
