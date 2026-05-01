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
 * Базовый TestCase для sister-pack'ов.
 *
 * DRY-извлекает паттерн: ApiServiceProvider → DelayedProcess → Translatable
 * → AdminServiceProvider + standard `defineEnvironment()` (sqlite :memory:,
 * array cache/session, sync queue).
 *
 * Sister-pack-овский TestCase наследуется и переопределяет
 * `getPackageProviders()` через `additionalProviders()`:
 *
 *     abstract class TestCase extends \Dskripchenko\LaravelAdmin\Testing\PackageTestCase
 *     {
 *         protected function additionalProviders(): array
 *         {
 *             return [AdminJobsServiceProvider::class];
 *         }
 *     }
 *
 * Для пакета с собственными миграциями / setUp() — переопределить
 * `defineDatabaseMigrations()` / `setUp()`. Для дополнительных env-настроек
 * — `defineAdditionalEnvironment()`.
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
     * Sister-pack-specific service providers (например, AdminJobsServiceProvider).
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
     * Hook для дополнительных config-overrides в подклассе. По умолчанию
     * no-op.
     */
    protected function defineAdditionalEnvironment($app): void
    {
        // override in subclass if needed
    }
}
