<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminQuill\Tests;

use Dskripchenko\DelayedProcess\Providers\DelayedProcessServiceProvider;
use Dskripchenko\LaravelAdmin\AdminServiceProvider;
use Dskripchenko\LaravelAdminQuill\AdminQuillServiceProvider;
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
            AdminQuillServiceProvider::class,
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
