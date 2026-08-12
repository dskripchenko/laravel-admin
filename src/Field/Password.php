<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A password field whose visibility can be toggled in the UI.
 *
 * `revealable(true)` lets the SPA show the eye button. As for validation, only
 * required() applies by default: no implicit min or confirmed — the developer
 * decides those through rules(), min() or confirmed().
 *
 * @method $this min(int $length)
 * @method $this max(int $length)
 */
final class Password extends Field
{
    public function fieldType(): string
    {
        return 'password';
    }

    public function revealable(bool $revealable = true): static
    {
        $this->attributes['revealable'] = $revealable;

        return $this;
    }

    /**
     * The field needs a confirmation (`{name}_confirmation`). It adds the
     * `confirmed` rule, and the SPA is expected to render the second field.
     */
    public function confirmed(bool $confirmed = true): static
    {
        $this->attributes['confirmed'] = $confirmed;
        if ($confirmed && ! in_array('confirmed', $this->rules, true)) {
            $this->rules[] = 'confirmed';
        }

        return $this;
    }
}
