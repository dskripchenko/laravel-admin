<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Table;

/**
 * Describes one column of a list screen's table.
 *
 * Unlike a Field, which belongs to a form, TableColumn has attributes of its
 * own: sortable, searchable, copyable, width, alignment, a preset
 * (date/money/badge/...), editable for inline editing and summary for the
 * footer aggregates.
 *
 * It serializes into a `ColumnSchema`; see docs/api/schemas.md.
 */
final class TableColumn
{
    private string $name;

    private ?string $label = null;

    private bool $sortable = false;

    private bool $searchable = false;

    private bool $copyable = false;

    private ?string $width = null;

    private bool $defaultHidden = false;

    private bool $cantHide = false;

    /** @var 'left'|'center'|'right' */
    private string $align = 'left';

    private ?string $preset = null;

    /** @var array<string, mixed> */
    private array $presetMeta = [];

    /** @var array<string, mixed>|null */
    private ?array $editable = null;

    /** @var list<string> */
    private array $summary = [];

    /** @var (callable(mixed, array<string, mixed>): mixed)|null */
    private $formatter = null;

    public static function make(string $name): self
    {
        $instance = new self;
        $instance->name = $name;

        return $instance;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function sort(): self
    {
        $this->sortable = true;

        return $this;
    }

    public function search(): self
    {
        $this->searchable = true;

        return $this;
    }

    public function copyable(): self
    {
        $this->copyable = true;

        return $this;
    }

    public function width(string $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function defaultHidden(): self
    {
        $this->defaultHidden = true;

        return $this;
    }

    public function cantHide(): self
    {
        $this->cantHide = true;

        return $this;
    }

    /**
     * @param  'left'|'center'|'right'  $align
     */
    public function align(string $align): self
    {
        $this->align = $align;

        return $this;
    }

    /**
     * Turns inline editing of the cell on.
     *
     * @param  list<string|array<string, mixed>>  $rules  The validation rules of the inline edit.
     * @param  'text'|'number'|'select'|'date'|'textarea'|'switcher'  $as  The input's type.
     * @param  array<int|string, string>  $options  For as='select': a value → label map.
     */
    public function editable(array $rules = [], string $as = 'text', array $options = []): self
    {
        $this->editable = [
            'field' => $this->name,
            'validation' => $rules,
            'as' => $as,
            'options' => $options,
        ];

        return $this;
    }

    /**
     * @param  list<'sum'|'avg'|'count'|'range'>  $aggregates
     */
    public function summary(array $aggregates): self
    {
        $this->summary = $aggregates;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function as(string $preset, array $meta = []): self
    {
        $this->preset = $preset;
        $this->presetMeta = $meta;

        return $this;
    }

    /**
     * Shorthand for as('date', ['format' => $format]).
     */
    public function asDate(string $format = 'd.m.Y'): self
    {
        return $this->as('date', ['format' => $format]);
    }

    /**
     * Shorthand for as('datetime', ['format' => $format]).
     */
    public function asDateTime(string $format = 'd.m.Y H:i:s'): self
    {
        return $this->as('datetime', ['format' => $format]);
    }

    /**
     * A monetary amount, formatted with its currency in the UI.
     */
    public function asMoney(string $currency = 'RUB', int $decimals = 2): self
    {
        return $this->as('money', ['currency' => $currency, 'decimals' => $decimals]);
    }

    /**
     * A boolean, shown as a true/false icon or badge.
     */
    public function asBoolean(?string $trueLabel = null, ?string $falseLabel = null): self
    {
        return $this->as('boolean', [
            'trueLabel' => $trueLabel,
            'falseLabel' => $falseLabel,
        ]);
    }

    /**
     * A size in bytes, rendered human-readably as 1.2 MB.
     */
    public function asBytes(): self
    {
        return $this->as('bytes');
    }

    /**
     * A badge coloured — and, when asked, captioned — by its value.
     *
     * Each entry of the map is either a tone, or an array with a `tone` (or
     * `color`) and a `label`:
     *
     *     ->asBadge(['active' => 'success', 'banned' => 'danger'])
     *     ->asBadge([
     *         'draft' => ['label' => 'Draft', 'tone' => 'warning'],
     *         'published' => ['label' => 'Published', 'tone' => 'success'],
     *     ])
     *     ->asBadge(['active' => 'success'], ['active' => 'Active'])
     *
     * The tones are the UI kit's (info, success, warning, danger, default) or
     * the colour names green, red, yellow, blue, gray. A label is translated
     * like any caption; a value with no label shows as it is.
     *
     * @param  array<string, string|array{label?: string, tone?: string, color?: string}>  $map  value => tone, or value => [label, tone]
     * @param  array<string, string>  $labels  value => label, on top of the labels of $map
     */
    public function asBadge(array $map = [], array $labels = []): self
    {
        $colors = [];
        $mapLabels = [];
        foreach ($map as $value => $entry) {
            $value = (string) $value;
            if (is_array($entry)) {
                $tone = $entry['tone'] ?? $entry['color'] ?? '';
                if ($tone !== '') {
                    $colors[$value] = $tone;
                }
                if (isset($entry['label'])) {
                    $mapLabels[$value] = $entry['label'];
                }
            } else {
                $colors[$value] = (string) $entry;
            }
        }
        foreach ($labels as $value => $label) {
            $mapLabels[(string) $value] = (string) $label;
        }

        $meta = ['colors' => $colors];
        if ($mapLabels !== []) {
            $meta['labels'] = $mapLabels;
        }

        return $this->as('badge', $meta);
    }

    /**
     * Turns the cell's value into a clickable link.
     *
     * $template is an href template with placeholders, resolved on the
     * frontend against the row:
     *   `{field}` — the value of that field of the row, `{signed_download_url}`
     *               for instance,
     *   `:value`  — the cell's own value.
     * When the resolution comes out empty — the field is null — no link is
     * rendered and the text stays.
     *
     * A callable is deliberately NOT supported: the column's configuration is
     * serialized into the manifest as JSON, and a closure cannot go there. Put
     * the link you need into an appended attribute of the model and point at
     * it with `{attr}`.
     */
    public function asLink(string $template, ?string $target = null): self
    {
        return $this->as('link', ['template' => $template, 'target' => $target]);
    }

    /**
     * An image at a URL; width and height fix the preview's size.
     */
    public function asImage(?int $width = null, ?int $height = null): self
    {
        return $this->as('image', [
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * A custom formatter — a server-side transformation of the value, called
     * as `format($value, $row)` while the rows are serialized: a resource's
     * list and tree, a TableWidget. The cell shows what it returns, and so
     * does an inline editor opened on that cell; `$row` is the raw row.
     *
     * @param  callable(mixed, array<string, mixed>): mixed  $formatter
     */
    public function format(callable $formatter): self
    {
        $this->formatter = $formatter;

        return $this;
    }

    public function hasFormatter(): bool
    {
        return $this->formatter !== null;
    }

    /**
     * Applies the formatter to a value.
     *
     * @param  array<string, mixed>  $row
     */
    public function applyFormatter(mixed $value, array $row): mixed
    {
        if ($this->formatter === null) {
            return $value;
        }

        return ($this->formatter)($value, $row);
    }

    /**
     * Runs the formatters of the given columns over serialized rows.
     *
     * Each formatter sees the raw row, whatever the other formatters return.
     * A dotted column name (`author.name`) reaches into a nested relation; a
     * column the row does not carry is left alone.
     *
     * @param  iterable<TableColumn>  $columns
     * @param  array<int|string, array<string, mixed>>  $rows
     * @return array<int|string, array<string, mixed>>
     */
    public static function formatRows(iterable $columns, array $rows): array
    {
        $formatted = [];
        foreach ($columns as $column) {
            if ($column->hasFormatter()) {
                $formatted[] = $column;
            }
        }
        if ($formatted === []) {
            return $rows;
        }

        foreach ($rows as $i => $row) {
            $out = $row;
            foreach ($formatted as $column) {
                $name = $column->name();
                if (array_key_exists($name, $row)) {
                    $out[$name] = $column->applyFormatter($row[$name], $row);
                } elseif (str_contains($name, '.') && \Illuminate\Support\Arr::has($row, $name)) {
                    \Illuminate\Support\Arr::set($out, $name, $column->applyFormatter(\Illuminate\Support\Arr::get($row, $name), $row));
                }
            }
            $rows[$i] = $out;
        }

        return $rows;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $meta = $this->presetMeta;
        foreach (['trueLabel', 'falseLabel', 'label'] as $key) {
            if (isset($meta[$key]) && is_string($meta[$key])) {
                $meta[$key] = \Dskripchenko\LaravelAdmin\I18n\Localize::string($meta[$key]);
            }
        }
        if (isset($meta['options']) && is_array($meta['options'])) {
            $meta['options'] = \Dskripchenko\LaravelAdmin\I18n\Localize::options($meta['options']);
        }
        if (isset($meta['labels']) && is_array($meta['labels'])) {
            $meta['labels'] = \Dskripchenko\LaravelAdmin\I18n\Localize::options($meta['labels']);
        }

        return [
            'name' => $this->name,
            'label' => \Dskripchenko\LaravelAdmin\I18n\Localize::string($this->label ?? $this->humanize($this->name)),
            'type' => $this->preset ?? 'text',
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'copyable' => $this->copyable,
            'width' => $this->width,
            'defaultHidden' => $this->defaultHidden,
            'cantHide' => $this->cantHide,
            'align' => $this->align,
            'editable' => $this->editable,
            'summary' => $this->summary === [] ? null : $this->summary,
            'preset' => $this->preset,
            'meta' => $meta,
        ];
    }

    private function humanize(string $field): string
    {
        return \Dskripchenko\LaravelAdmin\I18n\AutoLabel::sentence($field);
    }
}
