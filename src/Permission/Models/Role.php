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
 * @property array<array-key, mixed> $permissions A JSON list of permission strings — by convention, not by enforcement: see hasPermission()
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
        // The column is a JSON list of strings, but nothing enforces that: a
        // seed, an import or a hand-written row can put anything in there. A
        // non-string used to reach str_contains() and take the whole panel down
        // with a TypeError — a 500 on every request of a user holding that
        // role, from a permission check that should merely have said "no".
        //
        // Such an entry is skipped rather than interpreted. A map of the
        // `['printable.templates' => 1]` shape looks like an obvious intent to
        // grant, and guessing at it would GRANT access off a format we neither
        // document nor validate. In a permission check the safe direction is
        // "no".
        $permissions = array_values(array_filter(
            (array) $this->permissions,
            static fn (mixed $granted): bool => is_string($granted),
        ));

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
