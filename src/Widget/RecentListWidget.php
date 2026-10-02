<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use BackedEnum;
use DateTimeInterface;
use Dskripchenko\LaravelAdmin\I18n\Localize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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

    private ?string $periodColumn = null;

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
        $columns = $this->localizedColumns();
        if ($this->modelClass === null) {
            return ['rows' => [], 'columns' => $columns, 'linkTo' => null];
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;

        /** @var Builder<Model> $query */
        $query = $modelClass::query();
        if ($this->periodColumn !== null) {
            $query = $this->dashboardContext()->constrain($query, $this->periodColumn);
        }

        // The whole row is fetched: a listed column may be an accessor or a
        // relation path (`author.name`) rather than a table column, and
        // selecting only the listed names broke on such columns — an unknown
        // column on MySQL/Postgres, an empty cell elsewhere.
        $rows = $query
            ->orderBy($this->orderColumn, $this->orderDirection)
            ->limit($this->limit)
            ->get();

        return [
            'rows' => $rows->map(fn (Model $m): array => $this->row($m))->all(),
            'columns' => $columns,
            'linkTo' => $this->linkResourceSlug,
        ];
    }

    /**
     * The listed columns of one record, plus its key, keyed by the column
     * name as declared.
     *
     * @return array<string, mixed>
     */
    private function row(Model $model): array
    {
        $serialized = $model->toArray();
        $row = ['id' => $model->getKey()];
        foreach ($this->columns as $column) {
            $name = $column['column'];
            $value = array_key_exists($name, $serialized)
                ? $serialized[$name]
                : data_get($model, $name);
            if ($value instanceof DateTimeInterface) {
                $value = Carbon::instance($value)->toJSON();
            } elseif ($value instanceof BackedEnum) {
                $value = $value->value;
            }
            $row[$name] = $value;
        }

        return $row;
    }

    /**
     * @return list<array{column: string, label: string}>
     */
    private function localizedColumns(): array
    {
        return array_map(static fn (array $c): array => [
            'column' => $c['column'],
            'label' => (string) Localize::string($c['label']),
        ], $this->columns);
    }
}
