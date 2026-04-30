<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Rating — звёздный/иконочный рейтинг (значение 0..count).
 */
final class Rating extends Field
{
    public function fieldType(): string
    {
        return 'rating';
    }

    public function count(int $count): static
    {
        $this->attributes['count'] = $count;

        return $this;
    }

    public function half(bool $half = true): static
    {
        $this->attributes['half'] = $half;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->attributes['icon'] = $icon;

        return $this;
    }
}
