<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a model's table has a column — for the optional columns the panel
 * writes on the user (locale, theme, last_login_at), which a host's own users
 * table in the shared strategy may not have.
 *
 * The column list is read once per connection and table and kept for the
 * request: the instance is bound `scoped`, so a long-lived worker (Octane)
 * sees a migration on its next request.
 */
final class TableColumns
{
    /** @var array<string, array<string, true>> `connection|table` => column set */
    private array $columns = [];

    public static function has(Model $model, string $column): bool
    {
        return app(self::class)->check($model, $column);
    }

    public function check(Model $model, string $column): bool
    {
        $connection = $model->getConnectionName() ?? (string) config('database.default');
        $table = $model->getTable();
        $key = $connection.'|'.$table;

        if (! isset($this->columns[$key])) {
            $this->columns[$key] = array_fill_keys(
                array_map('strtolower', Schema::connection($connection)->getColumnListing($table)),
                true,
            );
        }

        return isset($this->columns[$key][strtolower($column)]);
    }
}
