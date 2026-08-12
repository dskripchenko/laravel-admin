<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * An invisible field carrying internal values: an id, something token-like.
 *
 * It is not rendered in the UI but does take part in the submitted payload.
 */
final class Hidden extends Field
{
    public function fieldType(): string
    {
        return 'hidden';
    }
}
