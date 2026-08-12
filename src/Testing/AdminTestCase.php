<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Testing;

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Settings\SettingsRegistry;
use Dskripchenko\LaravelAdmin\Testing\Concerns\ActsAsAdmin;
use Dskripchenko\LaravelAdmin\Testing\Concerns\InteractsWithAdminResources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;

/**
 * The base TestCase of a host project's admin tests.
 *
 * What it adds over a plain `extends TestCase`:
 *   - RefreshDatabase is built in;
 *   - clearAdminRegistries() in `setUp()` resets laravel-admin's
 *     ResourceRegistry, SettingsRegistry and AdminApi cache between tests;
 *   - the ActsAsAdmin trait, for logging in quickly.
 *
 * Usage:
 *
 *     class UsersResourceTest extends AdminTestCase
 *     {
 *         it('lists users', function () {
 *             $this->actingAsSuperAdmin();
 *             $this->registerResource(UserResource::class);
 *             $this->postJson('/api/admin/users/search')->assertOk();
 *         });
 *     }
 */
abstract class AdminTestCase extends LaravelTestCase
{
    use ActsAsAdmin;
    use InteractsWithAdminResources;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAdminRegistries();
    }

    /**
     * Resets laravel-admin's singletons, which matters for the tests that
     * register their own resources and settings — otherwise neighbouring tests
     * see one another.
     */
    protected function clearAdminRegistries(): void
    {
        $this->app->make(ResourceRegistry::class)->clear();
        $this->app->make(SettingsRegistry::class)->clear();
        AdminApi::clearCache();
    }

    /**
     * Registers a resource in the registry, for the current test.
     *
     * @param  class-string<\Dskripchenko\LaravelAdmin\Resource\Resource>  $class
     */
    protected function registerResource(string $class): void
    {
        $this->app->make(ResourceRegistry::class)->add($class);
        AdminApi::clearCache();
    }

    /**
     * Registers a SettingsResource in the registry, for the current test.
     *
     * @param  class-string<\Dskripchenko\LaravelAdmin\Settings\SettingsResource>  $class
     */
    protected function registerSettings(string $class): void
    {
        $this->app->make(SettingsRegistry::class)->add($class);
        AdminApi::clearCache();
    }
}
