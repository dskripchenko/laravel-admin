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
 * The single controller serving every registered resource.
 *
 * For each registered resource laravel-api adds an entry to
 * `getMethods() → controllers → {slug}` pointing at this class. When a request
 * arrives at `/api/admin/admin/{slug}/{action}`, laravel-api instantiates
 * `ResourceController` through DI; inside, `currentResource()` reads
 * `ApiRequest::getApiControllerKey()` (which is the slug) and resolves the
 * resource from ResourceRegistry.
 *
 * Actions implemented in P1.10: meta, search, read, create, update, delete.
 * The extended ones — restore, replicate, reorder, inlineEdit, view, audit and
 * so on — belong to P3 and later; see ARCHITECTURE.md §12.
 */
final class ResourceController extends ApiController
{
    public function __construct(private readonly ResourceRegistry $registry) {}

    /**
     * Returns the resource metadata: fields, columns, filters, actions.
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
     * Compiles GeneratedListScreen — the description of the list page.
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
     * The description of the resource's tree page.
     *
     * Returns the layout of the hierarchical list; the nodes themselves are
     * fetched by the separate `tree` action.
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
     * The hierarchical tree of the resource's records, by self-referencing
     * parent_id.
     *
     * Returns an already assembled tree as `data: TreeNode[]`. Filters and the
     * `?q=` search apply exactly as in `search()`, but there is no pagination:
     * tree navigation needs every branch at once, so the whole adjacency list
     * is loaded in a single SELECT. On models with tens of thousands of nodes
     * a host should either split the resource into sub-trees or override
     * `tree()` in a subclass.
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

        // Pre-tree hook: a resource may ask for extra ids of the main model —
        // the ancestors of matching leaves from treeExtraLeaves, say; see
        // GroupResource::treeAdditionalRowIds. They are mixed into the
        // selection with one more SELECT so that the tree keeps its parent
        // chain intact.
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

        // Per-node actions (see Resource::treeNodeActions): a map of row id →
        // actions, attached to node.actions inside buildTree.
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
     * Folds a flat set of Eloquent models into TreeNode[] by their adjacency
     * list. A node whose parent was filtered out — by a search matching a
     * child's label, for instance — floats up to the root; otherwise the match
     * would hide under an ancestor that is not there.
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

        // Extra leaves from treeExtraLeaves — templates under groups, say.
        // They come after the real children so that groups are listed first.
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
     * Compiles GeneratedEditScreen, loading the record by id.
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
     * Compiles GeneratedViewScreen — the read-only display built on Infolist.
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
     * Returns a list of records with filters, sorting and pagination.
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

        // Filters arrive either as { filters: [{column, operator, value}] }
        // or as { filters: { col: value } }.
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

        // Grouping: when group_by is given, counts are collected per distinct
        // value. Groups are not paginated — the frontend receives all of them
        // for the current filter.
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
     * Asks Resource::editableForRow for every row and every editable column.
     * If at least one override says false, an `_editable` map is added to the
     * row's data. A row with no overrides is left untouched.
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
     * Returns a single record by id.
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
     * Creates a record.
     *
     * Which `@input` fields there are is decided by Resource::fields().
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
     * Updates a record.
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
     * Streaming export of the list into any registered format.
     *
     * @input string ?$format  csv|xlsx|pdf — csv by default. The format must be
     *                          registered in ExporterRegistry.
     * @input array ?$filters
     * @input string ?$q
     * @input array ?$columns
     *
     * The body is the file itself, so there is nothing to describe field by
     * field: `@output file` never parsed — the generator wants a variable — and
     * was dropped in silence, leaving the operation with no response schema at
     * all. FileDownloadResponse says the same thing and is a template that
     * exists.
     *
     * @security AdminSession
     *
     * @response 200 {FileDownloadResponse}
     * @response 422 {ValidationErrorResponse} The format is not supported.
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

        // Generator chunks keep the export memory-friendly.
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
     * Applies one of the resource's own bulk actions to the selected records.
     *
     * The action is found by `key` among those the resource declares;
     * `payload` carries whatever that action asks for.
     *
     * The docblock that used to stand here described `reorder()` — it had
     * drifted one method up, and with it the wrong parameter name (`items`
     * instead of `ids`), two undocumented required fields and a response
     * template belonging to the neighbour. A client written from that
     * specification would send `items` and be told the required `ids` is
     * missing.
     *
     * @input array $ids Identifiers of the records; at least one. Each element
     *                   is required — a scalar array's element has no tag of
     *                   its own here
     * @input string $key The action, as the resource declares it
     * @input array ?$payload The action's own arguments, when it takes any
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {AffectedResponse}
     * @response 404 {NotFoundErrorResponse} The resource declares no such action
     * @response 422 {ValidationErrorResponse}
     * @response 501 The action is declared and the resource has no method for it
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

        // Find the action whose name equals $actionKey among Resource->actions().
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

        // Resolve the method on the resource itself, e.g. BulkAction->method('archive').
        $methodName = $action->toArray()['attributes']['method'] ?? null;
        if (! is_string($methodName) || ! method_exists($resource, $methodName)) {
            return $this->error([
                'errorKey' => 'action_not_implemented',
                'message' => "Method `{$methodName}` not found on resource",
            ], 501);
        }

        // The call is $resource->{method}(array $ids, array $payload).
        try {
            $result = $resource->{$methodName}($data['ids'], (array) ($data['payload'] ?? []));
        } catch (ActionFailedException $e) {
            // A refusal on the merits: the action ran and is explaining why it
            // could not finish. A 500 here would mean the panel itself broke —
            // and would wake somebody up over a mistyped port number.
            return $this->error([
                'errorKey' => 'action_failed',
                'message' => $e->getMessage(),
            ], 422);
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

    /**
     * Drag-and-drop reordering: a bulk update of several positions in one
     * transaction.
     *
     * Takes `items: [{id, position}]`. The resource must have
     * `reorderable() === true` and a `reorderColumn()`.
     *
     * @input array $items
     * @input mixed $items[].id
     * @input integer $items[].position
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceReorderedResponse}
     * @response 422 {ValidationErrorResponse}
     */
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
     * Clones a record.
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
     * Restores a soft-deleted record.
     *
     * @input integer $id
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ResourceRestoredResponse}
     * @response 404 {NotFoundErrorResponse}
     * @response 422 {ValidationErrorResponse} The resource does not use SoftDeletes.
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
     * Deletes a soft-deleted record for good.
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
     * A builder that includes trashed rows, for restore and forceDelete.
     *
     * The SoftDeletes trait adds SoftDeletingScope globally; removing it lets
     * the query see trashed records too. Equivalent to `->withTrashed()`, but
     * without relying on the builder's scope magic.
     */
    private function withTrashedQuery(Resource $resource): \Illuminate\Database\Eloquent\Builder
    {
        return $resource->modelQuery()
            ->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class);
    }

    /**
     * Deletes a record — softly when the model uses SoftDeletes, otherwise for good.
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
     * Inline editing of a single table cell.
     *
     * Takes {id, column, value}, finds the column in Resource::columns(), takes
     * its editable.validation rules, validates and updates that one column.
     * A column that is not editable answers 422.
     *
     * @input integer $id
     * @input string $column
     * @input string $value As typed; the server casts it to the column's own
     *                     type. `any` is not a type this markup has — it became
     *                     `string` in the spec anyway, silently.
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
     * Summary aggregates over the current filter: sum, avg, count, min, max.
     *
     * Returns a map of column => {sum?, avg?, …} for the columns that declare
     * `Resource::columns()->summary([...])`.
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

        // The same filters as in search are applied.
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
     * Finds the editable config of a column. Returns null when the column does
     * not exist or is not editable.
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
     * Maps an SQL exception onto a human-readable validation payload.
     * Covers 23505 (unique), 23502 (not-null) and 23503 (foreign key); anything
     * else falls back to a generic "could not save the record".
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

        // Fallback: never show raw SQL in production.
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
     * On update, unique rules without an explicit `except` exclude the record
     * being edited — otherwise it collides with itself. Both the string form
     * (`unique:table,column`) and Rule::unique() objects are handled.
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
     * Runs the HTML of every Wysiwyg field with shouldSanitize() = true
     * through HtmlSanitizer.
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
