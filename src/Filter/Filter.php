<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Filter;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Абстрактный URL-driven фильтр.
 *
 * Контракт:
 *   - `for($field)` — задаёт целевое поле модели/колонку.
 *   - `apply(Builder, value)` — применяет фильтр к Eloquent-builder'у.
 *   - `toArray()` — сериализация для манифеста SPA (тип, label, опции).
 *
 * Поведение по-умолчанию: equality / LIKE / null-comparison через `apply()`
 * подклассов. URL-вид: `?filters[email]=ivan` либо в JSON body для search-action.
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
            'label' => $this->label ?? $this->humanizeField(),
            'type' => $this->type(),
            'options' => null,
            'default' => $this->defaultValue,
            'multiple' => $this->multiple,
        ];
    }

    private function humanizeField(): string
    {
        $name = str_replace(['_', '.'], ' ', $this->field);

        return ucfirst(trim($name));
    }
}
