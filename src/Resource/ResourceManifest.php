<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource;

use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedCreateScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedEditScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedListScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedViewScreen;

/**
 * Сериализация Resource'а в manifest-entry.
 *
 * Дополняет `Resource::meta()` блоком `screens` — slugs трёх авто-сгенерированных
 * страниц (list/create/edit) с их permission-маппингом. SPA использует эти
 * slugs для построения роутов /admin/resources/{slug} → list, /create, /{id}/edit.
 *
 * Этот класс намеренно отдельный, чтобы Resource не разрастался UI-логикой.
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
