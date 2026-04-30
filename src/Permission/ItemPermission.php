<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission;

/**
 * Декларация группы permissions с fluent API.
 *
 *     ItemPermission::group('Системы')
 *         ->addPermission('admin.systems.users.view',   'Пользователи: просмотр')
 *         ->addPermission('admin.systems.users.update', 'Пользователи: редактирование');
 *
 * Возвращает себя из каждого `addPermission()`, чтобы можно было цепочкой
 * добавлять много permissions в одну группу. Зарегистрировать в
 * PermissionRegistry — через `PermissionRegistry::add($itemPermission)`
 * либо через `Admin::permissions([...])`.
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
     * Сериализация для UI матрицы ролей.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $items = [];
        foreach ($this->items as $key => $label) {
            $items[] = ['key' => $key, 'label' => $label];
        }

        return [
            'name' => $this->group,
            'items' => $items,
        ];
    }
}
