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
 * Базовый TestCase для admin-тестов host-проекта.
 *
 * Преимущества над прямым `extends TestCase`:
 *   - RefreshDatabase встроен;
 *   - clearAdminRegistries() в `setUp()` сбрасывает laravel-admin
 *     ResourceRegistry/SettingsRegistry/AdminApi cache между тестами;
 *   - ActsAsAdmin trait для быстрой авторизации.
 *
 * Использование:
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
     * Сбрасывает laravel-admin singleton'ы — критично для тестов с кастомной
     * регистрацией Resource/Settings (иначе соседние тесты видят друг друга).
     */
    protected function clearAdminRegistries(): void
    {
        $this->app->make(ResourceRegistry::class)->clear();
        $this->app->make(SettingsRegistry::class)->clear();
        AdminApi::clearCache();
    }

    /**
     * Зарегистрировать Resource в registry для текущего теста.
     *
     * @param  class-string<\Dskripchenko\LaravelAdmin\Resource\Resource>  $class
     */
    protected function registerResource(string $class): void
    {
        $this->app->make(ResourceRegistry::class)->add($class);
        AdminApi::clearCache();
    }

    /**
     * Зарегистрировать SettingsResource в registry для текущего теста.
     *
     * @param  class-string<\Dskripchenko\LaravelAdmin\Settings\SettingsResource>  $class
     */
    protected function registerSettings(string $class): void
    {
        $this->app->make(SettingsRegistry::class)->add($class);
        AdminApi::clearCache();
    }
}
