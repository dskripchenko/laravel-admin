<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Поле для редактирования ассоциативного массива (state: <string, mixed>).
 *
 * UI: таблица из строк `[key, value]` с кнопками add/remove. Для
 * предсказуемых ключей — лучше использовать Group; KeyValue — для
 * произвольных метаданных, settings, env-style configs.
 */
final class KeyValue extends Field
{
    public function fieldType(): string
    {
        return 'key_value';
    }

    public function keyLabel(string $label): static
    {
        $this->attributes['keyLabel'] = $label;

        return $this;
    }

    public function valueLabel(string $label): static
    {
        $this->attributes['valueLabel'] = $label;

        return $this;
    }

    public function addable(bool $addable = true): static
    {
        $this->attributes['addable'] = $addable;

        return $this;
    }

    public function removable(bool $removable = true): static
    {
        $this->attributes['removable'] = $removable;

        return $this;
    }

    /**
     * Ограничить ключи списком (для constrained metadata).
     *
     * @param  list<string>  $allowed
     */
    public function allowedKeys(array $allowed): static
    {
        $this->attributes['allowedKeys'] = $allowed;

        return $this;
    }
}
