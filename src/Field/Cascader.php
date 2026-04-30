<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Каскадный выбор: 2-3 связанных dropdown'а (страна → регион → город).
 *
 * State хранится как list<value> длиной levels (по одному value на уровень).
 * Каждый уровень имеет свой displayLabel (название этапа на UI).
 */
final class Cascader extends Field
{
    public function fieldType(): string
    {
        return 'cascader';
    }

    /**
     * @param  list<array{key: string, label: string, options?: list<array{value: mixed, label: string, children?: array<int, mixed>}>}>  $levels
     */
    public function levels(array $levels): static
    {
        $this->attributes['levels'] = $levels;

        return $this;
    }

    /**
     * Полный nested-tree options для всех уровней.
     *
     * @param  list<array{value: mixed, label: string, children?: array<int, mixed>}>  $tree
     */
    public function options(array $tree): static
    {
        $this->attributes['tree'] = $tree;

        return $this;
    }

    /**
     * Разделитель в displayed value: 'Россия / Москва / Москва'.
     */
    public function separator(string $separator): static
    {
        $this->attributes['separator'] = $separator;

        return $this;
    }

    /**
     * Включить ?q= search по всем уровням.
     */
    public function searchable(bool $searchable = true): static
    {
        $this->attributes['searchable'] = $searchable;

        return $this;
    }
}
