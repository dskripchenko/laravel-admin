<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminSearch\Drivers;

use Illuminate\Database\Eloquent\Model;

/**
 * Контракт search-driver'а.
 *
 * Реализации:
 *   - EloquentSearchDriver — LIKE по полям, default
 *   - ScoutSearchDriver — через Laravel\Scout (если установлен), для
 *     production-grade indexed-search
 */
interface SearchDriver
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    public function search(string $modelClass, array $fields, string $query, int $limit): array;
}
