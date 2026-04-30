<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * Один шаг внутри Wizard'а.
 */
final class Step extends Layout
{
    /**
     * @param  list<Renderable>  $children
     */
    public static function make(string $title, array $children = []): self
    {
        $instance = new self;
        $instance->props['title'] = $title;
        $instance->children = $children;

        return $instance;
    }

    public function type(): string
    {
        return 'step';
    }

    public function description(string $description): self
    {
        $this->props['description'] = $description;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->props['icon'] = $icon;

        return $this;
    }

    /**
     * Validation rules для inputs шага. Wizard блокирует переход вперёд
     * если шаг не валиден.
     *
     * @param  array<string, list<string>>  $rules
     */
    public function rules(array $rules): self
    {
        $this->props['rules'] = $rules;

        return $this;
    }
}
