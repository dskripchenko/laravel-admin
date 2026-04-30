<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

/**
 * Группировка нескольких action'ов под одну dropdown-кнопку.
 *
 * Для row-actions с большим набором операций: «Ещё...» с пунктами
 * Restore/ForceDelete/Replicate/Audit Trail/...
 *
 * Visibility и permissions проверяются для каждой вложенной action'ы
 * отдельно — пустой dropdown скрывается целиком на UI.
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
