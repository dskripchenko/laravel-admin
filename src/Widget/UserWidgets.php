<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;

/**
 * The checks on the widgets a user added in the SPA, which live only in the
 * saved layout (`type` + `config`) and have no class on the server.
 *
 * A kanban or calendar widget over a resource is kept only while its
 * resource is registered in the current panel, declares that view and may be
 * viewed by the user — the same rule as KanbanWidget / CalendarWidget's
 * resource mode. Its data goes through the resource's own endpoints, which
 * check the permission again; dropping the item keeps a layout from naming a
 * resource the user may not see.
 */
final class UserWidgets
{
    /** The widget types whose user config may name a resource. */
    public const RESOURCE_TYPES = ['kanban', 'calendar'];

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function filter(array $items): array
    {
        return array_values(array_filter($items, self::allows(...)));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function allows(array $item): bool
    {
        $type = $item['type'] ?? null;
        $config = $item['config'] ?? null;
        if (! is_string($type) || ! in_array($type, self::RESOURCE_TYPES, true) || ! is_array($config)) {
            return true;
        }
        $resource = $config['resource'] ?? null;
        if (($config['mode'] ?? null) === 'markup' || ! is_string($resource) || $resource === '') {
            return true;
        }

        return self::resourceAvailable($resource, $type);
    }

    /**
     * Whether a resource, by slug, is registered in the current panel,
     * declares the view ('kanban' or 'calendar') and may be viewed by the
     * current user.
     */
    public static function resourceAvailable(string $slug, string $view): bool
    {
        $resource = app(ResourceRegistry::class)->resolve($slug, Panels::current()->id);
        if ($resource === null) {
            return false;
        }
        $declared = match ($view) {
            'kanban' => $resource->kanban() !== null,
            'calendar' => $resource->calendar() !== null,
            default => false,
        };

        return $declared && PermissionCheck::allows($resource::permission().'.view');
    }
}
