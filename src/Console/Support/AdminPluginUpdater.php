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
     * Makes sure the plugin the generators write into is loaded: it is listed
     * in config('admin.plugins'). When the running config lacks it and
     * config/admin.php exists, the class is appended to its `plugins` list,
     * keeping the file's formatting. Without a published config — or with a
     * `plugins` key the updater cannot find — nothing is written and the
     * result carries the line to add by hand.
     *
     * @return array{status: 'listed'|'registered'|'manual', class: string, config: string, instructions: ?string}
     */
    public function ensurePluginRegistered(?string $pluginPath = null): array
    {
        $pluginPath ??= $this->findOrCreatePlugin();
        $class = $this->classOf($pluginPath) ?? 'App\\Admin\\AdminPlugin';
        $configPath = config_path('admin.php');
        $line = '\\'.$class.'::class';
        $manual = "Add {$line} to the 'plugins' list in config/admin.php"
            .' (publish it first: php artisan vendor:publish --tag=admin-config).';

        $listed = in_array($class, array_map(
            static fn (mixed $c): string => is_string($c) ? ltrim($c, '\\') : '',
            (array) config('admin.plugins', []),
        ), true);

        if (! $this->files->exists($configPath)) {
            return ['status' => $listed ? 'listed' : 'manual', 'class' => $class, 'config' => $configPath, 'instructions' => $listed ? null : $manual];
        }

        $source = (string) $this->files->get($configPath);
        $short = class_basename($class);
        if ($listed || preg_match('/(?<![\\w])'.preg_quote($class, '/').'::class/', $source) === 1
            || preg_match('/(?<![\\w\\\\])'.preg_quote($short, '/').'::class/', $source) === 1) {
            return ['status' => 'listed', 'class' => $class, 'config' => $configPath, 'instructions' => null];
        }

        $open = PhpListEditor::findConfigKeyList($source, 'plugins');
        $updated = $open !== null ? PhpListEditor::append($source, $open, $line) : null;
        if ($updated === null) {
            return ['status' => 'manual', 'class' => $class, 'config' => $configPath, 'instructions' => $manual];
        }

        $this->files->put($configPath, $updated);

        return ['status' => 'registered', 'class' => $class, 'config' => $configPath, 'instructions' => null];
    }

    /**
     * The fully qualified class name declared in a PHP file.
     */
    private function classOf(string $path): ?string
    {
        if (! $this->files->exists($path)) {
            return null;
        }
        $source = (string) $this->files->get($path);
        if (preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/m', $source, $class) !== 1) {
            return null;
        }
        $namespace = preg_match('/^\s*namespace\s+([^;]+);/m', $source, $ns) === 1 ? trim($ns[1]).'\\' : '';

        return $namespace.$class[1];
    }

    /**
     * Finds an existing plugin class in app/Admin/, or generates a new
     * AdminPlugin. ensurePluginRegistered() then makes sure it is listed in
     * config('admin.plugins').
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
        $admin->resources([
        ]);
        $admin->screen([
        ]);
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

        // Add to `$admin->resources([...])` or `$admin->screen([...])`, keeping
        // the list's formatting; with no such call, add one at the end of boot().
        $method = $kind === 'resources' ? 'resources' : 'screen';
        $open = PhpListEditor::findMethodCallList($contents, $method);
        $appended = $open !== null ? PhpListEditor::append($contents, $open, $shortClass.'::class') : null;
        $contents = $appended
            ?? $this->insertIntoBoot($contents, '        $admin->'.$method."([{$shortClass}::class]);");

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
