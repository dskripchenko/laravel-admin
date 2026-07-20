<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Регистрирует свежесгенерированный Resource/Screen/Widget в host'е:
 *   - либо в существующий AdminPlugin (предпочтительно)
 *   - либо в AppServiceProvider::boot()
 *
 * Также добавляет MenuNode::resource()/screen()/dashboard() в Admin::menu()
 * если host явно использует MenuRegistry.
 *
 * Идемпотентно: если уже добавлено — skip.
 */
final class AdminPluginUpdater
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * Регистрирует Resource в host-проекте. Возвращает путь к
     * модифицированному файлу (для отчёта команды).
     *
     * @return array{path: string, action: 'updated'|'unchanged'|'created'}
     */
    public function registerResource(string $resourceFqcn): array
    {
        return $this->registerInPlugin('resources', $resourceFqcn);
    }

    /**
     * Регистрирует Screen в host-проекте.
     *
     * @return array{path: string, action: 'updated'|'unchanged'|'created'}
     */
    public function registerScreen(string $screenFqcn): array
    {
        return $this->registerInPlugin('screen', $screenFqcn);
    }

    /**
     * Добавляет node в Admin::menu()->add(...). Если плагин не использует
     * MenuRegistry — добавит первым вызовом.
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

        // Найдём `boot(Admin $admin)` или `function boot(...)` метод и
        // вставим $admin->menu()->add(... );
        $modified = $this->insertIntoBoot($contents, '        '.$code);

        if ($modified === $contents) {
            return ['path' => $plugin, 'action' => 'unchanged'];
        }

        $this->files->put($plugin, $modified);

        return ['path' => $plugin, 'action' => 'updated'];
    }

    /**
     * Добавляет use-statement в plugin-файл, если ещё нет.
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
     * Найти существующий plugin-class в app/Admin/, иначе сгенерировать
     * новый AdminPlugin и зарегистрировать в config('admin.plugins').
     */
    private function findOrCreatePlugin(): string
    {
        // Поиск в стандартных местах
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

        // Найти любой AdminPlugin-implementor в app/Admin/
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

        // Fallback: создать stub plugin
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
        // Custom permissions / settings регистрируются здесь.
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
     * Вставка FQCN в `$admin->resources([...])` или `$admin->screen([...])`.
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

        // Уже импортирован?
        $needImport = ! str_contains($contents, $useLine);

        // Уже зарегистрирован?
        $needle = $shortClass.'::class';
        if (str_contains($contents, $needle)) {
            return ['path' => $plugin, 'action' => 'unchanged'];
        }

        if ($needImport) {
            $contents = $this->insertImport($contents, $useLine);
        }

        // Найти `$admin->resources([...])` или `$admin->screen([...])` и добавить.
        // Если такого вызова нет — добавить новый перед закрытием boot().
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
     * Вставляет use-line после последнего существующего `use` в namespace-блоке.
     */
    private function insertImport(string $contents, string $useLine): string
    {
        // Найдём последний use в начале файла.
        if (preg_match_all('/^use [^;]+;/m', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $last = end($matches[0]);
            $pos = $last[1] + strlen($last[0]);

            return substr($contents, 0, $pos)."\n".$useLine.substr($contents, $pos);
        }

        // Иначе после namespace.
        return preg_replace('/(namespace [^;]+;\n)/', "$1\n".$useLine."\n", $contents, 1) ?? $contents;
    }

    /**
     * Вставляет строчку в конец метода `boot(...)`.
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
