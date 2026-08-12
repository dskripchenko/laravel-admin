<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

/**
 * Groups several actions under one dropdown button.
 *
 * It suits row actions with a long list of operations: a "More…" holding
 * Restore, ForceDelete, Replicate, Audit Trail and the rest.
 *
 * The visibility and the permissions are checked for each nested action
 * separately, and an empty dropdown is hidden altogether in the UI.
 */
final class DropDown extends Action
{
    /** @var list<Action> */
    private array $items = [];

    public function type(): string
    {
        return 'dropdown';
    }

    /**
     * @param  list<Action>  $actions
     */
    public function items(array $actions): self
    {
        $this->items = $actions;

        return $this;
    }

    public function add(Action $action): self
    {
        $this->items[] = $action;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $base = parent::toArray();
        $visibleItems = array_values(array_filter(
            $this->items,
            static fn (Action $a): bool => $a->isVisible(),
        ));

        return [
            ...$base,
            'items' => array_map(
                static fn (Action $a): array => $a->toArray(),
                $visibleItems,
            ),
        ];
    }
}
