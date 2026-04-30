<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Визуальная группа полей с заголовком/описанием.
 *
 * В отличие от Layout::block(), Group — это field-уровневая обёртка: имеет
 * `name`, попадает в state и валидируется как объект (Laravel rule `array`).
 * Используется когда нужны вложенные поля в одной структуре state'а:
 *
 *     'address' => [
 *         'city' => '...',
 *         'street' => '...',
 *     ]
 */
final class Group extends Field
{
    public function fieldType(): string
    {
        return 'group';
    }

    /**
     * @param  list<Field>  $fields
     */
    public function fields(array $fields): static
    {
        $this->attributes['fields'] = array_map(
            static fn (Field $f): array => $f->toArray(),
            $fields,
        );

        return $this;
    }

    /**
     * Layout вложенных полей: 'rows' (default) | 'columns' | 'inline'.
     */
    public function layout(string $layout): static
    {
        $this->attributes['layout'] = $layout;

        return $this;
    }

    public function collapsible(bool $collapsible = true): static
    {
        $this->attributes['collapsible'] = $collapsible;

        return $this;
    }

    public function collapsed(bool $collapsed = true): static
    {
        $this->attributes['collapsed'] = $collapsed;
        if ($collapsed) {
            $this->attributes['collapsible'] = true;
        }

        return $this;
    }
}
