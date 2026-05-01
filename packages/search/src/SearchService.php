<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminSearch;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdminSearch\Concerns\Searchable;
use Dskripchenko\LaravelAdminSearch\Drivers\SearchDriver;

/**
 * Запускает search по всем Resource'ам, использующим trait Searchable,
 * с permission-фильтрацией.
 */
final class SearchService
{
    public function __construct(
        private readonly Admin $admin,
        private readonly SearchDriver $driver,
    ) {}

    /**
     * @param  callable(string $permission): bool  $hasPermission
     * @return array<int, array{
     *     resource: string,
     *     label: string,
     *     icon: string|null,
     *     priority: int,
     *     items: list<array{id: int|string, title: string, subtitle: ?string, url: string}>
     * }>
     */
    public function search(string $query, callable $hasPermission, ?int $perResource = null): array
    {
        $perResource ??= (int) config('admin-search.per_resource', 10);
        $minLength = (int) config('admin-search.min_length', 2);

        if (mb_strlen(trim($query)) < $minLength) {
            return [];
        }

        $groups = [];
        foreach ($this->admin->getResources() as $class) {
            if (! class_exists($class)) {
                continue;
            }
            // Trait check: только Resource'ы с trait Searchable участвуют.
            if (! in_array(Searchable::class, class_uses_recursive($class), true)) {
                continue;
            }

            // Permission check — обращаемся к статическому permission()-методу.
            $base = $this->resolvePermission($class);
            if ($base !== null && ! $hasPermission($base.'.view')) {
                continue;
            }

            /** @var \Dskripchenko\LaravelAdmin\Resource\Resource&Searchable $resource */
            $resource = new $class;
            $fields = $resource->searchableFields();
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
            $modelClass = $class::$model ?? '';
            if ($fields === []) {
                continue;
            }

            $rows = $this->driver->search($modelClass, $fields, $query, $perResource);
            if ($rows === []) {
                continue;
            }

            $items = [];
            foreach ($rows as $row) {
                $titleField = $resource->searchTitle();
                $subField = $resource->searchSubtitle();
                $items[] = [
                    'id' => $row['id'] ?? '',
                    'title' => (string) ($row[$titleField] ?? ($row['id'] ?? '')),
                    'subtitle' => $subField !== null ? (string) ($row[$subField] ?? '') : null,
                    'url' => $resource->searchUrl($row),
                ];
            }

            $groups[] = [
                'resource' => $class::slug(),
                'label' => $class::label(),
                'icon' => $resource->searchIcon(),
                'priority' => $resource->searchPriority(),
                'items' => $items,
            ];
        }

        usort(
            $groups,
            fn (array $a, array $b): int => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0),
        );

        return $groups;
    }

    /**
     * Базовый permission-key для Resource'а (admin.{slug}). null если
     * static::permission() недоступен.
     *
     * @param  class-string  $class
     */
    private function resolvePermission(string $class): ?string
    {
        if (! method_exists($class, 'permission')) {
            return null;
        }

        return (string) $class::permission();
    }
}
