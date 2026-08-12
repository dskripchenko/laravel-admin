<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A numeric field with min, max and step.
 *
 * The SPA renders it as `<input type="number">`. The validation gains
 * `numeric` or `integer` automatically when ValidationRulesExporter exports
 * it.
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
     * The integer mode; floats by default.
     */
    public function integer(bool $integer = true): static
    {
        $this->attributes['integer'] = $integer;

        return $this;
    }
}
