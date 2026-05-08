<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Генерирует Resource/Screen/Widget PHP-классы из stub'ов в host'е.
 *
 * Все pаblic methods принимают финальный массив подстановок и пишут файл.
 * Не делает business-logic'у — просто заменяет {{ placeholder }}.
 */
final class ResourceWriter
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @param  array<string, string>  $vars  {{ placeholder }} → value
     */
    public function fromStub(string $stubPath, string $targetPath, array $vars, bool $force = false): bool
    {
        if (! $force && $this->files->exists($targetPath)) {
            return false;
        }

        $stub = $this->files->get($stubPath);
        $content = $this->replace($stub, $vars);

        $this->files->ensureDirectoryExists(dirname($targetPath));
        $this->files->put($targetPath, $content);

        return true;
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function replace(string $stub, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $stub = str_replace('{{ '.$key.' }}', $value, $stub);
        }

        return $stub;
    }

    /**
     * Путь к stub'у laravel-admin'a, поддерживает host-override через
     * `php artisan vendor:publish --tag=admin-stubs` (publishes to
     * resources/stubs/admin/).
     */
    public function stubPath(string $stub): string
    {
        $hostStub = base_path('resources/stubs/admin/'.$stub);
        if ($this->files->exists($hostStub)) {
            return $hostStub;
        }

        return __DIR__.'/../../../resources/stubs/admin/'.$stub;
    }

    public function classExists(string $namespace, string $class): bool
    {
        $path = $this->classPath($namespace, $class);

        return $this->files->exists($path);
    }

    /**
     * Базовое маппинг namespace → путь файла:
     *   App\Admin\Resources\ArticleResource → app/Admin/Resources/ArticleResource.php
     */
    public function classPath(string $namespace, string $class): string
    {
        $relative = str_replace(['App\\', '\\'], ['app/', '/'], $namespace.'\\'.$class).'.php';

        return base_path($relative);
    }

    /**
     * Подобрать имя класса из строки label/name пользователя.
     */
    public function classNameFor(string $singularLabel, string $suffix = 'Resource'): string
    {
        return Str::studly(Str::singular($singularLabel)).$suffix;
    }
}
