<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * `php artisan admin:link`
 *
 * Симлинк собранных SPA-ассетов admin → public/vendor/admin.
 *
 * Резолвит источник в таком порядке:
 *   1. node_modules/@dskripchenko/laravel-admin/dist (npm-сборка SPA)
 *   2. vendor/dskripchenko/laravel-admin/dist (composer-pre-built)
 *   3. локальный dist/ пакета (для dev-режима с path-repo)
 *
 * Если ни один источник не найден — печатает инструкцию.
 */
final class LinkCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'admin:link
                            {--force : Перезаписать существующий симлинк/директорию}
                            {--relative : Создать relative symlink}';

    /**
     * @var string
     */
    protected $description = 'Симлинк скомпилированных SPA-ассетов admin в public/vendor/admin';

    public function handle(Filesystem $files): int
    {
        $target = $this->resolveSourcePath($files);
        if ($target === null) {
            $this->error('Не найдены собранные ассеты admin.');
            $this->line('Соберите SPA (`npm run build`) и попробуйте снова.');
            $this->line('Допустимые источники (в порядке поиска):');
            $this->line('  1. node_modules/@dskripchenko/laravel-admin/dist');
            $this->line('  2. vendor/dskripchenko/laravel-admin/dist');
            $this->line('  3. локальный dist/');

            return self::FAILURE;
        }

        $linkPath = public_path('vendor/admin');

        if ($files->exists($linkPath)) {
            if (! $this->option('force')) {
                $this->error("{$linkPath} уже существует. Используйте --force для перезаписи.");

                return self::FAILURE;
            }
            $files->isDirectory($linkPath) && ! $files->isLink($linkPath)
                ? $files->deleteDirectory($linkPath)
                : $files->delete($linkPath);
        }

        $files->ensureDirectoryExists(dirname($linkPath));

        if ($this->option('relative')) {
            $files->relativeLink($target, $linkPath);
        } else {
            $files->link($target, $linkPath);
        }

        $this->info("Симлинк создан: {$linkPath} → {$target}");

        return self::SUCCESS;
    }

    private function resolveSourcePath(Filesystem $files): ?string
    {
        $candidates = [
            base_path('node_modules/@dskripchenko/laravel-admin/dist'),
            base_path('vendor/dskripchenko/laravel-admin/dist'),
            __DIR__.'/../../dist',
        ];

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real !== false && $files->isDirectory($real)) {
                return $real;
            }
        }

        return null;
    }
}
