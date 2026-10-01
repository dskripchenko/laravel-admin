<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

use Dskripchenko\LaravelAdmin\Support\BootstrapBuilder;
use Dskripchenko\LaravelAdmin\Support\PrebuiltAssets;
use Dskripchenko\LaravelAdmin\Support\ViteManifest;
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
     * In order, see config/admin.php → 'assets':
     *  1. The host's own build: `assets.vite_manifest` + `assets.vite_entry`
     *     (a Vite manifest, resolved with its imports and css), and/or the
     *     explicit `assets.css` / `assets.js` URL lists, which come after the
     *     manifest's files so that they can override them.
     *  2. Otherwise the prebuilt application the package ships, published to
     *     public/vendor/admin by `admin:install` / `admin:publish`.
     *
     * `missing` tells the shell that nothing could be found, so it explains
     * what to run instead of rendering a blank page; `stale` that the
     * published prebuilt copy is older than the installed package.
     *
     * @return array{css: list<string>, js: list<string>, missing: bool, stale: bool}
     */
    private function resolveAssets(): array
    {
        $css = array_values((array) config('admin.assets.css', []));
        $js = array_values((array) config('admin.assets.js', []));

        $manifestPath = config('admin.assets.vite_manifest');
        $entry = config('admin.assets.vite_entry');

        if (is_string($manifestPath) && $manifestPath !== '' && is_string($entry) && $entry !== '') {
            $resolved = ViteManifest::resolve(
                $manifestPath,
                $entry,
                (string) config('admin.assets.vite_base_url', '/build/'),
            ) ?? ['css' => [], 'js' => []];
            $css = [...$resolved['css'], ...$css];
            $js = [...$resolved['js'], ...$js];
        }

        $stale = false;
        if ($css === [] && $js === []) {
            $prebuilt = PrebuiltAssets::resolve();
            if ($prebuilt !== null) {
                [$css, $js] = [$prebuilt['css'], $prebuilt['js']];
                $stale = PrebuiltAssets::isStale();
            }
        }

        return [
            'css' => array_values(array_unique($css)),
            'js' => array_values(array_unique($js)),
            'missing' => $js === [],
            'stale' => $stale,
        ];
    }
}
