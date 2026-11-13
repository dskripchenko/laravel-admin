<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Navigation;

use Illuminate\Database\Eloquent\Model;

/**
 * A user's own navigation in one panel: the favourites, the recent pages and
 * the folded menu groups. See NavigationController.
 *
 * @property int $id
 * @property string $owner_type
 * @property string $owner_id
 * @property string $panel
 * @property list<array<string, mixed>>|null $favorites
 * @property list<array<string, mixed>>|null $recent
 * @property array<string, bool>|null $menu_groups
 */
final class NavigationState extends Model
{
    protected $table = 'admin_navigation_states';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'panel',
        'favorites',
        'recent',
        'menu_groups',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'favorites' => 'array',
            'recent' => 'array',
            'menu_groups' => 'array',
        ];
    }

    /**
     * The user's row in the panel, new and unsaved when there is none yet.
     */
    public static function for(Model $user, string $panel): self
    {
        /** @var self $row */
        $row = self::query()->firstOrNew([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => (string) $user->getKey(),
            'panel' => $panel,
        ]);

        return $row;
    }

    /**
     * The saved state of the menu groups, without reading a row that is not there.
     *
     * @return array<string, bool>
     */
    public static function menuGroupsOf(?Model $user, string $panel): array
    {
        if ($user === null) {
            return [];
        }
        try {
            $row = self::query()
                ->where('owner_type', $user->getMorphClass())
                ->where('owner_id', (string) $user->getKey())
                ->where('panel', $panel)
                ->first();
        } catch (\Throwable) {
            // The package's migration has not been run yet: no saved state.
            return [];
        }
        $groups = $row?->menu_groups;

        return is_array($groups) ? array_map(static fn (mixed $v): bool => (bool) $v, $groups) : [];
    }
}
