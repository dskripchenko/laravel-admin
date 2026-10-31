<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Filter;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * The abstract URL-driven filter.
 *
 * The contract:
 *   - `for($field)` names the model's field or column.
 *   - `apply(Builder, value)` applies the filter to an Eloquent builder.
 *   - `toArray()` serializes it for the SPA's manifest: the type, the label,
 *     the options.
 *
 * By default the subclasses' `apply()` does an equality, a LIKE or a null
 * comparison. In a URL it looks like `?filters[email]=ivan`, or it travels in
 * the JSON body of the search action.
 *
 * @phpstan-consistent-constructor
 */
abstract class Filter
{
    protected string $field;

    protected ?string $label = null;

    protected mixed $defaultValue = null;

    protected bool $multiple = false;

    abstract public function type(): string;

    abstract public function apply(Builder $query, mixed $value): Builder;

    public static function for(string $field): static
    {
        /** @var static $instance */
        $instance = new static;
        $instance->field = $field;

        return $instance;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->defaultValue = $value;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * The values of a multiple() filter: a list as sent, or a comma list —
     * the form the panel puts in its requests and links. Null when the filter
     * takes one value or the value is not a list.
     *
     * @return list<mixed>|null
     */
    protected function multipleValues(mixed $value): ?array
    {
        if (! $this->multiple) {
            return null;
        }
        if (is_array($value)) {
            return array_values($value);
        }
        if (is_string($value) && str_contains($value, ',')) {
            return array_values(array_filter(
                array_map('trim', explode(',', $value)),
                static fn (string $v): bool => $v !== '',
            ));
        }

        return null;
    }

    public function field(): string
    {
        return $this->field;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->field,
            'label' => \Dskripchenko\LaravelAdmin\I18n\Localize::string($this->label ?? $this->humanizeField()),
            'type' => $this->type(),
            'options' => null,
            'default' => $this->defaultValue,
            'multiple' => $this->multiple,
        ];
    }

    private function humanizeField(): string
    {
        return \Dskripchenko\LaravelAdmin\I18n\AutoLabel::sentence($this->field);
    }
}
