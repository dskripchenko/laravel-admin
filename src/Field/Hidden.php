<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Невидимое поле для transport'а служебных значений (id, _token-like).
 *
 * Не рендерится в UI, но участвует в submit-payload.
 */
final class Hidden extends Field
{
    public function fieldType(): string
    {
        return 'hidden';
    }
}
