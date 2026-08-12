<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource;

use Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess;
use Dskripchenko\LaravelAdmin\Table\SavedViewsController;

/**
 * Compiles ResourceRegistry into the `controllers` array of
 * AdminApi::getMethods().
 *
 * Every registered resource becomes a controller entry with the actions meta,
 * search, read, create, update and delete, plus listScreen, createScreen and
 * editScreen. Each of them is given the AdminAccess middleware automatically,
 * with the matching permission `admin.{slug}.{action}`.
 *
 * Every resource uses ONE and the same ResourceController FQCN; inside, the
 * controller works out which resource to serve through
 * `ApiRequest::getApiControllerKey()`.
 */
final class ResourceCompiler
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function compile(ResourceRegistry $registry, ?string $panel = null): array
    {
        $controllers = [];
        foreach ($registry->all($panel) as $slug => $class) {
            $resource = $registry->resolve($slug);
            if ($resource === null) {
                continue;
            }
            $controllers[$slug] = self::buildControllerEntry($resource);
            // The saved views follow a flag on the resource, as the other
            // features do: four routes per resource make sense where the list
            // really is filtered.
            if ($resource->savedViews()) {
                $controllers[$slug.'_views'] = self::buildSavedViewsEntry($resource::permission());
            }
        }

        return $controllers;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildSavedViewsEntry(string $base): array
    {
        $view = AdminAccess::class.':'.$base.'.view';

        return [
            'controller' => SavedViewsController::class,
            'actions' => [
                'list' => ['method' => ['get'], 'middleware' => [$view]],
                'create' => ['method' => ['post'], 'middleware' => [$view]],
                'update' => ['method' => ['post'], 'middleware' => [$view]],
                'delete' => ['method' => ['post'], 'middleware' => [$view]],
            ],
        ];
    }

    /**
     * The resource's actions.
     *
     * Some of them make sense only for some resources: the tree only for the
     * hierarchical ones, restore and forceDelete only with SoftDeletes.
     * Registering them across the board created routes that on most resources
     * could answer with nothing but an error, and swelled the published map of
     * the API.
     *
     * @return array<string, mixed>
     */
    private static function buildControllerEntry(Resource $resource): array
    {
        $base = $resource::permission();
        $view = AdminAccess::class.':'.$base.'.view';
        $create = AdminAccess::class.':'.$base.'.create';
        $update = AdminAccess::class.':'.$base.'.update';
        $delete = AdminAccess::class.':'.$base.'.delete';
        $restore = AdminAccess::class.':'.$base.'.restore';
        $forceDelete = AdminAccess::class.':'.$base.'.force-delete';
        $replicate = AdminAccess::class.':'.$base.'.replicate';
        $reorder = AdminAccess::class.':'.$base.'.reorder';

        $actions = [
            'meta' => ['method' => ['get'], 'middleware' => [$view]],
            'search' => ['method' => ['post'], 'middleware' => [$view]],
            'summary' => ['method' => ['post'], 'middleware' => [$view]],
            'read' => ['method' => ['get'], 'middleware' => [$view]],
            'create' => ['method' => ['post'], 'middleware' => [$create]],
            'update' => ['method' => ['post'], 'middleware' => [$update]],
            'inlineUpdate' => ['method' => ['post'], 'middleware' => [$update]],
            'replicate' => ['method' => ['post'], 'middleware' => [$replicate]],
            'reorder' => ['method' => ['post'], 'middleware' => [$reorder]],
            'export' => ['method' => ['get', 'post'], 'middleware' => [$view]],
            'delete' => ['method' => ['post'], 'middleware' => [$delete]],
            'restore' => ['method' => ['post'], 'middleware' => [$restore]],
            'forceDelete' => ['method' => ['post'], 'middleware' => [$forceDelete]],
            // The generic bulk-action dispatcher: POST /{slug}/action with a
            // body of {key, ids[], payload?}. It resolves the action out of
            // Resource->actions().
            'action' => ['method' => ['post'], 'middleware' => [$view]],
            'listScreen' => ['method' => ['get'], 'middleware' => [$view]],
            'treeScreen' => ['method' => ['get'], 'middleware' => [$view]],
            'tree' => ['method' => ['post'], 'middleware' => [$view]],
            'createScreen' => ['method' => ['get'], 'middleware' => [$create]],
            'editScreen' => ['method' => ['get'], 'middleware' => [$update]],
            'viewScreen' => ['method' => ['get'], 'middleware' => [$view]],
        ];

        if ($resource->hierarchyParentKey() === null) {
            unset($actions['tree'], $actions['treeScreen']);
        }

        if (! $resource::supportsSoftDeletes()) {
            unset($actions['restore'], $actions['forceDelete']);
        }

        if (! $resource->replicable()) {
            unset($actions['replicate']);
        }

        if (! $resource->reorderable()) {
            unset($actions['reorder']);
        }

        return [
            'controller' => ResourceController::class,
            'actions' => $actions,
        ];
    }
}
