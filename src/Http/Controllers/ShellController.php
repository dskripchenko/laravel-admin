<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

use Dskripchenko\LaravelAdmin\Support\BootstrapBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Single Page Application shell.
 *
 * Возвращает один и тот же Blade на любой URL под /admin/* (кроме API).
 * Vue-router на клиенте сам обрабатывает routing.
 *
 * Стратегия 'inline' (default): bootstrap-payload инжектится в shell.blade
 * через `<script>`-тег с CSP-nonce. Стратегия 'xhr': SPA сама запрашивает
 * `/api/admin/system/bootstrap`. Контракт payload'а — единый, через
 * BootstrapBuilder.
 */
final class ShellController
{
    public function __invoke(Request $request, BootstrapBuilder $builder): View
    {
        $strategy = (string) config('admin.bootstrap.strategy', 'inline');

        $bootstrap = $strategy === 'inline'
            ? $builder->build($request)
            : ['strategy' => 'xhr'];

        /** @var view-string $view */
        $view = 'admin::shell';

        return view($view, [
            'bootstrap' => $bootstrap,
            'strategy' => $strategy,
            'cspNonce' => $request->attributes->get('admin.csp_nonce'),
            'brand' => (array) config('admin.brand', []),
            'assets' => $this->resolveAssets(),
        ]);
    }

    /**
     * Резолвит CSS/JS ассеты для shell.blade.
     *
     * Два режима, см. config/admin.php → 'assets':
     *  1. Явный список — `assets.css` / `assets.js` массивы URL'ов.
     *  2. Vite-manifest — `assets.vite_manifest` (path к manifest.json) +
     *     `assets.vite_entry` (например `resources/js/admin.js`). Контроллер
     *     парсит manifest и строит финальный список с учётом `imports` chunks
     *     и `css` для каждого entry.
     *
     * Оба режима совместимы — если указан vite_manifest, он применяется в
     * дополнение к явным спискам (явные ставятся ПОСЛЕ — для override-кейса).
     *
     * @return array{css: list<string>, js: list<string>}
     */
    private function resolveAssets(): array
    {
        $css = array_values((array) config('admin.assets.css', []));
        $js = array_values((array) config('admin.assets.js', []));

        $manifestPath = config('admin.assets.vite_manifest');
        $entry = config('admin.assets.vite_entry');

        if (is_string($manifestPath) && $manifestPath !== '' && is_string($entry) && $entry !== '' && is_file($manifestPath)) {
            $resolved = $this->resolveViteManifest($manifestPath, $entry);
            // vite-manifest ассеты идут ПЕРЕД явными — чтобы host мог
            // override'ить через config.
            $css = [...$resolved['css'], ...$css];
            $js = [...$resolved['js'], ...$js];
        }

        return [
            'css' => array_values(array_unique($css)),
            'js' => array_values(array_unique($js)),
        ];
    }

    /**
     * Парсит Vite manifest.json и собирает CSS/JS для указанного entry.
     *
     * Manifest format (см. https://vite.dev/guide/backend-integration.html):
     *   {
     *     "resources/js/admin.js": {
     *       "file": "assets/admin-XXX.js",
     *       "isEntry": true,
     *       "imports": ["_shared-YYY.js"],
     *       "css": ["assets/admin-ZZZ.css"]
     *     },
     *     "_shared-YYY.js": { "file": "...", "css": [...] }
     *   }
     *
     * @return array{css: list<string>, js: list<string>}
     */
    private function resolveViteManifest(string $manifestPath, string $entry): array
    {
        /** @var array<string, array<string, mixed>>|null $manifest */
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest) || ! isset($manifest[$entry])) {
            return ['css' => [], 'js' => []];
        }

        $base = (string) config('admin.assets.vite_base_url', '/build/');
        $base = rtrim($base, '/').'/';

        $css = [];
        $js = [];
        $visited = [];

        $visit = static function (string $key) use (&$visit, &$visited, &$css, &$js, $manifest, $base): void {
            if (isset($visited[$key]) || ! isset($manifest[$key])) {
                return;
            }
            $visited[$key] = true;
            $node = $manifest[$key];
            foreach ((array) ($node['imports'] ?? []) as $importKey) {
                $visit((string) $importKey);
            }
            foreach ((array) ($node['css'] ?? []) as $cssFile) {
                $css[] = $base.ltrim((string) $cssFile, '/');
            }
            if (isset($node['file']) && is_string($node['file'])) {
                $js[] = $base.ltrim($node['file'], '/');
            }
        };

        $visit($entry);

        return ['css' => $css, 'js' => $js];
    }
}
