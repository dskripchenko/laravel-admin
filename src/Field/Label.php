<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\I18n\Localize;

/**
 * A read-only display field — static text inside a form.
 *
 * Neither editable nor submitted. Its text comes from `->value()` or from the state, by name.
 */
final class Label extends Field
{
    public function fieldType(): string
    {
        return 'label';
    }

    /**
     * A static text given through `->value()` is a caption like a title, so
     * it is translated into the panel's language; a text read from the state
     * is data and is shown as it is.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = parent::toArray();
        if (is_array($out['attributes'] ?? null) && is_string($out['attributes']['value'] ?? null)) {
            $out['attributes']['value'] = Localize::string($out['attributes']['value']);
        }

        return $out;
    }
}
