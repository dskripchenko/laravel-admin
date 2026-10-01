<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

/**
 * The prebuilt admin application shipped in the package's public/ directory.
 *
 * `admin:install` (or `admin:publish`) copies it to the host's
 * public/vendor/admin, and the shell loads it whenever the host has not
 * configured a Vite build of its own — so the admin works right after
 * `composer require`, with no Node and no build step.
 */
final class PrebuiltAssets
{
    /** The Vite entry the prebuilt bundle is built from. */
    public const ENTRY = 'resources/ts/app.ts';

    /** Where the bundle lives inside the package. */
    public static function sourcePath(): string
    {
        return dirname(__DIR__, 2).'/public';
    }

    /** Where it is published in the host. */
    public static function publishedPath(): string
    {
        return public_path('vendor/admin');
    }

    public static function isPublished(): bool
    {
        return is_file(self::manifest(self::publishedPath()));
    }

    /**
     * Whether the published copy differs from the one this package version
     * ships — the host updated the package but did not republish.
     */
    public static function isStale(): bool
    {
        $source = self::manifest(self::sourcePath());
        $published = self::manifest(self::publishedPath());
        if (! is_file($source) || ! is_file($published) || is_link(self::publishedPath())) {
            return false;
        }

        return hash_file('xxh128', $source) !== hash_file('xxh128', $published);
    }

    /** @return array{css: list<string>, js: list<string>}|null */
    public static function resolve(): ?array
    {
        return ViteManifest::resolve(self::manifest(self::publishedPath()), self::ENTRY, asset('vendor/admin'));
    }

    private static function manifest(string $dir): string
    {
        return $dir.'/.vite/manifest.json';
    }
}
