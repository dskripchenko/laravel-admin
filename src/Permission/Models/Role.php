<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An administrator's role.
 *
 * Each role holds a slug, a localized name and a JSON array of permissions. It
 * is assigned to a user through the polymorphic `admin_role_assignments`
 * pivot, so that several administrator models — several guards — are
 * supported.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property list<string> $permissions
 * @property bool $is_system The system roles, Super Admin among them, cannot be deleted
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
     * Tells whether the role holds a permission. Glob wildcards work:
     *
     *   `*`                      — every permission
     *   `admin.users.*`          — every sub-key of a section
     *   `admin.content.*.view`   — view access to any content resource
     *   `admin.*.view`           — view access to everything
     *
     * It goes through `fnmatch()`, where `*` matches anything at all, dots
     * included, as POSIX glob does without `FNM_PATHNAME`.
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
