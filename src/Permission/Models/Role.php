<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Роль администратора.
 *
 * Каждая роль хранит slug + локализованное name + JSON-массив permissions.
 * Назначение пользователю — через polymorphic pivot `admin_role_assignments`,
 * чтобы поддерживать разные модели администраторов (multi-guard).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property list<string> $permissions
 * @property bool $is_system Системные роли (Super Admin) защищены от удаления
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Role extends Model
{
    use HasFactory;

    protected $table = 'admin_roles';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'permissions',
        'is_system',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
        ];
    }

    /**
     * Имеет ли роль конкретный permission. Поддерживает glob-wildcard'ы:
     *
     *   `*`                      — все permissions
     *   `admin.users.*`          — все sub-keys раздела
     *   `admin.content.*.view`   — view-доступ к любому контентному ресурсу
     *   `admin.*.view`           — view ко всему
     *
     * Реализация — через `fnmatch()`: `*` совпадает с любым содержимым
     * (включая точки), как POSIX glob без `FNM_PATHNAME`.
     */
    public function hasPermission(string $key): bool
    {
        $permissions = (array) $this->permissions;

        if (in_array('*', $permissions, true)) {
            return true;
        }

        if (in_array($key, $permissions, true)) {
            return true;
        }

        foreach ($permissions as $granted) {
            if ($granted === '') {
                continue;
            }
            if (str_contains($granted, '*') && fnmatch($granted, $key)) {
                return true;
            }
        }

        return false;
    }
}
