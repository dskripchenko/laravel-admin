<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Views;

use BackedEnum;
use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\I18n\Localize;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use InvalidArgumentException;
use UnitEnum;

/**
 * The kanban view of a resource: its records as cards in columns, one column
 * per value of a status attribute. Dragging a card to another column writes
 * that value; dragging it inside a column of a reorderable resource writes
 * the order.
 *
 *     public function kanban(): ?KanbanView
 *     {
 *         return KanbanView::make('status')
 *             ->columns(['todo' => 'To do', 'doing' => 'Doing', 'done' => 'Done'])
 *             ->title('name')
 *             ->tags('labels')
 *             ->assignee('owner.name')
 *             ->due('due_at')
 *             ->lanes('priority')
 *             ->wip(['doing' => 5]);
 *     }
 */
final class KanbanView
{
    /** @var list<array{value: int|string, label: string}>|null */
    private ?array $columns = null;

    private ?string $title = null;

    private ?string $description = null;

    private ?string $tags = null;

    private ?string $assignee = null;

    private ?string $due = null;

    private ?string $lanes = null;

    /** @var list<array{value: int|string, label: string}>|null */
    private ?array $laneLabels = null;

    /** @var array<array-key, int> */
    private array $wip = [];

    private int $limit = 50;

    private ?string $orderBy = null;

    private bool $readonly = false;

    private function __construct(private readonly string $column) {}

    /**
     * @param  string  $column  The status attribute: its values are the board's columns.
     */
    public static function make(string $column): self
    {
        if ($column === '') {
            throw new InvalidArgumentException('KanbanView::make() needs a column');
        }

        return new self($column);
    }

    /**
     * The board's columns, in order: a map of value ⇒ label, a list of
     * {value, label}, or an enum class (BackedEnum values; the label is the
     * case name, as Select::fromEnum() names them). By default they are the
     * options of the form field of the same name, else the distinct values
     * the records hold.
     *
     * @param  array<int|string, string>|list<array{value: int|string, label: string}>|class-string  $columns
     */
    public function columns(array|string $columns): self
    {
        $this->columns = self::normalize($columns);

        return $this;
    }

    /** The attribute (or dot path over a loaded relation) for the card's title; recordTitle() by default. */
    public function title(string $path): self
    {
        $this->title = $path;

        return $this;
    }

    /** The line under the title. */
    public function description(string $path): self
    {
        $this->description = $path;

        return $this;
    }

    /** The card's tags: an array attribute or a comma-separated string. */
    public function tags(string $path): self
    {
        $this->tags = $path;

        return $this;
    }

    /** Who the card is assigned to: an attribute or a dot path, e.g. `owner.name`. */
    public function assignee(string $path): self
    {
        $this->assignee = $path;

        return $this;
    }

    /** The due date; a card past it is marked overdue. */
    public function due(string $path): self
    {
        $this->due = $path;

        return $this;
    }

    /**
     * Swimlanes by another attribute. Their labels and order are given the same
     * way as columns(); by default from the field of that name, else the
     * distinct values.
     *
     * @param  array<int|string, string>|list<array{value: int|string, label: string}>|class-string|null  $labels
     */
    public function lanes(string $column, array|string|null $labels = null): self
    {
        $this->lanes = $column;
        $this->laneLabels = $labels === null ? null : self::normalize($labels);

        return $this;
    }

    /**
     * Work-in-progress limits, by column value: the header warns when a
     * column holds more.
     *
     * @param  array<array-key, int>  $limits
     */
    public function wip(array $limits): self
    {
        $this->wip = $limits;

        return $this;
    }

    /** Cards per column per load; the column's "more" control fetches the next ones. */
    public function limit(int $limit): self
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('KanbanView::limit() must be at least 1');
        }
        $this->limit = $limit;

        return $this;
    }

    /**
     * The order of the cards inside a column. On a reorderable resource it is
     * its reorderColumn() by default, and a drop inside a column is saved the
     * way the list's drag-reorder saves it.
     */
    public function orderBy(string $column): self
    {
        $this->orderBy = $column;

        return $this;
    }

    /** No dragging: the board only shows. */
    public function readonly(bool $readonly = true): self
    {
        $this->readonly = $readonly;

        return $this;
    }

    public function column(): string
    {
        return $this->column;
    }

    public function laneColumn(): ?string
    {
        return $this->lanes;
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    public function perColumn(): int
    {
        return $this->limit;
    }

    public function titlePath(): ?string
    {
        return $this->title;
    }

    public function descriptionPath(): ?string
    {
        return $this->description;
    }

    public function tagsPath(): ?string
    {
        return $this->tags;
    }

    public function assigneePath(): ?string
    {
        return $this->assignee;
    }

    public function duePath(): ?string
    {
        return $this->due;
    }

    /**
     * The column the cards are ordered by inside a board column.
     */
    public function orderColumn(Resource $resource): ?string
    {
        return $this->orderBy ?? ($resource->reorderable() ? $resource->reorderColumn() : null);
    }

    /**
     * Whether a drop position is saved: the resource is reorderable and the
     * cards are ordered by its reorder column.
     */
    public function savesPosition(Resource $resource): bool
    {
        return $resource->reorderable() && $this->orderColumn($resource) === $resource->reorderColumn();
    }

    /**
     * The relations the card's dot paths read, to eager-load.
     *
     * @return list<string>
     */
    public function relations(): array
    {
        $out = [];
        foreach ([$this->title, $this->description, $this->tags, $this->assignee, $this->due] as $path) {
            if ($path !== null && str_contains($path, '.')) {
                $out[] = substr($path, 0, (int) strrpos($path, '.'));
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * The columns known without a query: given, or the options of the field of
     * the same name. Null when they have to come from the records.
     *
     * @return list<array{value: int|string, label: string}>|null
     */
    public function staticColumns(Resource $resource): ?array
    {
        return $this->columns ?? self::fieldOptions($resource, $this->column);
    }

    /**
     * @return list<array{value: int|string, label: string}>|null
     */
    public function staticLanes(Resource $resource): ?array
    {
        if ($this->lanes === null) {
            return null;
        }

        return $this->laneLabels ?? self::fieldOptions($resource, $this->lanes);
    }

    /**
     * The WIP limit of a column value, or null.
     */
    public function wipFor(int|string $value): ?int
    {
        $limit = $this->wip[$value] ?? null;

        return is_int($limit) && $limit > 0 ? $limit : null;
    }

    /**
     * The view's description for the resource meta.
     *
     * @return array<string, mixed>
     */
    public function toArray(Resource $resource): array
    {
        $columns = $this->staticColumns($resource);

        return [
            'column' => $this->column,
            'columns' => $columns === null ? null : array_map(fn (array $c): array => [
                ...$c,
                'wip' => $this->wipFor($c['value']),
            ], $columns),
            'lanes' => $this->lanes,
            'lane_labels' => $this->staticLanes($resource),
            'limit' => $this->limit,
            'order_by' => $this->orderColumn($resource),
            'saves_position' => $this->savesPosition($resource),
            'readonly' => $this->readonly,
            'card' => [
                'title' => $this->title,
                'description' => $this->description,
                'tags' => $this->tags,
                'assignee' => $this->assignee,
                'due' => $this->due,
            ],
        ];
    }

    /**
     * The options of the resource's form field named `$name`, as {value, label}.
     *
     * @return list<array{value: int|string, label: string}>|null
     */
    public static function fieldOptions(Resource $resource, string $name): ?array
    {
        foreach ($resource->fields() as $field) {
            /** @var Field $field */
            if ($field->name() !== $name) {
                continue;
            }
            $options = $field->getAttributes()['options'] ?? null;
            if (! is_array($options) || $options === []) {
                return null;
            }
            $out = [];
            foreach ($options as $option) {
                if (! is_array($option) || ! array_key_exists('value', $option)) {
                    continue;
                }
                $value = self::scalar($option['value']);
                if ($value === null) {
                    continue;
                }
                $out[] = ['value' => $value, 'label' => (string) ($option['label'] ?? $value)];
            }

            return $out === [] ? null : $out;
        }

        return null;
    }

    /**
     * A stored value as a column key: an enum becomes its value, a bool an int.
     */
    public static function scalar(mixed $value): int|string|null
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            is_int($value), is_string($value) => $value,
            is_bool($value) => (int) $value,
            is_float($value) => (string) $value,
            default => null,
        };
    }

    /**
     * @param  array<int|string, mixed>|string  $items
     * @return list<array{value: int|string, label: string}>
     */
    private static function normalize(array|string $items): array
    {
        if (is_string($items)) {
            if (! enum_exists($items)) {
                throw new InvalidArgumentException("{$items} must be an enum");
            }
            $out = [];
            /** @var list<UnitEnum> $cases */
            $cases = $items::cases();
            foreach ($cases as $case) {
                $out[] = ['value' => $case instanceof BackedEnum ? $case->value : $case->name, 'label' => $case->name];
            }

            return $out;
        }

        $out = [];
        if (array_is_list($items) && $items !== [] && is_array($items[0])) {
            foreach ($items as $item) {
                if (is_array($item) && array_key_exists('value', $item) && ($value = self::scalar($item['value'])) !== null) {
                    $out[] = ['value' => $value, 'label' => (string) (Localize::string((string) ($item['label'] ?? $value)) ?? $value)];
                }
            }

            return $out;
        }

        foreach ($items as $value => $label) {
            $out[] = ['value' => $value, 'label' => (string) (Localize::string(is_string($label) ? $label : (string) $value) ?? $value)];
        }

        return $out;
    }
}
