<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Views;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Reads a resource's records as a kanban board: the columns, the lanes, a page
 * of cards per column, one card.
 *
 * The queries it is given already carry the resource's scope (indexQuery), the
 * list's filters and its free-text search; it narrows them by column.
 */
final class KanbanBoard
{
    public function __construct(
        private readonly Resource $resource,
        private readonly KanbanView $view,
    ) {}

    /**
     * The board's columns: the declared ones, else the distinct values the
     * records in scope hold.
     *
     * @param  Builder<Model>  $scope
     * @return list<array{value: int|string, label: string}>
     */
    public function columns(Builder $scope): array
    {
        return $this->view->staticColumns($this->resource) ?? self::distinct($scope, $this->view->column());
    }

    /**
     * The lanes, or null when the board has none.
     *
     * @param  Builder<Model>  $scope
     * @return list<array{value: int|string, label: string}>|null
     */
    public function lanes(Builder $scope): ?array
    {
        $column = $this->view->laneColumn();
        if ($column === null) {
            return null;
        }

        return $this->view->staticLanes($this->resource) ?? self::distinct($scope, $column);
    }

    /**
     * One board column: its total and a page of its cards.
     *
     * @param  Builder<Model>  $base
     * @param  array{value: int|string, label: string}  $column
     * @return array<string, mixed>
     */
    public function column(Builder $base, array $column, int $offset, int $limit): array
    {
        $query = (clone $base)->where($this->view->column(), $column['value']);
        $total = (clone $query)->count();

        $rows = $this->ordered($query)
            ->with($this->view->relations())
            ->offset($offset)
            ->limit($limit)
            ->get();

        return [
            'value' => $column['value'],
            'label' => $column['label'],
            'wip' => $this->view->wipFor($column['value']),
            'total' => $total,
            'offset' => $offset,
            'cards' => $rows->map(fn (Model $row): array => $this->card($row))->values()->all(),
        ];
    }

    /**
     * The keys of a column's records in their board order.
     *
     * @param  Builder<Model>  $base
     * @return list<int|string>
     */
    public function orderedIds(Builder $base, int|string $value): array
    {
        $model = $base->getModel();

        /** @var list<int|string> */
        return $this->ordered((clone $base)->where($this->view->column(), $value))
            ->pluck($model->getQualifiedKeyName())
            ->all();
    }

    /**
     * A record as a card.
     *
     * @return array<string, mixed>
     */
    public function card(Model $row): array
    {
        $titlePath = $this->view->titlePath();
        $title = $titlePath !== null ? self::text(data_get($row, $titlePath)) : null;
        $descriptionPath = $this->view->descriptionPath();
        $assigneePath = $this->view->assigneePath();
        $laneColumn = $this->view->laneColumn();
        [$due, $overdue] = $this->due($row);

        return [
            'id' => $row->getKey(),
            'value' => KanbanView::scalar($row->getAttribute($this->view->column())),
            'lane' => $laneColumn !== null ? KanbanView::scalar($row->getAttribute($laneColumn)) : null,
            'title' => $title ?? $this->resource->recordTitle($row),
            'description' => $descriptionPath !== null ? self::text(data_get($row, $descriptionPath)) : null,
            'tags' => $this->tags($row),
            'assignee' => $assigneePath !== null ? self::text(data_get($row, $assigneePath)) : null,
            'due' => $due,
            'overdue' => $overdue,
            // The card may not leave its column: Resource::editableForRow()
            // forbids changing the status of this record.
            'locked' => ! $this->resource->editableForRow($row, $this->view->column()),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function ordered(Builder $query): Builder
    {
        $model = $query->getModel();
        $order = $this->view->orderColumn($this->resource);
        if ($order !== null) {
            $query->orderBy($order);
        } else {
            foreach ($this->resource->defaultOrder() as $o) {
                $query->orderBy($o['column'], $o['direction'] === 'desc' ? 'desc' : 'asc');
            }
        }

        // A stable tie-break, so pages do not overlap.
        return $query->orderBy($model->getQualifiedKeyName());
    }

    /**
     * @return list<string>
     */
    private function tags(Model $row): array
    {
        $path = $this->view->tagsPath();
        if ($path === null) {
            return [];
        }
        $value = data_get($row, $path);
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if ($value instanceof \Illuminate\Support\Collection) {
            $value = $value->all();
        }
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $tag) {
            $text = self::text($tag);
            if ($text !== null && trim($text) !== '') {
                $out[] = trim($text);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * The due date as the panel shows it ('Y-m-d', or 'Y-m-d H:i' when it has
     * a time) and whether it has passed.
     *
     * @return array{0: string|null, 1: bool}
     */
    private function due(Model $row): array
    {
        $path = $this->view->duePath();
        if ($path === null) {
            return [null, false];
        }
        $value = data_get($row, $path);
        if ($value === null || $value === '') {
            return [null, false];
        }
        try {
            $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value);
        } catch (\Throwable) {
            return [null, false];
        }
        $dateOnly = ! str_contains($path, '.') && CalendarView::isDateColumn($row, $path)
            || (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1);

        if ($dateOnly) {
            return [$date->format('Y-m-d'), $date->lt(Carbon::today())];
        }

        return [$date->format('Y-m-d H:i'), $date->lt(Carbon::now())];
    }

    private static function text(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof CarbonInterface => $value->format('Y-m-d H:i'),
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => null,
        };
    }

    /**
     * @param  Builder<Model>  $scope
     * @return list<array{value: int|string, label: string}>
     */
    private static function distinct(Builder $scope, string $column): array
    {
        $values = (clone $scope)
            ->reorder()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();

        $out = [];
        foreach ($values as $value) {
            $value = KanbanView::scalar($value);
            if ($value !== null) {
                $out[] = ['value' => $value, 'label' => (string) $value];
            }
        }

        return $out;
    }
}
