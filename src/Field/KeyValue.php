<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A field for editing an associative array; the state is <string, mixed>.
 *
 * The UI is a table of `[key, value]` rows with add and remove buttons. When
 * the keys are known in advance, Group is the better fit; KeyValue is for
 * arbitrary metadata, settings and env-style configuration.
 */
final class KeyValue extends Field
{
    public function fieldType(): string
    {
        return 'key_value';
    }

    public function keyLabel(string $label): static
    {
        $this->attributes['keyLabel'] = $label;

        return $this;
    }

    public function valueLabel(string $label): static
    {
        $this->attributes['valueLabel'] = $label;

        return $this;
    }

    public function addable(bool $addable = true): static
    {
        $this->attributes['addable'] = $addable;

        return $this;
    }

    public function removable(bool $removable = true): static
    {
        $this->attributes['removable'] = $removable;

        return $this;
    }

    /**
     * Restricts the keys to a list, for constrained metadata.
     *
     * @param  list<string>  $allowed
     */
    public function allowedKeys(array $allowed): static
    {
        $this->attributes['allowedKeys'] = $allowed;

        return $this;
    }
}
