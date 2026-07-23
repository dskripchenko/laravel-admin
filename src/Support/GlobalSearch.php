<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * Глобальный поиск по всем зарегистрированным Resource'ам панели.
 *
 * Идёт по `Resource::searchableFields()` (колонки, помеченные `->search()`),
 * уважает `Resource::indexQuery()` — значит применяются soft-delete scope,
 * tenant-скоуп и host-овские ограничения (напр. скрытие client-ролей, BL-3).
 * Ресурсы, к которым у пользователя нет `.view`-права, пропускаются.
 */
final class GlobalSearch
{
    public function __construct(private readonly ResourceRegistry $resources) {}

    /**
     * @return list<array{slug: string, label: string, icon: string|null, items: list<array{id: mixed, title: string, subtitle: string|null, url: string}>, hasMore: bool, moreUrl: string}>
     */
    public function search(
        string $query,
        ?object $user = null,
        string $panel = 'admin',
        int $perResource = 5,
        int $maxGroups = 8,
    ): array {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $needle = '%'.$query.'%';
        $groups = [];

        foreach (array_keys($this->resources->all($panel)) as $slug) {
            if (count($groups) >= $maxGroups) {
                break;
            }

            $resource = $this->resources->resolve($slug);
            if ($resource === null) {
                continue;
            }

            $fields = array_values(array_filter(
                $resource->searchableFields(),
                static fn (string $f): bool => preg_match('/^[a-zA-Z0-9_]+$/', $f) === 1,
            ));
            if ($fields === []) {
                continue;
            }

            if (! $this->canView($user, $resource::permission())) {
                continue;
            }

            $builder = $resource->indexQuery();
            // pgsql ILIKE — полноценный Unicode case-insensitive (прод/стенд).
            // Прочие драйверы (sqlite в тестах) — LIKE: ASCII-нечувствителен,
            // Unicode совпадает посимвольно (для substring-поиска достаточно).
            $operator = $builder->getConnection() instanceof \Illuminate\Database\PostgresConnection ? 'ilike' : 'like';
            $builder->where(static function ($where) use ($fields, $needle, $operator): void {
                foreach ($fields as $field) {
                    $where->orWhere($field, $operator, $needle);
                }
            });

            /** @var \Illuminate\Database\Eloquent\Collection<int, Model> $rows */
            $rows = $builder->limit($perResource + 1)->get();
            if ($rows->isEmpty()) {
                continue;
            }

            $hasMore = $rows->count() > $perResource;
            $items = $rows->take($perResource)->map(static fn (Model $row): array => [
                'id' => $row->getKey(),
                'title' => $resource->recordTitle($row),
                'subtitle' => $resource->recordSubtitle($row),
                'url' => '/r/'.$slug.'/'.$row->getKey(),
            ])->values()->all();

            $groups[] = [
                'slug' => $slug,
                'label' => (string) $resource::label(),
                'icon' => $resource::$icon,
                'items' => $items,
                'hasMore' => $hasMore,
                'moreUrl' => '/r/'.$slug,
            ];
        }

        return $groups;
    }

    private function canView(?object $user, string $permissionBase): bool
    {
        // Модели без granular-прав (hasAccess-only, wildcard ['*']) и гость —
        // авторизация остаётся за backend action'ом; здесь не запираем.
        if ($user === null || ! method_exists($user, 'hasAccess')) {
            return true;
        }

        if ($permissionBase === '') {
            return true;
        }

        return $user->hasAccess($permissionBase.'.view');
    }
}
