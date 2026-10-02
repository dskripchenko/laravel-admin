<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A full table as a widget — TableColumns, a sort and a limit.
 *
 * Unlike RecentList it supports the same columns and presets as a resource's
 * list does, which makes it a good fit for a summary table on a dashboard.
 */
class TableWidget extends Widget
{
    /** @var class-string<Model>|null */
    private ?string $modelClass = null;

    /** @var (callable(Builder<Model>): Builder<Model>)|null */
    private $queryModifier = null;

    /** @var list<TableColumn> */
    private array $columns = [];

    private int $limit = 10;

    private string $orderColumn = 'id';

    private string $orderDirection = 'desc';

    private ?string $periodColumn = null;

    public function widgetType(): string
    {
        return 'table';
    }

    /**
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        $this->modelClass = $model;

        return $this;
    }

    /**
     * @param  list<TableColumn>  $columns
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function orderBy(string $column, string $direction = 'desc'): static
    {
        $this->orderColumn = $column;
        $this->orderDirection = $direction === 'asc' ? 'asc' : 'desc';

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = max(1, $limit);

        return $this;
    }

    /**
     * An arbitrary filter over the default query — a where(...) for a scope, say.
     *
     * @param  callable(Builder<Model>): Builder<Model>  $modifier
     */
    public function query(callable $modifier): static
    {
        $this->queryModifier = $modifier;

        return $this;
    }

    /**
     * Shows only the records inside the dashboard's selected period, by a
     * timestamp column. Off by default: the widget ignores the period.
     */
    public function withinPeriod(string $column = 'created_at'): static
    {
        $this->periodColumn = $column;

        return $this->periodAware();
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        if ($this->modelClass === null) {
            return [
                'rows' => [],
                'columns' => array_map(static fn (TableColumn $c): array => $c->toArray(), $this->columns),
            ];
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;
        /** @var Builder<Model> $query */
        $query = $modelClass::query();
        if ($this->queryModifier !== null) {
            $query = ($this->queryModifier)($query);
        }

        if ($this->periodColumn !== null) {
            $query = $this->dashboardContext()->constrain($query, $this->periodColumn);
        }

        $rows = $query
            ->orderBy($this->orderColumn, $this->orderDirection)
            ->limit($this->limit)
            ->get()
            ->map(static fn (Model $m): array => $m->toArray())
            ->all();

        return [
            'rows' => TableColumn::formatRows($this->columns, $rows),
            'columns' => array_map(static fn (TableColumn $c): array => $c->toArray(), $this->columns),
        ];
    }
}
