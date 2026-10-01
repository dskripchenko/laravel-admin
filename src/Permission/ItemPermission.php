<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission;

use Dskripchenko\LaravelAdmin\I18n\Localize;

/**
 * Declares a group of permissions, with a fluent API.
 *
 *     ItemPermission::group('Systems')
 *         ->addPermission('admin.systems.users.view',   'Users: view')
 *         ->addPermission('admin.systems.users.update', 'Users: edit');
 *
 * Every `addPermission()` returns the group itself, so that many permissions
 * can be chained into one. Registering it goes through
 * `PermissionRegistry::add($itemPermission)` or `Admin::permissions([...])`.
 *
 * Pass the group name and the labels as source strings (or translation keys),
 * not as `__()` results: groups are registered once at boot, in whatever
 * locale the application started with, while `toArray()` translates them in
 * the locale of the request being served. A label that is already translated
 * still works — a string without a translation comes back unchanged.
 */
final class ItemPermission
{
    /** @var array<string, string> permission key => label */
    private array $items = [];

    public function __construct(public readonly string $group) {}

    public static function group(string $name): self
    {
        return new self($name);
    }

    public function addPermission(string $key, string $label): self
    {
        $this->items[$key] = $label;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->items);
    }

    /**
     * Serializes for the role matrix in the UI, translating the group name and
     * the labels in the current locale.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $items = [];
        foreach ($this->items as $key => $label) {
            $items[] = ['key' => $key, 'label' => (string) Localize::string($label)];
        }

        return [
            'name' => (string) Localize::string($this->group),
            'items' => $items,
        ];
    }
}
