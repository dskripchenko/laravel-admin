<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Строка с автогенерацией криптослучайного значения на create-форме
 * (токены, secret keys). SPA генерирует через crypto.getRandomValues
 * при монтировании (если значение пусто) + кнопка «Сгенерировать».
 */
final class Generated extends Field
{
    public function fieldType(): string
    {
        return 'generated-field';
    }

    /**
     * Длина генерируемой строки (default 32).
     */
    public function length(int $length): static
    {
        $this->attributes['length'] = max(1, $length);

        return $this;
    }

    /**
     * Алфавит генерации (default a-zA-Z0-9).
     */
    public function charset(string $charset): static
    {
        $this->attributes['charset'] = $charset;

        return $this;
    }

    /**
     * Автогенерация при монтировании, если значение пусто (default true).
     */
    public function autogenerate(bool $on = true): static
    {
        $this->attributes['autogenerate'] = $on;

        return $this;
    }
}
