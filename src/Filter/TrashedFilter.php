<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Filter;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Tri-state фильтр для SoftDeletes-моделей.
 *
 * Значения:
 *   - 'without' (default) → только не-trashed (Eloquent default).
 *   - 'with' → trashed + не-trashed (`->withTrashed()`).
 *   - 'only' → только trashed (`->onlyTrashed()`).
 *
 * URL: `?filters[trashed]=only`.
 */
final class TrashedFilter extends Filter
{
    public static function for(string $field = 'trashed'): static
    {
        return parent::for($field);
    }

    public function type(): string
    {
        return 'trashed';
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        $value = is_string($value) ? $value : '';

        if ($value !== 'with' && $value !== 'only') {
            return $query;
        }

        $model = $query->getModel();
        $usesSoftDeletes = in_array(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive($model::class),
            true,
        );
        if (! $usesSoftDeletes) {
            return $query;
        }

        // SoftDeletes-scope добавляет global scope SoftDeletingScope. withTrashed/
        // onlyTrashed реализованы там как macro/method; обходим без method-magic
        // через withoutGlobalScope + (для 'only') явный where deleted_at IS NOT NULL.
        $query = $query->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class);
        if ($value === 'only') {
            $deletedAtColumn = method_exists($model, 'getDeletedAtColumn')
                ? $model->getDeletedAtColumn()
                : 'deleted_at';
            $query = $query->whereNotNull($model->getTable().'.'.$deletedAtColumn);
        }

        return $query;
    }
}
