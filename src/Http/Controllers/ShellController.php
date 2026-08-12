<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

use Dskripchenko\LaravelAdmin\Support\BootstrapBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Single Page Application shell.
 *
 * It returns the same Blade view for every URL under /admin/*, the API aside;
 * the routing is the client-side vue-router's business.
 *
 * With the 'inline' strategy, the default, the bootstrap payload is injected
 * into shell.blade through a `<script>` tag with a CSP nonce. With the 'xhr'
 * strategy the SPA requests `/api/admin/system/bootstrap` itself. Either way
 * the payload's contract is the same one, produced by BootstrapBuilder.
 */
final class ShellController
{
    public function __invoke(Request $request, BootstrapBuilder $builder): View
    {
        // Panels: each panel's shell route carries its id in the route defaults.
        $panelId = $request->route('adminPanel');
        if (is_string($panelId)) {
            $request->attributes->set('admin.panel', $panelId);
        }

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
            'brand' => \Dskripchenko\LaravelAdmin\I18n\Localize::brand(),
            // Read on every request rather than cached along with the
            // bootstrap: the banner counts down to a particular moment, and
            // the host application sets it mid-request.
            'notice' => (array) config('admin.notice', []),
            'assets' => $this->resolveAssets(),
        ]);
    }

    /**
     * Resolves the CSS and JS assets for shell.blade.
     *
     * There are two modes, see config/admin.php → 'assets':
     *  1. An explicit list: the `assets.css` and `assets.js` arrays of URLs.
     *  2. A Vite manifest: `assets.vite_manifest`, the path to manifest.json,
     *     plus `assets.vite_entry`, `resources/js/admin.js` for instance. The
     *     controller parses the manifest and builds the final list, taking the
     *     `imports` chunks and each entry's `css` into account.
     *
     * The two are compatible: when vite_manifest is set it applies on top of
     * the explicit lists, which come AFTER it so that they can override.
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
            // The Vite manifest's assets come BEFORE the explicit ones, so
            // that a host can override them through the config.
            $css = [...$resolved['css'], ...$css];
            $js = [...$resolved['js'], ...$js];
        }

        return [
            'css' => array_values(array_unique($css)),
            'js' => array_values(array_unique($js)),
        ];
    }

    /**
     * Parses a Vite manifest.json and collects the CSS and JS of the given
     * entry.
     *
     * The manifest's format, see https://vite.dev/guide/backend-integration.html:
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
