<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission;

use Dskripchenko\LaravelAdmin\I18n\Localize;

/**
 * The registry of every registered permission group.
 *
 * A singleton, bound in the container. The resources and the plugins add their
 * groups through `add()`, and the role matrix in the UI reads them with
 * `groups()` and `flat()`.
 *
 * Merging is supported: the same permission key may arrive from several
 * sources, and the last label wins — though it should not come to that, since
 * in production the permissions are expected to be unique by key.
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
            // Merge the items into the existing group
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
     * Without an argument: every group; with a panel: that panel's scope alone.
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
     * The flat list of every permission key.
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
     * Tells whether a permission key is known.
     */
    public function knows(string $key): bool
    {
        return in_array($key, $this->flat(), true);
    }

    /**
     * The human label of a permission key in the current locale, for messages
     * such as "no permission"; the key itself when no group declares it.
     */
    public function label(string $key): string
    {
        $label = null;
        foreach ($this->groups as $group) {
            // The last label wins, as it does when the groups are merged.
            $label = $group->items()[$key] ?? $label;
        }
        if (! is_string($label) || trim($label) === '') {
            return $key;
        }

        return (string) Localize::string($label);
    }

    /**
     * The label of a permission key through the registry bound in the
     * container; the key when there is none.
     */
    public static function labelOf(string $key): string
    {
        if ($key === '' || ! app()->bound(self::class)) {
            return $key;
        }

        /** @var self $registry */
        $registry = app(self::class);

        return $registry->label($key);
    }

    /**
     * Serializes for the UI and the manifest.
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
