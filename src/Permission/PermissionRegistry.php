<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission;

/**
 * Регистр всех зарегистрированных групп permissions.
 *
 * Singleton, биндится в DI. Resource'ы и Plugin'ы добавляют свои группы
 * через `add()`. UI матрицы ролей читает через `groups()` / `flat()`.
 *
 * Поддерживает merging: один и тот же ключ permission может быть добавлен
 * из разных источников — последняя метка побеждает (хотя и не должно так
 * быть — в production permissions должны быть уникальными по key).
 */
final class PermissionRegistry
{
    /** @var array<string, ItemPermission> group_name => ItemPermission */
    private array $groups = [];

    /** @var array<string, string> group_name => panel id */
    private array $panels = [];

    public function add(ItemPermission $item, string $panel = 'admin'): void
    {
        $this->panels[$item->group] ??= $panel;

        if (isset($this->groups[$item->group])) {
            // Merge items в существующую группу
            foreach ($item->items() as $key => $label) {
                $this->groups[$item->group]->addPermission($key, $label);
            }

            return;
        }

        $this->groups[$item->group] = $item;
    }

    /**
     * @param  list<ItemPermission>  $items
     */
    public function addMany(array $items, string $panel = 'admin'): void
    {
        foreach ($items as $item) {
            $this->add($item, $panel);
        }
    }

    /**
     * Без аргумента — все группы (BC); с панелью — только её скоуп.
     *
     * @return list<ItemPermission>
     */
    public function groups(?string $panel = null): array
    {
        if ($panel === null) {
            return array_values($this->groups);
        }

        return array_values(array_filter(
            $this->groups,
            fn (ItemPermission $g): bool => ($this->panels[$g->group] ?? 'admin') === $panel,
        ));
    }

    /**
     * Плоский список всех permission-keys.
     *
     * @return list<string>
     */
    public function flat(): array
    {
        $keys = [];
        foreach ($this->groups as $group) {
            foreach ($group->keys() as $key) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Известен ли permission-key.
     */
    public function knows(string $key): bool
    {
        return in_array($key, $this->flat(), true);
    }

    /**
     * Сериализация для UI / manifest.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (ItemPermission $g): array => $g->toArray(), $this->groups());
    }

    public function clear(): void
    {
        $this->groups = [];
        $this->panels = [];
    }
}
