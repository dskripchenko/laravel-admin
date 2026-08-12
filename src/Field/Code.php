<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A field for editing code, drawn by Monaco or CodeMirror on the SPA's side.
 *
 * Which editor that is, the frontend decides; the backend only declares the
 * language, the theme and the height. The full list of languages belongs to
 * the SPA's wrapper.
 *
 * @method $this height(int|string $height)
 */
final class Code extends Field
{
    public function fieldType(): string
    {
        return 'code';
    }

    public function language(string $language): static
    {
        $this->attributes['language'] = $language;

        return $this;
    }

    public function theme(string $theme): static
    {
        $this->attributes['theme'] = $theme;

        return $this;
    }

    public function lineNumbers(bool $on = true): static
    {
        $this->attributes['lineNumbers'] = $on;

        return $this;
    }
}
