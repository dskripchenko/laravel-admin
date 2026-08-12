<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Generates the resource, screen and widget PHP classes in the host, out of
 * the stubs.
 *
 * Every public method takes the final array of substitutions and writes the
 * file. There is no logic beyond replacing {{ placeholder }}.
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
     * The path of a laravel-admin stub; a host may override it through
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
     * The basic mapping from a namespace to a file path:
     *   App\Admin\Resources\ArticleResource → app/Admin/Resources/ArticleResource.php
     */
    public function classPath(string $namespace, string $class): string
    {
        $relative = str_replace(['App\\', '\\'], ['app/', '/'], $namespace.'\\'.$class).'.php';

        return base_path($relative);
    }

    /**
     * Derives a class name from the label or name the user typed.
     */
    public function classNameFor(string $singularLabel, string $suffix = 'Resource'): string
    {
        return Str::studly(Str::singular($singularLabel)).$suffix;
    }
}
