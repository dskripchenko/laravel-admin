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

it('exposes explicit asset list to the shell view', function (): void {
    config()->set('admin.assets.css', ['/build/admin.css']);
    config()->set('admin.assets.js', ['/build/admin.js']);

    $response = $this->get('/admin');

    $response->assertOk();
    $assets = $response->viewData('assets');
    expect($assets)->toMatchArray([
        'css' => ['/build/admin.css'],
        'js' => ['/build/admin.js'],
    ]);
});

it('resolves css/js from a Vite manifest', function (): void {
    $manifest = [
        'resources/js/admin.js' => [
            'file' => 'assets/admin-AAA.js',
            'isEntry' => true,
            'imports' => ['_shared-BBB.js'],
            'css' => ['assets/admin-CCC.css'],
        ],
        '_shared-BBB.js' => [
            'file' => 'assets/shared-BBB.js',
            'css' => ['assets/shared-DDD.css'],
        ],
    ];
    $manifestPath = sys_get_temp_dir().'/admin-vite-manifest-'.uniqid().'.json';
    file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));

    config()->set('admin.assets.vite_manifest', $manifestPath);
    config()->set('admin.assets.vite_entry', 'resources/js/admin.js');
    config()->set('admin.assets.vite_base_url', '/build/');

    $response = $this->get('/admin');

    @unlink($manifestPath);

    $response->assertOk();
    $assets = $response->viewData('assets');
    // shared chunk visited первым (depth-first по imports), потом entry.
    expect($assets['js'])->toBe([
        '/build/assets/shared-BBB.js',
        '/build/assets/admin-AAA.js',
    ]);
    expect($assets['css'])->toBe([
        '/build/assets/shared-DDD.css',
        '/build/assets/admin-CCC.css',
    ]);
});

it('returns empty assets when neither config-list nor vite-manifest provided', function (): void {
    $response = $this->get('/admin');

    $response->assertOk();
    expect($response->viewData('assets'))->toBe([
        'css' => [],
        'js' => [],
    ]);
});
