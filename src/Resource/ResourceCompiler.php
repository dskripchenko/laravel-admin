<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource;

use Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess;
use Dskripchenko\LaravelAdmin\Table\SavedViewsController;

/**
 * Компилирует ResourceRegistry в массив `controllers` для AdminApi::getMethods().
 *
 * Каждый зарегистрированный Resource превращается в controller-entry с
 * actions: meta/search/read/create/update/delete + listScreen/createScreen/
 * editScreen. Каждому action автоматически привязывается AdminAccess
 * middleware с соответствующим permission'ом — `admin.{slug}.{action}`.
 *
 * Все Resource'ы используют ОДИН и тот же FQCN ResourceController. Внутри
 * controller через `ApiRequest::getApiControllerKey()` определяет, какой
 * именно Resource обслужить.
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
            // Сохранённые представления — по флагу ресурса, как остальные
            // возможности: четыре маршрута на ресурс имеют смысл там, где
            // список действительно фильтруют.
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
     * Actions ресурса.
     *
     * Часть действий имеет смысл не для каждого ресурса: дерево — только для
     * иерархических, restore/forceDelete — только при SoftDeletes. Регистрируя
     * их поголовно, мы заводили роуты, которые на большинстве ресурсов могли
     * ответить только ошибкой, и раздували публикуемую карту API.
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
            // Generic bulk-action dispatcher: POST /{slug}/action body
            // {key, ids[], payload?}. Резолвит Action из Resource->actions().
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
