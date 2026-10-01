<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

/**
 * Reads a Vite manifest.json and collects the CSS and JS an entry needs.
 *
 * The format, see https://vite.dev/guide/backend-integration.html:
 *   {
 *     "resources/js/admin.js": {
 *       "file": "assets/admin-XXX.js",
 *       "isEntry": true,
 *       "imports": ["_shared-YYY.js"],
 *       "css": ["assets/admin-ZZZ.css"]
 *     },
 *     "_shared-YYY.js": { "file": "...", "css": [...] }
 *   }
 */
final class ViteManifest
{
    /**
     * @return array{css: list<string>, js: list<string>}|null null when the
     *                                                         manifest or the
     *                                                         entry is missing
     */
    public static function resolve(string $manifestPath, string $entry, string $baseUrl): ?array
    {
        if (! is_file($manifestPath)) {
            return null;
        }
        /** @var array<string, array<string, mixed>>|null $manifest */
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest) || ! isset($manifest[$entry])) {
            return null;
        }

        $base = rtrim($baseUrl, '/').'/';
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
