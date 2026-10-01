<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

use Dskripchenko\LaravelAdmin\Field\Field;

/**
 * The read-only view of a form field: the whole serialized field travels with
 * the entry, and the SPA draws it with the view registered for the field's
 * type — a rendered markdown, a code block, stars for a rating, the option's
 * label for a radio, the path through a tree.
 *
 * The default Resource::infolist() uses it for the fields that have such a
 * view; a host may use it in its own infolist() too:
 *
 *     FieldEntry::fromField(Markdown::make('body')->title('Body'))
 */
final class FieldEntry extends Entry
{
    public function entryType(): string
    {
        return 'field';
    }

    public static function fromField(Field $field): self
    {
        $entry = self::make($field->name());
        $entry->attributes['label'] = (string) ($field->getAttributes()['title'] ?? $field->name());
        $entry->attributes['field'] = $field->toArray();

        return $entry;
    }
}
