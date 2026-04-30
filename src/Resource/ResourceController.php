<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource;

use Dskripchenko\LaravelAdmin\Filter\Filter;
use Dskripchenko\LaravelAdmin\Filter\HttpFilterParser;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedCreateScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedEditScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedListScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedViewScreen;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
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
        foreach ($resource->filters() as $filter) {
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

        // Order
        $orders = (array) $request->input('order', []);
        foreach ($orders as $order) {
            if (! is_array($order) || ! isset($order['column'])) {
                continue;
            }
            $direction = ($order['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            $query = $query->orderBy((string) $order['column'], $direction);
        }

        // Pagination
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min($perPage, 100));
        $page = max(1, (int) $request->input('page', 1));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Group-by: если передан group_by — собираем counts по уникальным значениям.
        // Pagination над group'ами не делаем — фронт получает все group'ы для текущего фильтра.
        $groups = null;
        $groupBy = (string) $request->input('group_by', '');
        if ($groupBy !== '') {
            $groupQuery = $resource->indexQuery();
            $filterInputs = HttpFilterParser::parse($request);
            foreach ($resource->filters() as $filter) {
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

        return $this->success([
            'data' => $paginator->items(),
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
            'record' => $record->toArray(),
            'state' => $record->toArray(),
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

        $modelClass = $resource::$model;
        /** @var \Illuminate\Database\Eloquent\Model $record */
        $record = new $modelClass;
        $record->forceFill($data);
        $record->save();

        return $this->created([
            'record' => $record->toArray(),
            'redirect_url' => '/admin/resources/'.$resource::slug().'/'.$record->getKey(),
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

        $data = $request->validate($this->flattenRules($resource->validationRules('update')));
        $record->forceFill($data);
        $record->save();

        return $this->success([
            'record' => $record->toArray(),
            'state' => $record->toArray(),
            'message' => 'Updated',
        ]);
    }

    /**
     * Потоковый CSV-экспорт списка с применением текущих filters.
     *
     * Использует встроенный fputcsv через StreamedResponse — без зависимостей.
     * Для огромных датасетов рекомендуется delayed-process job (фаза P13).
     *
     * @input array ?$filters
     * @input string ?$q
     * @input array ?$columns Список имён колонок для выгрузки. Default: все
     *                          из Resource::columns(), кроме defaultHidden.
     *
     * @output file CSV
     *
     * @security AdminSession
     *
     * @response 200 {ResourceCsvExportResponse}
     */
    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $resource = $this->currentResource();
        $query = $resource->indexQuery();

        $filterInputs = HttpFilterParser::parse($request);
        foreach ($resource->filters() as $filter) {
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

        // Колонки для экспорта.
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

        $filename = $resource::slug().'-'.date('Y-m-d-His').'.csv';

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($query, $columns): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }
                // BOM для Excel UTF-8 совместимости.
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, array_values($columns), ',', '"', '\\');

                $query->chunkById(500, function ($rows) use ($handle, $columns): void {
                    foreach ($rows as $row) {
                        $line = [];
                        foreach (array_keys($columns) as $col) {
                            $value = data_get($row->toArray(), $col);
                            $line[] = is_scalar($value) ? (string) $value : json_encode($value);
                        }
                        fputcsv($handle, $line, ',', '"', '\\');
                    }
                });

                fclose($handle);
            },
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
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
            'record' => $record->toArray(),
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
            'record' => $record->toArray(),
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
            'record' => $record->toArray(),
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
        foreach ($resource->filters() as $filter) {
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
        $resource = $this->registry->resolve($slug);
        if ($resource === null) {
            throw new NotFoundHttpException("Resource `{$slug}` is not registered");
        }

        return $resource;
    }

    /**
     * @param  array<string, array<int, string|array<string, mixed>>>  $rules
     * @return array<string, array<int, string|array<string, mixed>>>
     */
    private function flattenRules(array $rules): array
    {
        return $rules;
    }
}
