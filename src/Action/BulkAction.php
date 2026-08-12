<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

/**
 * An action applied to several selected records at once.
 *
 * The SPA renders it as a button in the table's bulk toolbar, which appears as
 * soon as something is selected. The backend receives
 * {ids: [...], confirm: ...} in the payload.
 *
 * On the backend it is the `runMethod` action of ResourceController, which
 * calls the resource's method by name with the given set of ids.
 */
final class BulkAction extends Action
{
    public function __construct()
    {
        $this->position = ['bulk'];
    }

    public function type(): string
    {
        return 'bulk';
    }

    /**
     * The name of the resource's method that performs the action. It takes
     * `array<int, mixed> $ids` plus an optional payload.
     */
    public function method(string $method): self
    {
        $this->attributes['method'] = $method;

        return $this;
    }

    /**
     * The smallest selection at which the action becomes available.
     */
    public function requiresAtLeast(int $count): self
    {
        $this->attributes['requiresAtLeast'] = max(1, $count);

        return $this;
    }

    /**
     * The largest one, which keeps a pointless "delete 10000" from happening.
     */
    public function requiresAtMost(int $count): self
    {
        $this->attributes['requiresAtMost'] = $count;

        return $this;
    }
}
