<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Support\PrebuiltAssets;
use Illuminate\Support\Facades\File;

afterEach(function (): void {
    File::deleteDirectory(PrebuiltAssets::publishedPath());
    File::delete([config_path('admin.php'), base_path('vite.config.js'), base_path('resources/js/admin.js')]);
});

it('installs non-interactively with the prebuilt frontend', function (): void {
    $this->artisan('admin:install', ['--no-migrate' => true, '--no-user' => true, '--no-composer-hook' => true])
        ->assertSuccessful();

    expect(file_exists(config_path('admin.php')))->toBeTrue()
        ->and(PrebuiltAssets::isPublished())->toBeTrue();
});

it('wires the host Vite build with --custom-build', function (): void {
    File::put(base_path('vite.config.js'), <<<'JS'
        import laravel from 'laravel-vite-plugin';
        export default { plugins: [laravel({ input: ['resources/css/app.css', 'resources/js/app.js'] })] };
        JS);

    $this->artisan('admin:install', ['--custom-build' => true, '--no-migrate' => true, '--no-user' => true])
        ->assertSuccessful();

    expect(File::get(base_path('vite.config.js')))->toContain("'resources/js/admin.js'")
        ->and(File::get(base_path('resources/js/admin.js')))->toContain('createAdminApp')
        ->and(File::get(config_path('admin.php')))->toContain("'vite_entry' => 'resources/js/admin.js'")
        ->and(PrebuiltAssets::isPublished())->toBeFalse();
});
