<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Toggle-switch для boolean-поля.
 *
 * Класс называется `Switcher`, а не `Switch` — `switch` зарезервирован PHP
 * как ключевое слово (PHP 8.5 разрешает в namespace, но IDE/static analyzers
 * могут на это ругаться).
 */
final class Switcher extends Field
{
    public function fieldType(): string
    {
        return 'switch';
    }

    /**
     * Размер на UI: 'sm' | 'md' | 'lg'.
     */
    public function size(string $size): static
    {
        $this->attributes['size'] = $size;

        return $this;
    }

    /**
     * Подписи on/off у переключателя.
     */
    public function labels(string $on, string $off): static
    {
        $this->attributes['onLabel'] = $on;
        $this->attributes['offLabel'] = $off;

        return $this;
    }
}
