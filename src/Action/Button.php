<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

/**
 * A button in a command bar or a row, bound to a command method of a screen
 * or a resource.
 *
 * With `Button::make('Save')->method('save')`, a click makes the SPA POST to
 * the current controller with a body of `{method: 'save', state, parameters}`.
 */
final class Button extends Action
{
    public function type(): string
    {
        return 'button';
    }

    public function method(string $method): self
    {
        $this->attributes['method'] = $method;

        return $this;
    }
}
