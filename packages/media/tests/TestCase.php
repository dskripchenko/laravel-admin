<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminMedia\Tests;

use Dskripchenko\DelayedProcess\Providers\DelayedProcessServiceProvider;
use Dskripchenko\LaravelAdmin\AdminServiceProvider;
use Dskripchenko\LaravelAdminMedia\AdminMediaServiceProvider;
use Dskripchenko\LaravelApi\Providers\ApiServiceProvider;
use Dskripchenko\LaravelTranslatable\Providers\TranslatableServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            ApiServiceProvider::class,
            DelayedProcessServiceProvider::class,
            TranslatableServiceProvider::class,
            AdminServiceProvider::class,
            AdminMediaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.debug', true);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Fake disk для media uploads.
        $app['config']->set('filesystems.disks.media-test', [
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/admin-media-test',
        ]);
        $app['config']->set('admin-media.disk', 'media-test');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media-test');
    }
}
