<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Текстовый input.
 *
 * `type` (HTML) — text по умолчанию, переопределяется через `->type('email')`.
 *
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
