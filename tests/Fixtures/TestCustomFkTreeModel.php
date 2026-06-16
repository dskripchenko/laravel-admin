<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Иерархическая модель с кастомным FK (не `parent_id`) — для проверки
 * что автодетект Resource::hierarchyParentKey() читает FK из самой
 * relation-декларации, а не предполагает имя.
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
