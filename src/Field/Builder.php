<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A page builder — an array of blocks of different types, each with its own
 * set of fields.
 *
 * The block types are declared through `block(name, fields)`. The state is a
 * list<{type: 'name', data: {...}}>. The SPA renders a drag-and-drop list of
 * blocks with an inline editor, and the backend serializes it into a JSON
 * column.
 *
 * For example:
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
     * Declares a block type with its fields.
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
     * Returns the field list of a particular block type, for validating the
     * payload on the server.
     *
     * @return list<Field>|null
     */
    public function fieldsForBlock(string $type): ?array
    {
        return $this->blocks[$type] ?? null;
    }

    /**
     * The block types on offer, for the SPA's "add a block" menu.
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
