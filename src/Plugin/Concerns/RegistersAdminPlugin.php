<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Plugin\Concerns;

/**
 * Trait для ServiceProvider'ов sister-pack'ов.
 *
 * Каждый sister-pack должен пушить свой AdminPlugin-class в
 * `config('admin.plugins')` на register()-фазе — чтобы core'овский
 * AdminServiceProvider::bootPlugins() забрал его при загрузке. Этот
 * trait DRY-извлекает 8-строчный boilerplate.
 *
 * Использование:
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
     * Добавить класс AdminPlugin в `config('admin.plugins')`.
     *
     * Идемпотентно: повторные вызовы не дублируют запись.
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
