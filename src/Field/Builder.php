<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Page-builder — массив блоков разных типов с собственным набором полей.
 *
 * Конкретные типы блоков объявляются через `block(name, fields)`. State
 * хранится как list<{type: 'name', data: {...}}>. SPA рендерит как dnd-список
 * блоков с inline-редактором. Backend сериализует в JSON-колонке.
 *
 * Пример:
 *
 *     Builder::make('content')
 *         ->block('hero', [
 *             Input::make('title')->required(),
 *             Markdown::make('subtitle'),
 *         ])
 *         ->block('gallery', [
 *             FileUpload::make('images')->multiple(),
 *         ]);
 */
final class Builder extends Field
{
    /** @var array<string, list<Field>> name => fields */
    private array $blocks = [];

    public function fieldType(): string
    {
        return 'builder';
    }

    /**
     * Объявить тип блока с полями.
     *
     * @param  list<Field>  $fields
     */
    public function block(string $name, array $fields, ?string $label = null, ?string $icon = null): static
    {
        $this->blocks[$name] = $fields;
        $serialized = $this->getAttribute('blocks') ?? [];
        $serialized[$name] = [
            'type' => $name,
            'label' => $label ?? $name,
            'icon' => $icon,
            'fields' => array_map(static fn (Field $f): array => $f->toArray(), $fields),
        ];
        $this->attributes['blocks'] = $serialized;

        return $this;
    }

    /**
     * Получить fields-список для конкретного типа блока (для server-side
     * валидации payload'а).
     *
     * @return list<Field>|null
     */
    public function fieldsForBlock(string $type): ?array
    {
        return $this->blocks[$type] ?? null;
    }

    /**
     * Дозволенные типы блоков (для SPA-меню «добавить блок»).
     *
     * @return list<string>
     */
    public function allowedTypes(): array
    {
        return array_keys($this->blocks);
    }

    public function maxBlocks(int $max): static
    {
        $this->attributes['maxBlocks'] = $max;

        return $this;
    }

    public function reorderable(bool $reorderable = true): static
    {
        $this->attributes['reorderable'] = $reorderable;

        return $this;
    }
}
