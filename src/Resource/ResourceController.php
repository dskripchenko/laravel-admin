<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource;

use Dskripchenko\LaravelAdmin\Filter\Filter;
use Dskripchenko\LaravelAdmin\Filter\HttpFilterParser;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedCreateScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedEditScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedListScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedTreeScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedViewScreen;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Универсальный controller, обслуживающий все зарегистрированные Resource'ы.
 *
 * laravel-api для каждого зарегистрированного Resource добавляет запись в
 * `getMethods() → controllers → {slug}` со ссылкой на этот класс. Когда
 * приходит запрос `/api/admin/admin/{slug}/{action}`, laravel-api
 * инстанцирует `ResourceController` через DI. Внутри метод `currentResource()`
 * читает `ApiRequest::getApiControllerKey()` (= {slug}) и резолвит Resource
 * из ResourceRegistry.
 *
 * Реализованные actions для P1.10: meta, search, read, create, update, delete.
 * Расширенные (restore, replicate, reorder, inlineEdit, view, audit, ...) —
 * фазы P3+, см. ARCHITECTURE.md п.12.
 */
final class ResourceController extends ApiController
{
    public function __construct(private readonly ResourceRegistry $registry) {}

    /**
     * Получить метаданные ресурса (поля, колонки, фильтры, actions).
     *
     * @output object $payload Resource meta.
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {ResourceMetaResponse}
     */
    public function meta(): JsonResponse
    {
        return $this->success($this->currentResource()->meta());
    }

    /**
     * Compile GeneratedListScreen — описание list-страницы.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceListScreenResponse}
     */
    public function listScreen(): JsonResponse
    {
        return $this->success((new GeneratedListScreen($this->currentResource()))->compile());
    }

    /**
     * Compile GeneratedTreeScreen — описание tree-страницы для иерархических
     * ресурсов (с self-ref parent_id). Данные подгружаются SPA через
     * {@see tree()} action.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceTreeScreenResponse}
     */
    public function treeScreen(): JsonResponse
    {
        return $this->success((new GeneratedTreeScreen($this->currentResource()))->compile());
    }

    /**
     * Иерархическое дерево записей ресурса (self-ref parent_id).
     *
     * Возвращает уже свёрнутое дерево `data: TreeNode[]`. Применяет filters
     * и `?q=` поиск как `search()`, но без пагинации — для UI-tree-навигации
     * нужны все ветки сразу (eager-load всего адъяцентного списка одним
     * SELECT). На моделях с десятками тысяч узлов host должен либо разбивать
     * Resource на под-деревья, либо переопределить tree() в подклассе.
     *
     * @input array $filters
     * @input string $q
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceTreeResponse}
     * @response 409 {ConflictErrorResponse}
     */
    public function tree(Request $request): JsonResponse
    {
        $resource = $this->currentResource();
        $parentKey = $resource->hierarchyParentKey();
        if ($parentKey === null) {
            return $this->error([
                'errorKey' => 'not_hierarchical',
                'message' => 'Resource is not hierarchical (hierarchyParentKey() returned null)',
            ], 409);
        }

        $labelColumn = $this->resolveLabelColumn($resource);

        $query = $resource->indexQuery();

        $filterInputs = HttpFilterParser::parse($request);
        foreach ($resource->resolvedFilters() as $filter) {
            /** @var Filter $filter */
            $value = $filterInputs[$filter->field()] ?? null;
            if ($value !== null) {
                $query = $filter->apply($query, $value);
            }
        }

        $q = HttpFilterParser::searchTerm($request);
        $searchTerm = $q !== '' ? $q : null;
        $searchable = $resource->searchableFields();
        if ($searchTerm !== null && $searchable !== []) {
            $query = $query->where(function ($builder) use ($searchTerm, $searchable): void {
                foreach ($searchable as $col) {
                    $builder->orWhere($col, 'like', '%'.$searchTerm.'%');
                }
            });
        }

        $rows = $query
            ->orderBy($parentKey)
            ->orderBy($labelColumn)
            ->get()
            ->all();

        // Pre-tree hook: ресурс может потребовать дополнительные id основной
        // модели (например, предков matching leaf'ов из treeExtraLeaves —
        // см. GroupResource::treeAdditionalRowIds). Подмешиваем их в выборку
        // одним дополнительным SELECT, чтобы tree сохранял parent-цепочку.
        if ($searchTerm !== null) {
            $extraIds = $resource->treeAdditionalRowIds($searchTerm);
            if ($extraIds !== []) {
                $loadedIds = array_map(static fn ($r) => $r->getKey(), $rows);
                $missingIds = array_values(array_diff($extraIds, $loadedIds));
                if ($missingIds !== []) {
                    $keyName = $resource->indexQuery()->getModel()->getKeyName();
                    $extraRows = $resource->indexQuery()
                        ->whereIn($keyName, $missingIds)
                        ->get()
                        ->all();
                    $rows = array_merge($rows, $extraRows);
                }
            }
        }

        $extraLeaves = $resource->treeExtraLeaves($rows, $searchTerm);

        // Per-node actions (см. Resource::treeNodeActions) — собираем мапу
        // row-id → actions[]. В buildTree её цепляем к node.actions.
        $actionsByRowId = [];
        foreach ($rows as $row) {
            $actions = $resource->treeNodeActions($row);
            if ($actions !== []) {
                $actionsByRowId[$row->getKey()] = $actions;
            }
        }

        $tree = $this->buildTree($rows, $parentKey, $labelColumn, $extraLeaves, $actionsByRowId);

        return $this->success([
            'data' => $tree['nodes'],
            'meta' => [
                'total' => count($rows),
                'max_depth' => $tree['max_depth'],
                'parent_key' => $parentKey,
                'label_column' => $labelColumn,
            ],
        ]);
    }

    private function resolveLabelColumn(Resource $resource): string
    {
        foreach ($resource->columns() as $column) {
            $arr = $column->toArray();
            if (! empty($arr['searchable'])) {
                return (string) ($arr['name'] ?? 'name');
            }
        }

        return 'name';
    }

    /**
     * Свернуть плоский набор Eloquent-моделей в TreeNode[] по адъяцентному
     * списку. Узлы, чей parent отфильтрован (например, поиском по дочернему
     * лейблу), всплывают в корень — иначе матчи скрылись бы под недоступным
     * предком.
     *
     * @param  list<\Illuminate\Database\Eloquent\Model>  $rows
     * @param  array<int|string, list<array<string, mixed>>>  $extraLeaves  parent_id → leaves
     * @param  array<int|string, list<array<string, mixed>>>  $actionsByRowId  row_id → actions
     * @return array{nodes: list<array<string, mixed>>, max_depth: int}
     */
    private function buildTree(array $rows, string $parentKey, string $labelColumn, array $extraLeaves = [], array $actionsByRowId = []): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $id = $row->getKey();
            $byId[$id] = [
                'key' => $id,
                'label' => (string) ($row->getAttribute($labelColumn) ?? ''),
                'record' => $row->toArray(),
                'children' => [],
            ];
            if (isset($actionsByRowId[$id])) {
                $byId[$id]['actions'] = $actionsByRowId[$id];
            }
        }

        $roots = [];
        $maxDepth = 0;
        foreach ($rows as $row) {
            $id = $row->getKey();
            $pid = $row->getAttribute($parentKey);
            if ($pid !== null && isset($byId[$pid])) {
                $byId[$pid]['children'][] = &$byId[$id];
            } else {
                $roots[] = &$byId[$id];
            }
        }

        // Дополнительные leaf-узлы из treeExtraLeaves (например шаблоны под
        // группами). Добавляем после основных children, чтобы группы шли первыми.
        if ($extraLeaves !== []) {
            foreach ($byId as $id => &$node) {
                if (isset($extraLeaves[$id])) {
                    foreach ($extraLeaves[$id] as $leaf) {
                        $node['children'][] = $leaf;
                    }
                }
            }
            unset($node);
        }

        $assignDepth = function (array &$node, int $depth) use (&$assignDepth, &$maxDepth): void {
            $maxDepth = max($maxDepth, $depth);
            if (empty($node['children'])) {
                unset($node['children']);

                return;
            }
            foreach ($node['children'] as &$child) {
                $assignDepth($child, $depth + 1);
            }
        };
        foreach ($roots as &$root) {
            $assignDepth($root, 0);
        }
        unset($root);

        return ['nodes' => $roots, 'max_depth' => $maxDepth];
    }

    /**
     * Compile GeneratedCreateScreen.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceCreateScreenResponse}
     */
    public function createScreen(): JsonResponse
    {
        return $this->success((new GeneratedCreateScreen($this->currentResource()))->compile());
    }

    /**
     * Compile GeneratedEditScreen с подгрузкой записи по id.
     *
     * @input integer $id
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceEditScreenResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function editScreen(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if ($id === null) {
            return $this->error([
                'errorKey' => 'validation',
                'message' => 'id is required',
            ], 422);
        }

        $screen = new GeneratedEditScreen($this->currentResource());

        try {
            return $this->success($screen->compile($id));
        } catch (NotFoundHttpException) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'Record not found',
            ], 404);
        }
    }

    /**
     * Compile GeneratedViewScreen — read-only display через Infolist.
     *
     * @input integer $id
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceViewScreenResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function viewScreen(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if ($id === null) {
            return $this->error([
                'errorKey' => 'validation',
                'message' => 'id is required',
            ], 422);
        }

        $screen = new GeneratedViewScreen($this->currentResource());
        try {
            return $this->success($screen->compile($id));
        } catch (NotFoundHttpException) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'Record not found',
            ], 404);
        }
    }

    /**
     * Получить список записей с filters/sort/pagination.
     *
     * @input integer ?$page
     * @input integer ?$per_page
     * @input array ?$filters
     * @input string ?$q
     * @input array ?$order
     *
     * @output object $payload
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {ResourceSearchResponse}
     */
    public function search(Request $request): JsonResponse
    {
        $resource = $this->currentResource();
        $query = $resource->indexQuery();

        // Filters: { filters: [{column, operator, value}] } либо { filters: { col: value } }
        $filterInputs = HttpFilterParser::parse($request);
        foreach ($resource->resolvedFilters() as $filter) {
            /** @var Filter $filter */
            $value = $filterInputs[$filter->field()] ?? null;
            if ($value !== null) {
                $query = $filter->apply($query, $value);
            }
        }

        // Free-text search by ?q=...
        $q = HttpFilterParser::searchTerm($request);
        $searchable = $resource->searchableFields();
        if ($q !== '' && $searchable !== []) {
            $query = $query->where(function ($builder) use ($q, $searchable): void {
                foreach ($searchable as $col) {
                    $builder->orWhere($col, 'like', '%'.$q.'%');
                }
            });
        }

        // Order. When the request carries no explicit order, fall back to
        // either the reorder column (for resources that support drag-n-drop
        // reordering — keeps the manual sequence stable) or the resource's
        // defaultOrder() — typically PK DESC so newest rows surface first.
        $orders = (array) $request->input('order', []);
        $orders = array_values(array_filter(
            $orders,
            static fn ($o): bool => is_array($o) && isset($o['column']),
        ));
        if ($orders === []) {
            $orders = $resource->reorderable()
                ? [['column' => $resource->reorderColumn(), 'direction' => 'asc']]
                : $resource->defaultOrder();
        }
        foreach ($orders as $order) {
            $direction = ($order['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            $query = $query->orderBy((string) $order['column'], $direction);
        }

        // Pagination
        $perPage = (int) $request->input('per_page', (int) config('admin.pagination.default_per_page', 25));
        $perPage = max(1, min($perPage, (int) config('admin.pagination.max_per_page', 100)));
        $page = max(1, (int) $request->input('page', 1));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Group-by: если передан group_by — собираем counts по уникальным значениям.
        // Pagination над group'ами не делаем — фронт получает все group'ы для текущего фильтра.
        $groups = null;
        $groupBy = (string) $request->input('group_by', '');
        if ($groupBy !== '') {
            $groupQuery = $resource->indexQuery();
            $filterInputs = HttpFilterParser::parse($request);
            foreach ($resource->resolvedFilters() as $filter) {
                $value = $filterInputs[$filter->field()] ?? null;
                if ($value !== null) {
                    $groupQuery = $filter->apply($groupQuery, $value);
                }
            }
            $groups = $groupQuery
                ->select($groupBy, \Illuminate\Support\Facades\DB::raw('COUNT(*) as aggregate_count'))
                ->groupBy($groupBy)
                ->get()
                ->map(static fn ($row): array => [
                    'value' => $row->getAttribute($groupBy),
                    'count' => (int) $row->getAttribute('aggregate_count'),
                ])
                ->all();
        }

        $items = $this->withPerRowEditable($resource, $paginator->items());

        return $this->success([
            'data' => $items,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'summary' => null,
                'groups' => $groups,
            ],
        ]);
    }

    /**
     * Для каждой row + каждой editable-колонки спрашивает Resource::editableForRow.
     * Если хоть один override === false — добавляет `_editable` map в данные row.
     * Row без override-флагов остаётся как есть.
     *
     * @param  list<\Illuminate\Database\Eloquent\Model>  $items
     * @return list<array<string, mixed>>
     */
    private function withPerRowEditable(Resource $resource, array $items): array
    {
        $editableColumns = [];
        foreach ($resource->columns() as $column) {
            $arr = $column->toArray();
            if (! empty($arr['editable'])) {
                $editableColumns[] = (string) $arr['name'];
            }
        }
        if ($editableColumns === []) {
            return array_map(static fn ($m) => $m->toArray(), $items);
        }

        $out = [];
        foreach ($items as $model) {
            $row = $model->toArray();
            $overrides = [];
            foreach ($editableColumns as $col) {
                if (! $resource->editableForRow($model, $col)) {
                    $overrides[$col] = false;
                }
            }
            if ($overrides !== []) {
                $row['_editable'] = $overrides;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Получить одну запись по id.
     *
     * @input integer $id
     *
     * @output object $payload
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {ResourceReadResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function read(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if ($id === null) {
            return $this->error([
                'errorKey' => 'validation',
                'message' => 'id is required',
            ], 422);
        }

        $resource = $this->currentResource();
        $record = $resource->modelQuery()->find($id);

        if ($record === null) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'Record not found',
            ], 404);
        }

        return $this->success([
            'record' => $resource->transformRecord($record),
            'state' => $resource->transformRecord($record),
        ]);
    }

    /**
     * Создать запись.
     *
     * Конкретные `@input`-поля определяются Resource::fields().
     *
     * @output object $payload
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 201 {ResourceCreatedResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function create(Request $request): JsonResponse
    {
        $resource = $this->currentResource();
        $data = $request->validate($this->flattenRules($resource->validationRules('create')));
        $data = $this->sanitizeWysiwyg($resource, $data);
        $translations = \Dskripchenko\LaravelAdmin\Theme\TranslatableFieldBridge::extract(
            $resource->fields(),
            $data,
        );

        $modelClass = $resource::$model;
        /** @var \Illuminate\Database\Eloquent\Model $record */
        $record = new $modelClass;
        try {
            $resource->fillModel($record, $data);
            $record->save();
        } catch (QueryException $e) {
            return $this->error($this->dbExceptionToValidation($e), 422);
        }
        \Dskripchenko\LaravelAdmin\Theme\TranslatableFieldBridge::saveAll($record, $translations);

        return $this->created([
            'record' => $resource->transformRecord($record),
            'redirect_url' => '/admin/r/'.$resource::slug().'/'.$record->getKey(),
            'message' => 'Created',
        ]);
    }

    /**
     * Обновить запись.
     *
     * @input integer $id
     *
     * @output object $payload
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {ResourceUpdatedResponse}
     * @response 404 {NotFoundErrorResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function update(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if ($id === null) {
            return $this->error(['errorKey' => 'validation', 'message' => 'id is required'], 422);
        }

        $resource = $this->currentResource();
        $record = $resource->modelQuery()->find($id);
        if ($record === null) {
            return $this->error(['errorKey' => 'not_found', 'message' => 'Record not found'], 404);
        }

        $data = $request->validate($this->withUniqueIgnore(
            $this->flattenRules($resource->validationRules('update')),
            $record->getKey(),
        ));
        $data = $this->sanitizeWysiwyg($resource, $data);
        $translations = \Dskripchenko\LaravelAdmin\Theme\TranslatableFieldBridge::extract(
            $resource->fields(),
            $data,
        );
        try {
            $resource->fillModel($record, $data);
            $record->save();
        } catch (QueryException $e) {
            return $this->error($this->dbExceptionToValidation($e), 422);
        }
        \Dskripchenko\LaravelAdmin\Theme\TranslatableFieldBridge::saveAll($record, $translations);

        return $this->success([
            'record' => $resource->transformRecord($record),
            'state' => $resource->transformRecord($record),
            'message' => 'Updated',
        ]);
    }

    /**
     * Потоковый CSV-экспорт списка с применением текущих filters.
     *
     * Backward-compat alias для `export(format=csv)`. Новый код должен
     * использовать `export` action с явным форматом.
     *
     * @output file CSV
     *
     * @security AdminSession
     *
     * @response 200 {ResourceCsvExportResponse}
     */
    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->merge(['format' => 'csv']);

        return $this->export($request);
    }

    /**
     * Универсальный потоковый экспорт списка в любой зарегистрированный формат.
     *
     * @input string ?$format  csv|xlsx|pdf — default csv. Должен быть
     *                          зарегистрирован в ExporterRegistry.
     * @input array ?$filters
     * @input string ?$q
     * @input array ?$columns
     *
     * @output file
     *
     * @security AdminSession
     *
     * @response 200 {ResourceExportResponse}
     * @response 422 {ValidationErrorResponse} Format не поддержан.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $resource = $this->currentResource();
        $format = (string) $request->input('format', 'csv');

        /** @var \Dskripchenko\LaravelAdmin\Export\ExporterRegistry $registry */
        $registry = app(\Dskripchenko\LaravelAdmin\Export\ExporterRegistry::class);
        if (! $registry->has($format)) {
            return $this->error([
                'errorKey' => 'unsupported_format',
                'message' => "Format `{$format}` is not registered. Available: ".implode(', ', $registry->formats()),
            ], 422);
        }

        $query = $resource->indexQuery();

        $filterInputs = HttpFilterParser::parse($request);
        foreach ($resource->resolvedFilters() as $filter) {
            $value = $filterInputs[$filter->field()] ?? null;
            if ($value !== null) {
                $query = $filter->apply($query, $value);
            }
        }

        $q = HttpFilterParser::searchTerm($request);
        $searchable = $resource->searchableFields();
        if ($q !== '' && $searchable !== []) {
            $query = $query->where(function ($builder) use ($q, $searchable): void {
                foreach ($searchable as $col) {
                    $builder->orWhere($col, 'like', '%'.$q.'%');
                }
            });
        }

        $requested = (array) $request->input('columns', []);
        $columns = [];
        foreach ($resource->columns() as $col) {
            $arr = $col->toArray();
            if ($arr['defaultHidden'] && $requested === []) {
                continue;
            }
            if ($requested !== [] && ! in_array($col->name(), $requested, true)) {
                continue;
            }
            $columns[$col->name()] = (string) ($arr['label'] ?? $col->name());
        }

        // Generator chunks для memory-friendly экспорта.
        $rowGenerator = (function () use ($query): \Generator {
            foreach ($query->cursor() as $model) {
                yield $model->toArray();
            }
        })();

        return $registry->get($format)->export(
            $rowGenerator,
            $columns,
            $resource::slug().'-'.date('Y-m-d-His'),
        );
    }

    /**
     * Drag-n-drop reorder: bulk update позиций нескольких записей в одной
     * транзакции.
     *
     * Принимает `items: [{id, position}]`. Resource должен иметь
     * `reorderable() === true` и `reorderColumn()` колонку.
     *
     * @input array $items
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceReorderedResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function action(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
            'key' => ['required', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        $resource = $this->currentResource();
        $actionKey = $data['key'];

        // Ищем Action с name == $actionKey среди Resource->actions().
        $action = null;
        foreach ($resource->actions() as $a) {
            if ($a->name() === $actionKey) {
                $action = $a;
                break;
            }
        }
        if ($action === null) {
            return $this->error([
                'errorKey' => 'unknown_action',
                'message' => "Action `{$actionKey}` not declared on resource",
            ], 404);
        }

        // Resolve method'а на самом resource'е (BulkAction->method('archive')).
        $methodName = $action->toArray()['attributes']['method'] ?? null;
        if (! is_string($methodName) || ! method_exists($resource, $methodName)) {
            return $this->error([
                'errorKey' => 'action_not_implemented',
                'message' => "Method `{$methodName}` not found on resource",
            ], 501);
        }

        // Вызов: $resource->{method}(array $ids, array $payload).
        try {
            $result = $resource->{$methodName}($data['ids'], (array) ($data['payload'] ?? []));
        } catch (\Throwable $e) {
            return $this->error([
                'errorKey' => 'action_failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        return $this->success([
            'affected' => is_int($result) ? $result : count($data['ids']),
            'message' => 'Action `'.$actionKey.'` applied',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required'],
            'items.*.position' => ['required', 'integer', 'min:0'],
        ]);

        $resource = $this->currentResource();
        if (! $resource->reorderable()) {
            return $this->error([
                'errorKey' => 'validation',
                'message' => 'Resource is not reorderable',
            ], 422);
        }

        $column = $resource->reorderColumn();
        $modelClass = $resource::$model;

        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $modelClass, $column): void {
            foreach ($data['items'] as $item) {
                $modelClass::query()
                    ->whereKey($item['id'])
                    ->update([$column => (int) $item['position']]);
            }
        });

        return $this->success([
            'count' => count($data['items']),
            'message' => 'Reordered',
        ]);
    }

    /**
     * Клонировать запись.
     *
     * @input integer $id
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceReplicatedResponse}
     * @response 404 {NotFoundErrorResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function replicate(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required']]);
        $resource = $this->currentResource();

        if (! $resource->replicable()) {
            return $this->error([
                'errorKey' => 'validation',
                'message' => 'Resource is not replicable',
            ], 422);
        }

        $original = $resource->modelQuery()->find($data['id']);
        if ($original === null) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'Record not found',
            ], 404);
        }

        $copy = $resource->replicate($original);
        $copy->save();

        return $this->success([
            'record' => $resource->transformRecord($copy),
            'redirect_url' => '/admin/r/'.$resource::slug().'/'.$copy->getKey().'/edit',
            'message' => 'Replicated',
        ]);
    }

    /**
     * Восстановить soft-deleted запись.
     *
     * @input integer $id
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceRestoredResponse}
     * @response 404 {NotFoundErrorResponse}
     * @response 422 {ValidationErrorResponse} Resource не поддерживает SoftDeletes.
     */
    public function restore(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required']]);
        $resource = $this->currentResource();

        if (! $resource::supportsSoftDeletes()) {
            return $this->error([
                'errorKey' => 'validation',
                'message' => 'Resource does not support soft-delete',
            ], 422);
        }

        /** @var \Illuminate\Database\Eloquent\Model|null $record */
        $record = $this->withTrashedQuery($resource)->find($data['id']);
        if ($record === null) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'Record not found',
            ], 404);
        }

        $deletedAtColumn = method_exists($record, 'getDeletedAtColumn')
            ? $record->getDeletedAtColumn()
            : 'deleted_at';
        $record->setAttribute($deletedAtColumn, null);
        $record->save();

        return $this->success([
            'record' => $resource->transformRecord($record),
            'message' => 'Restored',
        ]);
    }

    /**
     * Окончательное удаление soft-deleted записи.
     *
     * @input integer $id
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceForceDeletedResponse}
     * @response 404 {NotFoundErrorResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function forceDelete(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required']]);
        $resource = $this->currentResource();

        if (! $resource::supportsSoftDeletes()) {
            return $this->error([
                'errorKey' => 'validation',
                'message' => 'Resource does not support soft-delete',
            ], 422);
        }

        $record = $this->withTrashedQuery($resource)->find($data['id']);
        if ($record === null) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'Record not found',
            ], 404);
        }

        $record->forceDelete();

        return $this->success([
            'id' => $data['id'],
            'message' => 'Force deleted',
        ]);
    }

    /**
     * Builder с включёнными trashed для restore/forceDelete.
     *
     * SoftDeletes trait добавляет SoftDeletingScope глобально; снимаем его,
     * чтобы запрос видел и trashed-записи. Это эквивалент `->withTrashed()`,
     * но не требует scope-magic methods на Builder'е.
     */
    private function withTrashedQuery(Resource $resource): \Illuminate\Database\Eloquent\Builder
    {
        return $resource->modelQuery()
            ->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class);
    }

    /**
     * Удалить запись (soft, если SoftDeletes; иначе hard).
     *
     * @input integer $id
     *
     * @output object $payload
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {ResourceDeletedResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function delete(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if ($id === null) {
            return $this->error(['errorKey' => 'validation', 'message' => 'id is required'], 422);
        }

        $resource = $this->currentResource();
        $record = $resource->modelQuery()->find($id);
        if ($record === null) {
            return $this->error(['errorKey' => 'not_found', 'message' => 'Record not found'], 404);
        }

        $record->delete();

        return $this->success([
            'record' => $resource->transformRecord($record),
            'message' => 'Deleted',
        ]);
    }

    /**
     * Inline-edit одной ячейки в таблице.
     *
     * Принимает {id, column, value}; ищет column в Resource::columns(), берёт
     * editable.validation rules, валидирует, обновляет одну колонку. Если
     * column не editable — 422.
     *
     * @input integer $id
     * @input string $column
     * @input any $value
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceInlineUpdatedResponse}
     * @response 404 {NotFoundErrorResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function inlineUpdate(Request $request): JsonResponse
    {
        $base = $request->validate([
            'id' => ['required'],
            'column' => ['required', 'string'],
        ]);

        $resource = $this->currentResource();
        $columnName = (string) $base['column'];
        $columnConfig = $this->findEditableColumn($resource, $columnName);

        if ($columnConfig === null) {
            return $this->error([
                'errorKey' => 'validation',
                'message' => "Column `{$columnName}` is not editable",
            ], 422);
        }

        $rules = is_array($columnConfig['validation'] ?? null) ? $columnConfig['validation'] : [];
        /** @var array<string, list<string|array<string, mixed>>> $rulesMap */
        $rulesMap = ['value' => $rules];
        $validated = $request->validate($rulesMap);

        $record = $resource->modelQuery()->find($base['id']);
        if ($record === null) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'Record not found',
            ], 404);
        }

        $record->forceFill([$columnName => $validated['value'] ?? null])->save();

        return $this->success([
            'record' => $resource->transformRecord($record),
            'column' => $columnName,
            'value' => $record->getAttribute($columnName),
        ]);
    }

    /**
     * Summary-агрегаты по текущему фильтру (sum/avg/count/min/max).
     *
     * Возвращает map column => {sum?, avg?, ...} по тем колонкам, у которых
     * Resource::columns()->summary([...]) объявлен.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceSummaryResponse}
     */
    public function summary(Request $request): JsonResponse
    {
        $resource = $this->currentResource();
        $query = $resource->indexQuery();

        // Применяем те же filters, что и в search.
        $filterInputs = HttpFilterParser::parse($request);
        foreach ($resource->resolvedFilters() as $filter) {
            $value = $filterInputs[$filter->field()] ?? null;
            if ($value !== null) {
                $query = $filter->apply($query, $value);
            }
        }

        $result = [];
        foreach ($resource->columns() as $col) {
            $aggregates = $col->toArray()['summary'] ?? null;
            if (! is_array($aggregates) || $aggregates === []) {
                continue;
            }

            $name = $col->name();
            $values = [];
            foreach ($aggregates as $agg) {
                $values[(string) $agg] = $this->aggregate(clone $query, $name, (string) $agg);
            }
            $result[$name] = $values;
        }

        return $this->success(['summary' => $result]);
    }

    /**
     * Найти конфиг editable для колонки. Возвращает null если колонки
     * нет или она не editable.
     *
     * @return array<string, mixed>|null
     */
    private function findEditableColumn(Resource $resource, string $name): ?array
    {
        foreach ($resource->columns() as $col) {
            if ($col->name() !== $name) {
                continue;
            }
            $config = $col->toArray()['editable'] ?? null;
            if (is_array($config)) {
                return $config;
            }
        }

        return null;
    }

    /**
     * @return float|int|array{min: mixed, max: mixed}|null
     */
    private function aggregate(\Illuminate\Contracts\Database\Eloquent\Builder $query, string $column, string $agg): mixed
    {
        return match ($agg) {
            'sum' => (float) $query->sum($column),
            'avg' => (float) $query->avg($column),
            'count' => $query->count($column),
            'min' => $query->min($column),
            'max' => $query->max($column),
            'range' => ['min' => $query->min($column), 'max' => $query->max($column)],
            default => null,
        };
    }

    /* -----------------------------------------------------------------
     * Internals
     * ----------------------------------------------------------------- */

    private function currentResource(): Resource
    {
        /** @var string|null $key */
        $key = ApiRequest::getApiControllerKey();
        $slug = (string) ($key ?? '');
        $resource = $this->registry->resolve($slug, \Dskripchenko\LaravelAdmin\Panel\Panels::current()->id);
        if ($resource === null) {
            throw new NotFoundHttpException("Resource `{$slug}` is not registered");
        }

        return $resource;
    }

    /**
     * Маппит SQL-exception в human-friendly validation-payload.
     * Покрывает: 23505 (unique), 23502 (not-null), 23503 (FK). По умолчанию
     * — generic «Не удалось сохранить запись».
     *
     * @return array<string, mixed>
     */
    private function dbExceptionToValidation(QueryException $e): array
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $message = (string) ($e->errorInfo[2] ?? $e->getMessage());

        $errors = [];

        // 23505 — duplicate key
        if ($sqlState === '23505') {
            // Postgres: "Key (group_id, key)=(7, foo) already exists"
            // MySQL:    "Duplicate entry 'foo' for key 'table.field_unique'"
            if (preg_match('/Key \(([^)]+)\)=\(([^)]+)\)/', $message, $m)) {
                $cols = array_map('trim', explode(',', $m[1]));
                foreach ($cols as $col) {
                    $errors[$col] = ['Уже существует запись с таким значением'];
                }
            } elseif (preg_match("/for key '[^']*\\.([^_']+)/", $message, $m)) {
                $errors[$m[1]] = ['Уже существует запись с таким значением'];
            }

            return [
                'errorKey' => 'unique_violation',
                'message' => 'Запись с такими данными уже существует',
                'messages' => $errors !== [] ? $errors : (object) [],
            ];
        }

        // 23502 — not null violation
        if ($sqlState === '23502') {
            if (preg_match('/column "([^"]+)"/', $message, $m)) {
                $errors[$m[1]] = ['Поле обязательно для заполнения'];
            }

            return [
                'errorKey' => 'not_null_violation',
                'message' => 'Не заполнено обязательное поле',
                'messages' => $errors !== [] ? $errors : (object) [],
            ];
        }

        // 23503 — foreign key violation
        if ($sqlState === '23503') {
            if (preg_match('/foreign key constraint.*"([^"]+)"/i', $message, $m)) {
                $errors['_'] = ["FK constraint violation: {$m[1]}"];
            }

            return [
                'errorKey' => 'foreign_key_violation',
                'message' => 'Нарушена связь с другой записью (запись используется или ссылка некорректна)',
                'messages' => $errors !== [] ? $errors : (object) [],
            ];
        }

        // Fallback: не показываем raw SQL в prod-mode
        $userMessage = config('app.debug')
            ? "DB error [{$sqlState}]: {$message}"
            : 'Не удалось сохранить запись. Обратитесь к администратору.';

        return [
            'errorKey' => 'db_error',
            'message' => $userMessage,
            'messages' => (object) [],
        ];
    }

    /**
     * @param  array<string, array<int, string|array<string, mixed>>>  $rules
     * @return array<string, array<int, string|array<string, mixed>>>
     */
    private function flattenRules(array $rules): array
    {
        return $rules;
    }

    /**
     * На update unique-правила без явного except исключают текущую запись —
     * иначе запись конфликтует сама с собой. Поддержаны строковая форма
     * (`unique:table,column`) и Rule::unique() объекты.
     *
     * @param  array<string, list<mixed>>  $rulesByField
     * @return array<string, list<mixed>>
     */
    private function withUniqueIgnore(array $rulesByField, mixed $id): array
    {
        foreach ($rulesByField as $field => $rules) {
            foreach ($rules as $i => $rule) {
                if ($rule instanceof \Illuminate\Validation\Rules\Unique) {
                    $rulesByField[$field][$i] = (clone $rule)->ignore($id);

                    continue;
                }
                if (is_string($rule) && str_starts_with($rule, 'unique:')) {
                    $parts = explode(',', substr($rule, strlen('unique:')));
                    if (count($parts) < 3) {
                        $target = $parts[0];
                        $column = $parts[1] ?? (string) $field;
                        $rulesByField[$field][$i] = "unique:{$target},{$column},{$id}";
                    }
                }
            }
        }

        return $rulesByField;
    }

    /**
     * Прогоняет HTML через HtmlSanitizer для всех Wysiwyg-полей,
     * у которых shouldSanitize() = true.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeWysiwyg(Resource $resource, array $data): array
    {
        $sanitizer = null;
        foreach ($resource->fields() as $field) {
            if (! $field instanceof \Dskripchenko\LaravelAdmin\Field\Wysiwyg) {
                continue;
            }
            if (! $field->shouldSanitize()) {
                continue;
            }
            $name = $field->name();
            if (! array_key_exists($name, $data) || ! is_string($data[$name])) {
                continue;
            }
            $sanitizer ??= new \Dskripchenko\LaravelAdmin\Uploads\HtmlSanitizer;
            $data[$name] = $sanitizer->sanitize($data[$name]);
        }

        return $data;
    }
}
