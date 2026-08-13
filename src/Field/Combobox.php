<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Concerns\HasOptions;

/**
 * A combobox — a select with autocompletion that also accepts an arbitrary
 * value, which makes it creatable. The SPA draws it as an input plus a
 * dropdown.
 *
 * The case it exists for: a value that comes from a known list which the panel
 * does not own. A model identifier of an AI provider is exactly that —
 * `claude-opus-5` has to be spelt precisely, remembering it is unreasonable,
 * and a closed select would go stale the day the provider ships a new model.
 * The list is a hint rather than a rule; use Select where the set really is
 * closed.
 *
 * Until 13.08.2026 the SPA drew this field as an ordinary Select: the type was
 * mapped to SelectField, so the suggestions worked and typing a value of one's
 * own did not. The class had shipped `creatable()` all along, and it changed
 * nothing.
 *
 * @method $this placeholder(string $placeholder)
 */
final class Combobox extends Field
{
    use HasOptions;

    public function fieldType(): string
    {
        return 'combobox';
    }

    /**
     * Allows values outside the list of options. On by default in the SPA —
     * that is the point of the field.
     */
    public function creatable(bool $creatable = true): static
    {
        $this->attributes['creatable'] = $creatable;

        return $this;
    }

    public function clearable(bool $clearable = true): static
    {
        $this->attributes['clearable'] = $clearable;

        return $this;
    }
}
