<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Controllers\ApiDocumentationController;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Services\OpenApi\DocLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The panel's API reference on `{api}/admin/doc`: @dskripchenko/openapi's
 * standalone page, the same one laravel-api serves on /api/doc, with the
 * panel's name, its locale and the shell's middleware and CSP nonce.
 *
 * The OpenAPI spec is assembled by laravel-api's usual machinery: for each
 * version `BaseApi::getOpenApiConfig` is written into storage and served by
 * URL. Each version goes to the page with DocLink's slug, so DocLink's anchors
 * open their operations here too.
 */
final class ApiReferenceController
{
    public function __invoke(Request $request): View
    {
        $folder = (string) config('laravel-api.openapi_path', 'public/openapi');
        if (! Storage::exists($folder)) {
            Storage::makeDirectory($folder);
        }

        $sources = [];
        foreach (ApiModule::getApiVersionList() as $version => $api) {
            /** @var class-string<BaseApi> $api */
            $filePath = "{$folder}/{$version}.json";

            if (! Storage::exists($filePath) || app()->hasDebugModeEnabled()) {
                $config = $api::getOpenApiConfig($version);
                Storage::put($filePath, json_encode($config) ?: '{}');
            } else {
                $config = json_decode((string) Storage::get($filePath), true) ?: [];
            }

            $hash = @filemtime(Storage::path($filePath)) ?: time();
            $sources[] = [
                'url' => asset(Storage::url($filePath)."?r={$hash}"),
                'title' => is_array($config) && is_array($config['info'] ?? null)
                    ? (string) ($config['info']['title'] ?? $version)
                    : (string) $version,
                'slug' => DocLink::documentSlug((string) $version),
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
