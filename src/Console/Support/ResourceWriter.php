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

        return $this->write($targetPath, $this->replace($this->files->get($stubPath), $vars), $force);
    }

    /**
     * Writes a generated file, unless it exists and $force is off.
     */
    public function write(string $targetPath, string $contents, bool $force = false): bool
    {
        if (! $force && $this->files->exists($targetPath)) {
            return false;
        }

        $this->files->ensureDirectoryExists(dirname($targetPath));
        $this->files->put($targetPath, $contents);

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
     * Derives a class name from the label the user typed, word by word:
     * "Contact us" → ContactUsScreen, a Cyrillic "Statya" (transliterated) → StatyaResource. The label is
     * taken as it is — the wizards ask for the singular already, and
     * singularizing the whole phrase mangled it ("Contact us" → "Contact u").
     */
    public function classNameFor(string $label, string $suffix = 'Resource'): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', Str::ascii($label), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $base = implode('', array_map(static fn (string $w): string => Str::ucfirst($w), $words));
        if ($base === '' || ctype_digit($base[0])) {
            $base = 'Admin'.$base;
        }
        if ($suffix !== '' && str_ends_with($base, $suffix)) {
            $base = substr($base, 0, -strlen($suffix)) ?: $base;
        }

        return $base.$suffix;
    }

    /**
     * The URL slug of a label: "Contact us" → contact-us.
     */
    public function slugFor(string $label): string
    {
        $slug = Str::slug(Str::ascii($label));

        return $slug !== '' ? $slug : 'section';
    }

    /**
     * The plural slug of a resource: "Blog post" → blog-posts.
     */
    public function resourceSlugFor(string $singularLabel): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', Str::ascii($singularLabel), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return 'records';
        }
        $last = array_pop($words);
        $words[] = Str::plural($last);

        return Str::lower(implode('-', $words));
    }
}
