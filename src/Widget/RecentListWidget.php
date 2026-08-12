<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "The latest N" — a list of a model's most recent records.
 *
 * It is configured with the model, the count, the columns to show and an
 * optional link to the view page.
 */
class RecentListWidget extends Widget
{
    /** @var class-string<Model>|null */
    private ?string $modelClass = null;

    private string $orderColumn = 'created_at';

    private string $orderDirection = 'desc';

    private int $limit = 5;

    /** @var list<array{column: string, label: string}> */
    private array $columns = [];

    private ?string $linkResourceSlug = null;

    public function widgetType(): string
    {
        return 'recent_list';
    }

    /**
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        $this->modelClass = $model;

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

    public function column(string $column, ?string $label = null): static
    {
        $this->columns[] = ['column' => $column, 'label' => $label ?? $column];

        return $this;
    }

    /**
     * The resource slug a click leads to; it forms a URL of the shape
     * /admin/resources/{slug}/{id}.
     */
    public function linkTo(string $resourceSlug): static
    {
        $this->linkResourceSlug = $resourceSlug;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        if ($this->modelClass === null) {
            return ['rows' => [], 'columns' => $this->columns, 'linkTo' => null];
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;
        $columnNames = array_map(static fn (array $c): string => $c['column'], $this->columns);
        $columnNames[] = 'id';

        /** @var Builder<Model> $query */
        $query = $modelClass::query();
        $rows = $query
            ->orderBy($this->orderColumn, $this->orderDirection)
            ->limit($this->limit)
            ->get(array_values(array_unique($columnNames)));

        return [
            'rows' => $rows->map(static fn (Model $m): array => $m->toArray())->all(),
            'columns' => $this->columns,
            'linkTo' => $this->linkResourceSlug,
        ];
    }
}
