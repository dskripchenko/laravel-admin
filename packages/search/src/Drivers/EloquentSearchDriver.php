<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminSearch\Drivers;

/**
 * LIKE %query% по каждому из searchable-полей. OR-семантика.
 *
 * Подходит для проектов до ~100K записей суммарно. Для больших таблиц
 * нужен ScoutSearchDriver.
 */
final class EloquentSearchDriver implements SearchDriver
{
    public function search(string $modelClass, array $fields, string $query, int $limit): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }
        if ($fields === []) {
            return [];
        }

        $instance = new $modelClass;
        $builder = $instance->newQuery();

        $like = '%'.$query.'%';
        $builder->where(function ($q) use ($fields, $like): void {
            foreach ($fields as $field) {
                $q->orWhere($field, 'LIKE', $like);
            }
        });

        $rows = $builder->limit($limit)->get();

        return $rows->map(fn ($r) => $r->toArray())->all();
    }
}
