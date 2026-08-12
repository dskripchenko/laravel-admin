<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Testing;

use Dskripchenko\DelayedProcess\Providers\DelayedProcessServiceProvider;
use Dskripchenko\LaravelAdmin\AdminServiceProvider;
use Dskripchenko\LaravelApi\Providers\ApiServiceProvider;
use Dskripchenko\LaravelTranslatable\Providers\TranslatableServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * The base TestCase of the sister packs.
 *
 * It holds the shared pattern — ApiServiceProvider → DelayedProcess →
 * Translatable → AdminServiceProvider — plus the usual `defineEnvironment()`:
 * sqlite in memory, an array cache and session, a synchronous queue.
 *
 * A sister pack's TestCase inherits from it and extends
 * `getPackageProviders()` through `additionalProviders()`:
 *
 *     abstract class TestCase extends \Dskripchenko\LaravelAdmin\Testing\PackageTestCase
 *     {
 *         protected function additionalProviders(): array
 *         {
 *             return [AdminJobsServiceProvider::class];
 *         }
 *     }
 *
 * A package with migrations or a setUp() of its own overrides
 * `defineDatabaseMigrations()` or `setUp()`; extra environment settings go
 * into `defineAdditionalEnvironment()`.
 */
abstract class PackageTestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ApiServiceProvider::class,
            DelayedProcessServiceProvider::class,
            TranslatableServiceProvider::class,
            AdminServiceProvider::class,
            ...$this->additionalProviders(),
        ];
    }

    /**
     * The sister pack's own service providers — AdminJobsServiceProvider, for one.
     *
     * @return list<class-string>
     */
    abstract protected function additionalProviders(): array;

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

        $this->defineAdditionalEnvironment($app);
    }

    /**
     * The hook for a subclass's extra config overrides; it does nothing by
     * default.
     */
    protected function defineAdditionalEnvironment($app): void
    {
        // override in subclass if needed
    }
}
