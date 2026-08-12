<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Table;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The saved views — presets of the filters, sort, columns and per_page of a
 * list table.
 *
 * An administrator creates them as "my filters"; they may be private, owned by
 * that administrator, or global, with no owner, given the rights.
 *
 * The state lives in a JSON column:
 *   {
 *     filters: { col: value, ... },
 *     order:   [{ column, direction }, ...],
 *     columns: [name, ...],          // the visible columns and their order
 *     per_page: 25,
 *     q:       'free text'
 *   }
 *
 * @property int $id
 * @property string $resource_slug
 * @property string $name
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property array<string, mixed> $state
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class SavedView extends Model
{
    protected $table = 'admin_saved_views';

    protected $fillable = [
        'resource_slug',
        'name',
        'owner_type',
        'owner_id',
        'state',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The scope of the views a particular user may see: their own plus the
     * global ones, where owner_id is NULL.
     *
     * @param  Builder<SavedView>  $query
     * @return Builder<SavedView>
     */
    public function scopeVisibleTo(Builder $query, ?Model $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void {
            $q->whereNull('owner_id');
            if ($user !== null) {
                $q->orWhere(function (Builder $own) use ($user): void {
                    $own->where('owner_type', $user->getMorphClass())
                        ->where('owner_id', $user->getKey());
                });
            }
        });
    }
}
