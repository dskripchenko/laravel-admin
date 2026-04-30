<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Table;

/**
 * Описание колонки таблицы list-экрана.
 *
 * В отличие от Field (форма), у TableColumn свой набор атрибутов: sortable,
 * searchable, copyable, width, alignment, preset (date/money/badge/...),
 * editable (inline-edit), summary (footer-агрегаты).
 *
 * Сериализуется в `ColumnSchema` (см. docs/api/schemas.md).
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
     * Включает inline-edit ячейки.
     *
     * @param  list<string|array<string, mixed>>  $rules  Validation rules для inline-edit.
     */
    public function editable(array $rules = []): self
    {
        $this->editable = ['field' => $this->name, 'validation' => $rules];

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
        return [
            'name' => $this->name,
            'label' => $this->label ?? $this->humanize($this->name),
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
            'meta' => $this->presetMeta,
        ];
    }

    private function humanize(string $field): string
    {
        return ucfirst(str_replace(['_', '.'], ' ', $field));
    }
}
