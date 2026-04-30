<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Поле для редактирования кода (Monaco/CodeMirror на стороне SPA).
 *
 * Конкретный редактор подключается на фронте — backend только декларирует
 * language/theme/height. Полный список языков — у SPA-обёртки.
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
