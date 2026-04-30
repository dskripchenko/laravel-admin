<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Тэги — список произвольных строк.
 *
 * State хранится как list<string>. Подсказки `suggestions(...)` отображаются
 * в dropdown'е; они не ограничивают ввод (creatable=true).
 */
final class TagsInput extends Field
{
    public function fieldType(): string
    {
        return 'tags';
    }

    /**
     * @param  list<string>  $tags
     */
    public function suggestions(array $tags): static
    {
        $this->attributes['suggestions'] = $tags;

        return $this;
    }

    public function maxItems(int $max): static
    {
        $this->attributes['maxItems'] = $max;

        return $this;
    }

    /**
     * Разделитель ввода (Enter / запятая / точка с запятой). Default: Enter.
     */
    public function separator(string $separator): static
    {
        $this->attributes['separator'] = $separator;

        return $this;
    }
}
