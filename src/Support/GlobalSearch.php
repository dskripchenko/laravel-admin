<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * The global search across every resource registered in a panel.
 *
 * It goes through `Resource::searchableFields()` — the columns marked with
 * `->search()` — and respects `Resource::indexQuery()`, so the soft-delete
 * scope, the tenant scope and the host's own restrictions (hiding the client
 * roles, for one) all apply. The resources the user has no `.view` right for
 * are skipped.
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
            // On pgsql, ILIKE is properly case-insensitive over Unicode —
            // that is production and the stands. The other drivers (sqlite in
            // the tests) get LIKE, which ignores case for ASCII and matches
            // Unicode character by character, which is enough for a substring
            // search.
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
        // For the models without granular rights — hasAccess only, the
        // wildcard ['*'] — and for a guest, the authorization is left to the
        // backend action; nothing is locked here.
        if ($user === null || ! method_exists($user, 'hasAccess')) {
            return true;
        }

        if ($permissionBase === '') {
            return true;
        }

        return $user->hasAccess($permissionBase.'.view');
    }
}
