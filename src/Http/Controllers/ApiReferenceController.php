<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

use Dskripchenko\LaravelAdmin\Http\Middleware\ApiDocsAccess;
use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Controllers\ApiDocumentationController;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Operations\Schema\JsonSchemas;
use Dskripchenko\LaravelApi\Services\OpenApi\DocLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The panel's API reference on `{api}/admin/doc`: @dskripchenko/openapi's
 * standalone page, the same one laravel-api serves on /api/doc, with the
 * panel's name, its locale and the shell's middleware and CSP nonce.
 *
 * The OpenAPI spec is assembled by laravel-api's usual machinery
 * (`BaseApi::getOpenApiConfig`), cached on the default disk under
 * `admin.api_docs.cache_path` and served through the `admin.api-doc-source`
 * route — never as a file in public storage. The page and the specs are
 * guarded by ApiDocsAccess. Each version goes to the page with DocLink's slug, so DocLink's anchors
 * open their operations here too.
 */
final class ApiReferenceController
{
    public function __invoke(Request $request): View
    {
        $panels = array_keys(app(\Dskripchenko\LaravelAdmin\Panel\PanelRegistry::class)->all());

        $sources = [];
        foreach (ApiModule::getApiVersionList() as $version => $api) {
            $version = (string) $version;
            // Another panel's spec only for that panel's own readers.
            if (in_array($version, $panels, true) && ! ApiDocsAccess::allows($version)) {
                continue;
            }

            /** @var class-string<BaseApi> $api */
            $config = self::specConfig($version, $api);
            $sources[] = [
                'url' => route('admin.api-doc-source', ['version' => $version]).'?r='.self::stamp($version),
                'title' => is_array($config['info'] ?? null)
                    ? (string) ($config['info']['title'] ?? $version)
                    : $version,
                'slug' => DocLink::documentSlug($version),
            ];
        }

        /** @var view-string $view */
        $view = 'admin::api-reference';

        return view($view, [
            'sources' => $sources,
            'script' => self::script(),
            'options' => self::options($sources),
            'cspNonce' => $request->attributes->get('admin.csp_nonce'),
        ]);
    }

    /**
     * One version's OpenAPI spec as JSON, for the page. ApiDocsAccess has
     * already let the reader through.
     */
    public function spec(string $version): JsonResponse
    {
        $versions = ApiModule::getApiVersionList();
        if (! isset($versions[$version])) {
            abort(404);
        }

        /** @var class-string<BaseApi> $api */
        $api = $versions[$version];

        return response()->json(JsonSchemas::encodable(self::specConfig($version, $api)));
    }

    /**
     * The version's spec, built once and kept on the default disk outside
     * the public folder; rebuilt every time in debug mode.
     *
     * @param  class-string<BaseApi>  $api
     * @return array<string, mixed>
     */
    private static function specConfig(string $version, string $api): array
    {
        $filePath = self::specPath($version);
        if (! Storage::exists($filePath) || app()->hasDebugModeEnabled()) {
            $config = $api::getOpenApiConfig($version);
            Storage::put($filePath, json_encode(JsonSchemas::encodable($config)) ?: '{}');

            return $config;
        }

        $config = json_decode((string) Storage::get($filePath), true);

        return is_array($config) ? $config : [];
    }

    private static function stamp(string $version): int
    {
        $filePath = self::specPath($version);

        return Storage::exists($filePath) ? (@filemtime(Storage::path($filePath)) ?: time()) : time();
    }

    private static function specPath(string $version): string
    {
        $folder = trim((string) config('admin.api_docs.cache_path', 'admin/openapi'), '/');
        if (! Storage::exists($folder)) {
            Storage::makeDirectory($folder);
        }

        return "{$folder}/{$version}.json";
    }

    /** `admin.openapi.script`, else laravel-api's (`laravel-api.documentation.script`). */
    public static function script(): string
    {
        return (string) (config('admin.openapi.script') ?: ApiDocumentationController::documentationScript());
    }

    /**
     * The options of `DskOpenApi.createApiReference()`: laravel-api's page
     * settings (`laravel-api.documentation`), the panel's locale and name.
     *
     * @param  array<int, array{url: string, title: string, slug: string}>  $sources
     * @return array<string, mixed>
     */
    public static function options(array $sources): array
    {
        $doc = (array) config('laravel-api.documentation', []);
        $persist = $doc['persist_credentials'] ?? false;
        if (is_string($persist) && ! in_array($persist, ['session', 'local'], true)) {
            $persist = filter_var($persist, FILTER_VALIDATE_BOOLEAN);
        }
        $locale = (string) (config('admin.openapi.locale') ?: app()->getLocale());

        $options = [
            'sources' => $sources,
            'locale' => in_array($locale, ['en', 'ru'], true) ? $locale : 'auto',
            'theme' => (string) (config('admin.openapi.theme') ?: ($doc['theme'] ?? 'system')),
            'persistTheme' => true,
            'title' => (string) config('admin.brand.name', 'Admin').' — API',
            'tryIt' => filter_var($doc['try_it'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'persistCredentials' => $persist,
            'defaultSnippet' => (string) ($doc['default_snippet'] ?? 'curl'),
        ];
        foreach (['proxy' => 'proxy', 'oauth_redirect_url' => 'oauthRedirectUrl'] as $key => $option) {
            if (! empty($doc[$key])) {
                $options[$option] = (string) $doc[$key];
            }
        }
        if (! empty($doc['snippet_targets']) && is_array($doc['snippet_targets'])) {
            $options['snippetTargets'] = array_values($doc['snippet_targets']);
        }

        return $options;
    }
}
