<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminTinymce\Tests;

use Dskripchenko\DelayedProcess\Providers\DelayedProcessServiceProvider;
use Dskripchenko\LaravelAdmin\AdminServiceProvider;
use Dskripchenko\LaravelAdminTinymce\AdminTinymceServiceProvider;
use Dskripchenko\LaravelApi\Providers\ApiServiceProvider;
use Dskripchenko\LaravelTranslatable\Providers\TranslatableServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            AdminTinymceServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
