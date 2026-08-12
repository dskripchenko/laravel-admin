<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource;

use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedCreateScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedEditScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedListScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedViewScreen;

/**
 * Serializes a resource into its manifest entry.
 *
 * It extends `Resource::meta()` with a `screens` block — the slugs of the three
 * generated pages (list, create, edit) and their permission mapping. The SPA
 * builds the /admin/resources/{slug} → list, /create and /{id}/edit routes out
 * of those slugs.
 *
 * The class is deliberately separate, so that Resource does not swell with UI
 * logic.
 */
final class ResourceManifest
{
    /**
     * @return array<string, mixed>
     */
    public static function describe(Resource $resource): array
    {
        $base = $resource->meta();

        return [
            ...$base,
            'screens' => [
                'list' => self::screenEntry(new GeneratedListScreen($resource)),
                'create' => self::screenEntry(new GeneratedCreateScreen($resource)),
                'edit' => self::screenEntry(new GeneratedEditScreen($resource)),
                'view' => self::screenEntry(new GeneratedViewScreen($resource)),
            ],
        ];
    }

    /**
     * @return array{slug: string, type: string, permission: list<string>|string|null, name: string}
     */
    private static function screenEntry(Screens\GeneratedScreen $screen): array
    {
        return [
            'slug' => $screen->instanceSlug(),
            'type' => 'generated.'.$screen->kind(),
            'permission' => $screen->permission(),
            'name' => $screen->name(),
        ];
    }
}
