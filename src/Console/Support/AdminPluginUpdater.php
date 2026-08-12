<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Registers a freshly generated resource, screen or widget in the host:
 *   - in an existing AdminPlugin, preferably
 *   - or in AppServiceProvider::boot()
 *
 * It also adds MenuNode::resource()/screen()/dashboard() to Admin::menu() when
 * the host uses MenuRegistry explicitly.
 *
 * Idempotent: anything already registered is skipped.
 */
final class AdminPluginUpdater
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * Registers a resource in the host project and returns the path of the
     * file it changed, for the command's report.
     *
     * @return array{path: string, action: 'updated'|'unchanged'|'created'}
     */
    public function registerResource(string $resourceFqcn): array
    {
        return $this->registerInPlugin('resources', $resourceFqcn);
    }

    /**
     * Registers a screen in the host project.
     *
     * @return array{path: string, action: 'updated'|'unchanged'|'created'}
     */
    public function registerScreen(string $screenFqcn): array
    {
        return $this->registerInPlugin('screen', $screenFqcn);
    }

    /**
     * Adds a node through Admin::menu()->add(...). When the plugin does not
     * use MenuRegistry yet, this becomes its first such call.
     *
     * @return array{path: string, action: 'updated'|'unchanged'}
     */
    public function addMenuNode(string $kind, string $slug, ?string $parent = null): array
    {
        $plugin = $this->findOrCreatePlugin();

        $code = $this->buildMenuLine($kind, $slug, $parent);
        $contents = $this->files->get($plugin);

        if (str_contains($contents, $code)) {
            return ['path' => $plugin, 'action' => 'unchanged'];
        }

        // Find the `boot(Admin $admin)` — or any `function boot(...)` —
        // method and insert $admin->menu()->add(...) into it.
        $modified = $this->insertIntoBoot($contents, '        '.$code);

        if ($modified === $contents) {
            return ['path' => $plugin, 'action' => 'unchanged'];
        }

        $this->files->put($plugin, $modified);

        return ['path' => $plugin, 'action' => 'updated'];
    }

    /**
     * Adds a use statement to the plugin file unless it is already there.
     */
    public function ensureImport(string $path, string $fqcn): void
    {
        $contents = $this->files->get($path);
        $useLine = "use {$fqcn};";
        if (str_contains($contents, $useLine)) {
            return;
        }
        $contents = preg_replace_callback(
            '/(\nuse [^;]+;\n)(?!\nuse )/u',
            fn (array $m): string => $m[1]."\n".$useLine."\n",
            $contents,
            1,
        ) ?? $contents;
        $this->files->put($path, $contents);
    }

    /**
     * Finds an existing plugin class in app/Admin/, or generates a new
     * AdminPlugin and registers it in config('admin.plugins').
     */
    private function findOrCreatePlugin(): string
    {
        // Look in the usual places
        $candidates = [
            base_path('app/Admin/AdminPlugin.php'),
            base_path('app/Admin/DemoPlugin.php'),
            base_path('app/Admin/Plugins/AdminPlugin.php'),
        ];
        foreach ($candidates as $path) {
            if ($this->files->exists($path)) {
                return $path;
            }
        }

        // Find any AdminPlugin implementor in app/Admin/
        $base = base_path('app/Admin');
        if (is_dir($base)) {
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
            foreach ($iter as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $content = $this->files->get($file->getPathname());
                if (str_contains($content, 'AdminPlugin') && str_contains($content, 'function boot')) {
                    return $file->getPathname();
                }
            }
        }

        // Fall back to creating a stub plugin
        return $this->createStubPlugin();
    }

    private function createStubPlugin(): string
    {
        $path = base_path('app/Admin/AdminPlugin.php');
        $this->files->ensureDirectoryExists(dirname($path));

        $contents = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Admin;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin as AdminPluginContract;

final class AdminPlugin implements AdminPluginContract
{
    public function name(): string
    {
        return 'app';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function register(): void
    {
        // Custom permissions and settings are registered here.
    }

    public function boot(Admin $admin): void
    {
        $admin->resources([]);
        $admin->screen([]);
    }
}
PHP;
        $this->files->put($path, $contents);

        return $path;
    }

    /**
     * Inserts an FQCN into `$admin->resources([...])` or `$admin->screen([...])`.
     *
     * @param  'resources'|'screen'  $kind
     * @return array{path: string, action: 'updated'|'unchanged'|'created'}
     */
    private function registerInPlugin(string $kind, string $fqcn): array
    {
        $plugin = $this->findOrCreatePlugin();
        $contents = $this->files->get($plugin);

        $shortClass = $this->shortName($fqcn);
        $useLine = "use {$fqcn};";

        // Imported already?
        $needImport = ! str_contains($contents, $useLine);

        // Registered already?
        $needle = $shortClass.'::class';
        if (str_contains($contents, $needle)) {
            return ['path' => $plugin, 'action' => 'unchanged'];
        }

        if ($needImport) {
            $contents = $this->insertImport($contents, $useLine);
        }

        // Find `$admin->resources([...])` or `$admin->screen([...])` and add
        // to it. When there is no such call, add a new one before boot() closes.
        $callPattern = $kind === 'resources'
            ? '/\$admin->resources\(\[(.*?)\]\)/s'
            : '/\$admin->screen\(\[(.*?)\]\)/s';

        if (preg_match($callPattern, $contents, $m)) {
            $existing = trim($m[1]);
            $newList = $existing === ''
                ? "\n            {$shortClass}::class,\n        "
                : rtrim($existing, " \n,").",\n            {$shortClass}::class,\n        ";
            $replaced = preg_replace_callback(
                $callPattern,
                fn (): string => '$admin->'.($kind === 'resources' ? 'resources' : 'screen')."([{$newList}])",
                $contents,
                1,
            );
            if ($replaced !== null) {
                $contents = $replaced;
            }
        } else {
            $newCall = '        $admin->'.($kind === 'resources' ? 'resources' : 'screen')."([{$shortClass}::class]);";
            $contents = $this->insertIntoBoot($contents, $newCall);
        }

        $this->files->put($plugin, $contents);

        return ['path' => $plugin, 'action' => 'updated'];
    }

    private function shortName(string $fqcn): string
    {
        return class_basename($fqcn);
    }

    /**
     * Inserts a use line after the last existing `use` of the namespace block.
     */
    private function insertImport(string $contents, string $useLine): string
    {
        // Find the last use at the top of the file.
        if (preg_match_all('/^use [^;]+;/m', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $last = end($matches[0]);
            $pos = $last[1] + strlen($last[0]);

            return substr($contents, 0, $pos)."\n".$useLine.substr($contents, $pos);
        }

        // Failing that, right after the namespace.
        return preg_replace('/(namespace [^;]+;\n)/', "$1\n".$useLine."\n", $contents, 1) ?? $contents;
    }

    /**
     * Inserts a line at the end of the `boot(...)` method.
     */
    private function insertIntoBoot(string $contents, string $line): string
    {
        return preg_replace_callback(
            '/(public function boot\([^)]*\)(?:\s*:\s*\w+)?\s*\{)(.*?)(\n\s*\})/s',
            function (array $m) use ($line): string {
                $body = rtrim($m[2]);
                if ($body !== '' && ! str_ends_with($body, "\n")) {
                    $body .= "\n";
                }

                return $m[1].$body."\n".$line.$m[3];
            },
            $contents,
            1,
        ) ?? $contents;
    }

    private function buildMenuLine(string $kind, string $slug, ?string $parent): string
    {
        $factory = match ($kind) {
            'resource' => "MenuNode::resource('{$slug}')",
            'screen' => "MenuNode::screen('{$slug}')",
            'dashboard' => "MenuNode::dashboard('{$slug}')",
            default => "MenuNode::make('{$slug}', '".Str::title($slug)."')",
        };

        if ($parent !== null && $parent !== '') {
            return "\$admin->menu()->under('{$parent}', [{$factory}]);";
        }

        return "\$admin->menu()->add({$factory});";
    }
}
