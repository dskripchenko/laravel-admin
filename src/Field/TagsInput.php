<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Tags — a list of arbitrary strings.
 *
 * The state is a list<string>. The `suggestions(...)` appear in a dropdown but
 * do not restrict what can be typed: this field is always creatable.
 */
final class TagsInput extends Field
{
    public function fieldType(): string
    {
        return 'tags';
    }

    /**
     * @param  list<string>  $tags
     */
    public function suggestions(array $tags): static
    {
        $this->attributes['suggestions'] = $tags;

        return $this;
    }

    /**
     * The grouped suggestions of the dropdown: every group has a label and a
     * list of items. The frontend's TagsField renders the group headings when
     * this is not empty, and it wins over the flat `suggestions`.
     *
     * @param  list<array{label: string, items: list<string>}>  $groups
     */
    public function suggestionsByGroup(array $groups): static
    {
        $this->attributes['suggestionsByGroup'] = $groups;

        return $this;
    }

    public function maxItems(int $max): static
    {
        $this->attributes['maxItems'] = $max;

        return $this;
    }

    /**
     * The input separator: Enter, a comma or a semicolon. Enter by default.
     */
    public function separator(string $separator): static
    {
        $this->attributes['separator'] = $separator;

        return $this;
    }
}
