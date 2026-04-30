<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use InvalidArgumentException;

/**
 * Многошаговая форма (Wizard).
 *
 * Состоит из Step'ов — каждый шаг показывается отдельно с навигацией
 * Next/Prev. Каждый Step может иметь validation rules — переход вперёд
 * блокируется если шаг невалиден.
 *
 * Используется в импорт-мастере (фаза P13) и onboarding'е.
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
     * Submit-action как имя method'а на Screen'е (вызывается через runMethod).
     */
    public function submit(string $method): self
    {
        $this->props['submitMethod'] = $method;

        return $this;
    }

    /**
     * Линейный режим (default) или freeForm — пользователь может прыгать
     * между шагами в любом порядке.
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
