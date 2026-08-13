<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A hierarchical model with a custom FK (not `parent_id`) — to check that the
 * autodetection in Resource::hierarchyParentKey() reads the FK from the relation
 * declaration itself rather than assuming the name.
 *
 * @internal
 */
final class TestCustomFkTreeModel extends Model
{
    protected $table = 'tree_custom';

    protected $guarded = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'category_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'category_id');
    }
}
