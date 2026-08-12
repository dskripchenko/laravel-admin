<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A text input.
 *
 * The HTML `type` is text by default and is overridden with `->type('email')`.
 *
 * @method $this type(string $type)
 * @method $this mask(string $mask)
 * @method $this prefix(string $prefix)
 * @method $this suffix(string $suffix)
 */
final class Input extends Field
{
    public function fieldType(): string
    {
        return 'input';
    }
}
