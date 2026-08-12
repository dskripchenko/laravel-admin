<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A string that generates a cryptographically random value on a create form —
 * tokens, secret keys. The SPA generates it with crypto.getRandomValues on
 * mount, when the value is empty, and offers a "Generate" button.
 */
final class Generated extends Field
{
    public function fieldType(): string
    {
        return 'generated-field';
    }

    /**
     * The generated string's length; 32 by default.
     */
    public function length(int $length): static
    {
        $this->attributes['length'] = max(1, $length);

        return $this;
    }

    /**
     * The alphabet used; a-zA-Z0-9 by default.
     */
    public function charset(string $charset): static
    {
        $this->attributes['charset'] = $charset;

        return $this;
    }

    /**
     * Whether to generate on mount when the value is empty; true by default.
     */
    public function autogenerate(bool $on = true): static
    {
        $this->attributes['autogenerate'] = $on;

        return $this;
    }
}
