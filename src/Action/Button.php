<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

/**
 * Кнопка в commandBar/row, привязанная к command-методу Screen/Resource.
 *
 * `Button::make('Сохранить')->method('save')` — при клике SPA шлёт POST на
 * текущий controller с body `{method: 'save', state, parameters}`.
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
