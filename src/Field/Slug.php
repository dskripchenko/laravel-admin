<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Illuminate\Support\Str;

/**
 * A URL slug generated from a source field.
 *
 * The SPA updates it automatically: as the source changes it applies
 * `Str::slug` and writes the result into this field's state, unless the slug
 * has been edited by hand. The backend's `generate()` does the same conversion
 * on the server, for tests and for the cases where there is no SPA.
 */
final class Slug extends Field
{
    public function fieldType(): string
    {
        return 'slug';
    }

    /**
     * The name of the other field, in the same form, the slug is generated from.
     */
    public function from(string $sourceField): static
    {
        $this->attributes['from'] = $sourceField;

        return $this;
    }

    public function separator(string $separator): static
    {
        $this->attributes['separator'] = $separator;

        return $this;
    }

    /**
     * Whether to follow every change of the source field; true by default.
     * With false it follows only the first one, and then the slug parts ways
     * with the source.
     */
    public function reactive(bool $reactive = true): static
    {
        $this->attributes['reactive'] = $reactive;

        return $this;
    }

    /**
     * Generates a slug from a string, for the backend's own cases: tests and
     * the fallback where there is no SPA.
     */
    public static function generate(string $source, string $separator = '-'): string
    {
        return Str::slug($source, $separator);
    }
}
