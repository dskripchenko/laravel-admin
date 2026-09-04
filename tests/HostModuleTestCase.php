<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests;

use Dskripchenko\LaravelAdmin\Tests\Fixtures\Host\TestHostApiModule;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Illuminate\Foundation\Application;

/**
 * An environment where the api_module is a host module: the admin plus a
 * version of the application's own.
 *
 * The binding has to land after AdminServiceProvider::register() — which
 * binds AdminApiModule — and before the providers boot, where laravel-api
 * reads the module to register the routes. `booting` is exactly that slot.
 */
abstract class HostModuleTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        /** @var Application $app */
        $app->booting(static function (Application $app): void {
            $app->singleton('api_module', TestHostApiModule::class);
            ApiModule::clearResolvedInstance('api_module');
        });
    }
}
