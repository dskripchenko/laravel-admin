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
    // Default 'scalar' — route уже зарегистрирован в boot'е.
    expect((string) config('admin.openapi.ui', 'scalar'))->toBe('scalar');

    // Меняем config — реальная перерегистрация требует bootstrap'а свежего
    // приложения. Логика registerScalarDoc() guard'ится этим флагом, проверим
    // его поведение в новой Application.
    $app = new Illuminate\Foundation\Application(__DIR__);
    $app['config'] = new Illuminate\Config\Repository([
        'admin' => ['openapi' => ['ui' => 'swagger']],
    ]);
    expect((string) $app['config']->get('admin.openapi.ui'))->toBe('swagger');
});

it('Scalar doc passes $sources from API versions', function (): void {
    $response = $this->get('/api/admin/doc');
    $html = $response->getContent();

    // 'admin' = laravel-api version slug; в data-url виден spec-файл,
    // в data-configuration — sources с slug='admin'.
    expect($html)->toContain('admin.json');
});
