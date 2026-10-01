<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Support\PrebuiltAssets;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::deleteDirectory(PrebuiltAssets::publishedPath());
    config()->set('admin.assets', ['css' => [], 'js' => [], 'vite_manifest' => null, 'vite_entry' => null]);
});

afterEach(function (): void {
    File::deleteDirectory(PrebuiltAssets::publishedPath());
});

it('ships a prebuilt bundle with a manifest entry', function (): void {
    $manifest = json_decode((string) file_get_contents(PrebuiltAssets::sourcePath().'/.vite/manifest.json'), true);

    expect($manifest)->toHaveKey(PrebuiltAssets::ENTRY)
        ->and($manifest[PrebuiltAssets::ENTRY]['css'] ?? [])->not->toBeEmpty();
});

it('explains what to run when no frontend is available', function (): void {
    $this->get('/admin')
        ->assertOk()
        ->assertSee('php artisan admin:publish')
        ->assertDontSee('type="module"', false);
});

it('loads the published prebuilt bundle when the host has no build of its own', function (): void {
    $this->artisan('admin:publish')->assertSuccessful();

    expect(PrebuiltAssets::isPublished())->toBeTrue()
        ->and(PrebuiltAssets::isStale())->toBeFalse();

    $this->get('/admin')
        ->assertOk()
        ->assertSee('/vendor/admin/assets/', false)
        ->assertSee('type="module"', false)
        ->assertDontSee('admin-assets-stale', false);
});

it('flags a published copy older than the package', function (): void {
    $this->artisan('admin:publish')->assertSuccessful();
    $manifest = PrebuiltAssets::publishedPath().'/.vite/manifest.json';
    $data = json_decode((string) file_get_contents($manifest), true);
    $data['_old.js'] = ['file' => 'assets/old.js'];
    file_put_contents($manifest, json_encode($data));

    expect(PrebuiltAssets::isStale())->toBeTrue();
    $this->get('/admin')->assertOk()->assertSee('admin-assets-stale', false);
});

it('prefers the host build over the prebuilt bundle', function (): void {
    $this->artisan('admin:publish')->assertSuccessful();
    config()->set('admin.assets.js', ['/build/own-admin.js']);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('/build/own-admin.js', false)
        ->assertDontSee('/vendor/admin/assets/', false);
});
