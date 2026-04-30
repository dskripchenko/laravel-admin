<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Числовое поле с min/max/step.
 *
 * SPA рендерит как `<input type="number">`. Валидация автоматически
 * добавит `numeric`/`integer` при экспорте через ValidationRulesExporter.
 *
 * @method $this min(int|float $min)
 * @method $this max(int|float $max)
 * @method $this step(int|float $step)
 * @method $this prefix(string $prefix)
 * @method $this suffix(string $suffix)
 */
final class Number extends Field
{
    public function fieldType(): string
    {
        return 'number';
    }

    /**
     * Целочисленный режим. По умолчанию float.
     */
    public function integer(bool $integer = true): static
    {
        $this->attributes['integer'] = $integer;

        return $this;
    }
}
