<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * One step inside a wizard.
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
     * The validation rules of the step's inputs. The wizard blocks the move
     * forward while the step is invalid.
     *
     * @param  array<string, list<string>>  $rules
     */
    public function rules(array $rules): self
    {
        $this->props['rules'] = $rules;

        return $this;
    }
}
