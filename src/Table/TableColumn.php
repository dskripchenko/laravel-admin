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
     * Включает inline-edit ячейки.
     *
     * @param  list<string|array<string, mixed>>  $rules  Validation rules для inline-edit.
     * @param  'text'|'number'|'select'|'date'|'textarea'|'switcher'  $as  Тип инпута.
     * @param  array<int|string, string>  $options  Для as='select' — мапа value→label.
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
     * Денежная сумма с currency-форматированием на UI.
     */
    public function asMoney(string $currency = 'RUB', int $decimals = 2): self
    {
        return $this->as('money', ['currency' => $currency, 'decimals' => $decimals]);
    }

    /**
     * Boolean с иконкой/badge'ом true/false.
     */
    public function asBoolean(?string $trueLabel = null, ?string $falseLabel = null): self
    {
        return $this->as('boolean', [
            'trueLabel' => $trueLabel,
            'falseLabel' => $falseLabel,
        ]);
    }

    /**
     * Размер в байтах → human-readable (1.2 MB).
     */
    public function asBytes(): self
    {
        return $this->as('bytes');
    }

    /**
     * Бэйдж с цветом по value (`map: ['active' => 'green', 'banned' => 'red']`).
     *
     * @param  array<string, string>  $colorMap  value => UI color name
     */
    public function asBadge(array $colorMap = []): self
    {
        return $this->as('badge', ['colors' => $colorMap]);
    }

    /**
     * Превратить значение в clickable link.
     *
     * @param  string|callable(mixed, array<string, mixed>): string  $href
     *                                                                      Строка-шаблон с `:value` либо callable($value, $row): string.
     */
    public function asLink(string|callable $href, ?string $target = null): self
    {
        $config = ['target' => $target];
        if (is_string($href)) {
            $config['template'] = $href;
        } else {
            $config['hrefFn'] = $href;
        }

        return $this->as('link', $config);
    }

    /**
     * Изображение по URL. width/height — фиксированный размер превью.
     */
    public function asImage(?int $width = null, ?int $height = null): self
    {
        return $this->as('image', [
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Custom formatter — server-side трансформация значения.
     * Вызывается в `format($value, $row)` при сериализации row'ов.
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
     * Применить formatter к значению.
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
