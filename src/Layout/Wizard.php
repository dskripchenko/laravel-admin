<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use InvalidArgumentException;

/**
 * A multi-step form — a wizard.
 *
 * It is made of steps, each shown on its own with Next and Prev navigation. A
 * step may carry validation rules, and moving forward is blocked while it is
 * invalid.
 *
 * The import wizard and the onboarding both use it.
 */
final class Wizard extends Layout
{
    /**
     * @param  list<Step>  $steps
     */
    public static function make(array $steps = []): self
    {
        $instance = new self;
        foreach ($steps as $step) {
            $instance->addStep($step);
        }

        return $instance;
    }

    public function type(): string
    {
        return 'wizard';
    }

    public function addStep(Step $step): self
    {
        $this->children[] = $step;

        return $this;
    }

    /**
     * The submit action, as the name of a screen's method called through runMethod.
     */
    public function submit(string $method): self
    {
        $this->props['submitMethod'] = $method;

        return $this;
    }

    /**
     * The linear mode, which is the default, or freeForm, where one may jump
     * between the steps in any order.
     */
    public function freeForm(bool $freeForm = true): self
    {
        $this->props['freeForm'] = $freeForm;

        return $this;
    }

    public function persistKey(string $key): self
    {
        if (trim($key) === '') {
            throw new InvalidArgumentException('Wizard persist key cannot be empty');
        }
        $this->props['persistKey'] = $key;

        return $this;
    }
}
