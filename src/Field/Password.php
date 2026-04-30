<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Поле пароля с toggle-видимостью на UI.
 *
 * `revealable(true)` — позволяет SPA показать кнопку «глаз». Дефолтная
 * валидация: только если установлено required(), без implicit min/confirmed —
 * это решает разработчик через rules() или min()/confirmed().
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
     * Поле требует подтверждения (`{name}_confirmation`). Добавляет rule
     * `confirmed`, SPA должен отрендерить второе поле.
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
