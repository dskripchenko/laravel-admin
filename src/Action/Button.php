<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

/**
 * A button in a command bar or a row, bound to a command method of a screen
 * or a resource.
 *
 * With `Button::make('Save')->method('save')`, a click makes the SPA POST to
 * the screen's runMethod with a body of `{method: 'save', payload}` — the
 * form's state. A button may carry parameters of its own:
 *
 *     Button::make('Обновить')->method('sync')->parameters(['dictionary' => 'vehicle_marks'])
 *
 * They are sent as `params` next to the state, and the method receives them
 * as its second argument — `sync(array $state, array $parameters)`; a method
 * that declares one argument is called as before.
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

    /**
     * The button's own parameters — a dictionary's code, a record's id — which
     * reach the screen method as its second argument. They come back from the
     * browser, so the method validates them as any input.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function parameters(array $parameters): self
    {
        $this->attributes['parameters'] = $parameters;

        return $this;
    }

    /**
     * An alias of parameters().
     *
     * @param  array<string, mixed>  $parameters
     */
    public function params(array $parameters): self
    {
        return $this->parameters($parameters);
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        $parameters = $this->attributes['parameters'] ?? [];

        return is_array($parameters) ? $parameters : [];
    }
}
