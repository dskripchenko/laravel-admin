<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Filter;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * A tri-state filter for the models with SoftDeletes.
 *
 * The values:
 *   - 'without', the default → the untrashed alone, as Eloquent has it.
 *   - 'with' → both trashed and untrashed (`->withTrashed()`).
 *   - 'only' → the trashed alone (`->onlyTrashed()`).
 *
 * In a URL: `?filters[trashed]=only`.
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

        // SoftDeletes adds the SoftDeletingScope global scope, where
        // withTrashed and onlyTrashed live as macros. We work around the magic
        // with withoutGlobalScope plus, for 'only', an explicit
        // where deleted_at IS NOT NULL.
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
