<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Замена для default ApiDocumentationController из laravel-api.
 *
 * Default — Swagger UI; здесь — Scalar UI (более современный, читаемый,
 * с поддержкой dark mode из коробки). Скрипт подгружается с CDN
 * (jsdelivr) лениво.
 *
 * OpenAPI spec собирается стандартным laravel-api механизмом: для каждой
 * версии — `BaseApi::getOpenApiConfig` пишется в storage, отдаётся URL.
 */
final class ScalarDocController
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
            $fileName = "{$version}.json";
            $filePath = "{$folder}/{$fileName}";

            if (! Storage::exists($filePath) || app()->hasDebugModeEnabled()) {
                $config = $api::getOpenApiConfig($version);
                Storage::put($filePath, json_encode($config) ?: '{}');
            } else {
                $config = json_decode((string) Storage::get($filePath), true) ?: [];
            }

            $urlPath = Storage::url($filePath);
            $hash = @filemtime(Storage::path($filePath)) ?: time();
            $sources[] = [
                'url' => asset("{$urlPath}?r={$hash}"),
                'title' => is_array($config) && is_array($config['info'] ?? null)
                    ? (string) ($config['info']['title'] ?? $version)
                    : $version,
                'slug' => (string) $version,
            ];
        }

        /** @var view-string $view */
        $view = 'admin::scalar-doc';

        return view($view, [
            'sources' => $sources,
            'theme' => (string) config('admin.openapi.scalar_theme', 'default'),
            'cspNonce' => $request->attributes->get('admin.csp_nonce'),
        ]);
    }
}
