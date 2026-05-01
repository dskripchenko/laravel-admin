<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminSearch\Drivers;

/**
 * Через Laravel\Scout::search(). Активируется если в host'е установлен
 * laravel/scout и модели Resource'ов имеют trait `Laravel\Scout\Searchable`.
 *
 * Поля игнорируются — Scout сам решает что индексировать на основе
 * model::toSearchableArray().
 */
final class ScoutSearchDriver implements SearchDriver
{
    public function search(string $modelClass, array $fields, string $query, int $limit): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }
        // Scout не обязательно установлен; проверяем trait наличия.
        if (! method_exists($modelClass, 'search')) {
            return [];
        }

        $rows = $modelClass::search($query)->take($limit)->get();

        if (! is_iterable($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[] = is_object($row) && method_exists($row, 'toArray')
                ? $row->toArray()
                : (array) $row;
        }

        return $result;
    }
}
