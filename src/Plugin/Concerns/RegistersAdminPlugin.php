<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Plugin\Concerns;

/**
 * The trait for the sister packs' service providers.
 *
 * Every sister pack has to push its AdminPlugin class into
 * `config('admin.plugins')` during register(), so that the core's
 * AdminServiceProvider::bootPlugins() picks it up. This trait holds the eight
 * lines of boilerplate that takes.
 *
 * Usage:
 *
 *     final class AdminJobsServiceProvider extends ServiceProvider
 *     {
 *         use RegistersAdminPlugin;
 *
 *         public function register(): void
 *         {
 *             $this->mergeConfigFrom(...);
 *             $this->registerAdminPlugin(AdminJobsPlugin::class);
 *         }
 *     }
 */
trait RegistersAdminPlugin
{
    /**
     * Adds an AdminPlugin class to `config('admin.plugins')`.
     *
     * Idempotent: calling it again adds nothing.
     *
     * @param  class-string  $pluginClass
     */
    protected function registerAdminPlugin(string $pluginClass): void
    {
        $existing = (array) config('admin.plugins', []);
        if (in_array($pluginClass, $existing, true)) {
            return;
        }
        config(['admin.plugins' => [...$existing, $pluginClass]]);
    }
}
