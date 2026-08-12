<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * The toggle switch of a boolean field.
 *
 * The class is called `Switcher` rather than `Switch` because `switch` is a
 * reserved word in PHP. PHP 8.5 allows it inside a namespace, but IDEs and
 * static analyzers may still complain.
 */
final class Switcher extends Field
{
    public function fieldType(): string
    {
        return 'switch';
    }

    /**
     * The size in the UI: 'sm' | 'md' | 'lg'.
     */
    public function size(string $size): static
    {
        $this->attributes['size'] = $size;

        return $this;
    }

    /**
     * The switch's on and off labels.
     */
    public function labels(string $on, string $off): static
    {
        $this->attributes['onLabel'] = $on;
        $this->attributes['offLabel'] = $off;

        return $this;
    }
}
