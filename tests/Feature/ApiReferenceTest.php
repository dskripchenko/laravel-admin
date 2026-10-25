<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\Controllers\ApiReferenceController;
use Dskripchenko\LaravelApi\Controllers\ApiDocumentationController;

it('serves the openapi reference on /api/admin/doc', function (): void {
    $html = $this->get('/api/admin/doc')->assertOk()->getContent();

    expect($html)->toContain('id="app"');
    expect($html)->toContain('DskOpenApi.createApiReference');
    expect($html)->toContain('@dskripchenko/openapi@');
    expect($html)->not->toContain('@scalar/api-reference');
});

it('renders the CSP nonce on script tags', function (): void {
    $html = $this->get('/api/admin/doc')->getContent();

    expect($html)->toMatch('/<script[^>]*nonce="[A-Za-z0-9+\/=]+"/');
});

it('hands the API versions to the page as sources with DocLink slugs', function (): void {
    $html = $this->get('/api/admin/doc')->getContent();

    // 'admin' is the laravel-api version; its slug is the first segment of
    // every DocLink anchor, so the page must get it rather than derive one.
    expect($html)->toContain('admin.json');
    expect($html)->toContain('"slug":"admin"');
});

it('takes the script from admin.openapi.script, else from laravel-api', function (): void {
    config()->set('admin.openapi.script', null);
    config()->set('laravel-api.documentation.script', null);
    expect(ApiReferenceController::script())->toBe(ApiDocumentationController::DEFAULT_SCRIPT);

    config()->set('laravel-api.documentation.script', '/vendor/laravel-api/openapi/standalone.js');
    expect(ApiReferenceController::script())->toBe('/vendor/laravel-api/openapi/standalone.js');

    config()->set('admin.openapi.script', '/vendor/admin/openapi.js');
    expect(ApiReferenceController::script())->toBe('/vendor/admin/openapi.js');
});

it('builds the page options from the panel and laravel-api.documentation', function (): void {
    config()->set('admin.brand.name', 'Shop');
    config()->set('admin.openapi.locale', 'ru');
    config()->set('admin.openapi.theme', null);
    config()->set('laravel-api.documentation', [
        'theme' => 'dark',
        'try_it' => 'false',
        'proxy' => '/api/doc-proxy',
        'persist_credentials' => 'local',
        'snippet_targets' => ['curl', 'php-laravel'],
        'default_snippet' => 'php-laravel',
    ]);

    $options = ApiReferenceController::options([['url' => '/x.json', 'title' => 'X', 'slug' => 'x']]);

    expect($options)->toMatchArray([
        'locale' => 'ru',
        'theme' => 'dark',
        'title' => 'Shop — API',
        'tryIt' => false,
        'proxy' => '/api/doc-proxy',
        'persistCredentials' => 'local',
        'snippetTargets' => ['curl', 'php-laravel'],
        'defaultSnippet' => 'php-laravel',
    ]);
    expect($options['sources'][0]['slug'])->toBe('x');
});

it('falls back with raw spec links and a configurable script URL', function (): void {
    config()->set('admin.openapi.script', '/vendor/openapi/standalone.js');

    $admin = Dskripchenko\LaravelAdmin\Models\AdminUser::create([
        'name' => 'D', 'email' => 'doc-'.uniqid().'@example.com', 'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $html = $this->get('/api/admin/doc')->assertOk()->getContent();

    expect($html)->toContain('src="/vendor/openapi/standalone.js"');
    expect($html)->not->toContain('cdn.jsdelivr.net');
    expect($html)->toContain('api-doc-fallback');
});

it('keeps the pre-1.56 controller name working', function (): void {
    expect(class_exists('Dskripchenko\\LaravelAdmin\\Http\\Controllers\\ScalarDocController'))->toBeTrue();
    expect(new Dskripchenko\LaravelAdmin\Http\Controllers\ScalarDocController)->toBeInstanceOf(ApiReferenceController::class);
});
