<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Closure;
use Dskripchenko\LaravelAdmin\I18n\Localize;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Permission\PermissionRegistry;

/**
 * The permissions of a role as a matrix: groups as rows — folded, each with a
 * three-state checkbox and "N of M" — a search over the labels and the keys,
 * "Only selected", "Select all" and the total "Selected N of M".
 *
 *     PermissionMatrix::make('permission_keys')
 *         ->title('Права')
 *         ->groups([
 *             'Клиенты › Очереди' => [
 *                 'clients.claims' => 'Обращения и претензии',
 *                 'clients.callbacks' => 'Обратные звонки',
 *             ],
 *         ])
 *         ->extraColumn('ПДн без маски', ['clients.claims' => 'clients.claims.reveal']);
 *
 * The value is a list of the checked keys — the extra column's keys among
 * them. How a role stores it (a JSON list, Orchid's `{key: 1}` map) is the
 * resource's business: fill and save it there.
 *
 * `groups()` takes a list of `['label' => …, 'items' => [['key', 'label',
 * 'hint'?, 'extra'?]]]` too, or a closure returning either shape — resolved
 * when the form is serialized, never while the routes are built.
 * `fromRegistry()` takes the groups declared through Admin::permissions().
 */
final class PermissionMatrix extends Field
{
    /** @var array<int|string, mixed>|Closure(): array<int|string, mixed>|null */
    private array|Closure|null $groups = null;

    private ?string $registryPanel = null;

    private bool $fromRegistry = false;

    /** @var array<string, string> item key => the extra column's key */
    private array $extraKeys = [];

    public function fieldType(): string
    {
        return 'permission_matrix';
    }

    /**
     * @param  array<int|string, mixed>|Closure(): array<int|string, mixed>  $groups
     */
    public function groups(array|Closure $groups): static
    {
        $this->groups = $groups;

        return $this;
    }

    /**
     * The groups of PermissionRegistry (Admin::permissions()), of one panel
     * or of every one.
     */
    public function fromRegistry(?string $panel = null): static
    {
        $this->fromRegistry = true;
        $this->registryPanel = $panel;

        return $this;
    }

    /**
     * A second column of checkboxes: its caption and the key it grants for
     * each row (`'clients.claims' => 'clients.claims.reveal'`). A row without
     * one shows a dash.
     *
     * @param  array<string, string>  $keys
     */
    public function extraColumn(string $label, array $keys = []): static
    {
        $this->attributes['extraLabel'] = $label;
        $this->extraKeys = $keys;

        return $this;
    }

    /**
     * Opens every group from the start; folded by default when there are
     * more than five.
     */
    public function expanded(bool $expanded = true): static
    {
        $this->attributes['expanded'] = $expanded;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = parent::toArray();
        $out['attributes']['groups'] = $this->resolvedGroups();

        return $out;
    }

    /**
     * Every key the matrix offers — the extra column's included. For the
     * resource's `rules(['array', Rule::in(...)])` or its own checks.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [];
        foreach ($this->resolvedGroups() as $group) {
            foreach ($group['items'] as $item) {
                $keys[] = $item['key'];
                if ($item['extra'] !== null) {
                    $keys[] = $item['extra'];
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<array{label: string, items: list<array{key: string, label: string, hint: ?string, extra: ?string}>}>
     */
    private function resolvedGroups(): array
    {
        $raw = $this->groups instanceof Closure ? ($this->groups)() : ($this->groups ?? []);
        if ($this->fromRegistry) {
            foreach (app(PermissionRegistry::class)->groups($this->registryPanel) as $group) {
                /** @var ItemPermission $group */
                $raw[] = ['label' => $group->group, 'items' => $group->items()];
            }
        }

        $groups = [];
        foreach ($raw as $label => $group) {
            if (is_array($group) && array_key_exists('items', $group)) {
                $groupLabel = (string) ($group['label'] ?? $label);
                $items = (array) $group['items'];
            } else {
                $groupLabel = (string) $label;
                $items = (array) $group;
            }

            $list = [];
            foreach ($items as $key => $item) {
                if (is_array($item)) {
                    $itemKey = (string) ($item['key'] ?? $key);
                    $itemLabel = (string) ($item['label'] ?? $itemKey);
                    $hint = isset($item['hint']) ? (string) $item['hint'] : null;
                    $extra = isset($item['extra']) ? (string) $item['extra'] : null;
                } else {
                    $itemKey = (string) $key;
                    $itemLabel = (string) $item;
                    $hint = null;
                    $extra = null;
                }
                $list[] = [
                    'key' => $itemKey,
                    'label' => (string) Localize::string($itemLabel),
                    'hint' => $hint === null ? null : Localize::string($hint),
                    'extra' => $extra ?? ($this->extraKeys[$itemKey] ?? null),
                ];
            }

            $groups[] = ['label' => (string) Localize::string($groupLabel), 'items' => $list];
        }

        return $groups;
    }
}
