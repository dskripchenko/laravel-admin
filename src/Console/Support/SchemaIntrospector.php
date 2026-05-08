<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Анализирует БД-таблицу и (опц.) Eloquent-модель — возвращает структурное
 * описание для генератора Resource'ов.
 *
 * Используется `admin:make-section` / `admin:make-resource` командами.
 */
final class SchemaIntrospector
{
    /**
     * Список Eloquent-моделей host-проекта (поиск в app/Models/**).
     *
     * @return list<class-string<Model>>
     */
    public function discoverModels(): array
    {
        $models = [];
        foreach (['App\\Models\\', 'App\\'] as $namespace) {
            $base = base_path('app/'.($namespace === 'App\\' ? '' : 'Models/'));
            if (! is_dir($base)) {
                continue;
            }
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
            foreach ($iter as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $rel = str_replace([$base, '/', '.php'], ['', '\\', ''], $file->getPathname());
                $class = $namespace.ltrim($rel, '\\');
                if (! class_exists($class)) {
                    continue;
                }
                if (is_subclass_of($class, Model::class)) {
                    $models[] = $class;
                }
            }
        }

        return array_values(array_unique($models));
    }

    /**
     * Список таблиц в текущем connection (исключает служебные Laravel-таблицы).
     *
     * @return list<string>
     */
    public function listTables(): array
    {
        $exclude = [
            'migrations', 'failed_jobs', 'job_batches', 'jobs',
            'cache', 'cache_locks', 'sessions',
            'password_reset_tokens', 'password_resets',
            'personal_access_tokens', 'notifications',
            'admin_users', 'admin_roles', 'admin_settings', 'audit_logs',
            'dashboard_layouts', 'translations',
        ];

        try {
            $tables = collect(Schema::getTables())
                ->pluck('name')
                ->filter(fn (string $t): bool => ! in_array($t, $exclude, true))
                ->values()
                ->all();
        } catch (Throwable) {
            // Старые версии Laravel/драйвера могут не поддерживать getTables().
            $tables = [];
        }

        return $tables;
    }

    /**
     * Анализ таблицы: колонки + типы + nullable + indexes + unique.
     *
     * @return array{
     *     table: string,
     *     columns: list<array{name: string, type: string, nullable: bool, default: mixed, comment: ?string, is_primary: bool, is_unique: bool, is_indexed: bool, enum_values: ?list<string>}>,
     *     soft_deletes: bool,
     *     timestamps: bool,
     *     primary_key: string
     * }
     */
    public function analyzeTable(string $table): array
    {
        $rawCols = Schema::getColumns($table);
        $indexes = Schema::getIndexes($table);

        $primaryKey = 'id';
        $uniques = [];
        $indexed = [];
        foreach ($indexes as $idx) {
            $cols = $idx['columns'] ?? [];
            if ($idx['primary'] ?? false) {
                $primaryKey = $cols[0] ?? 'id';
            }
            if ($idx['unique'] ?? false) {
                foreach ($cols as $c) {
                    $uniques[$c] = true;
                }
            }
            foreach ($cols as $c) {
                $indexed[$c] = true;
            }
        }

        $columns = [];
        $hasSoftDeletes = false;
        $hasTimestamps = ['created_at' => false, 'updated_at' => false];

        foreach ($rawCols as $col) {
            $name = $col['name'];
            $type = strtolower($col['type_name'] ?? $col['type'] ?? 'string');

            if ($name === 'deleted_at') {
                $hasSoftDeletes = true;
            }
            if (isset($hasTimestamps[$name])) {
                $hasTimestamps[$name] = true;
            }

            $enumValues = null;
            if (str_starts_with($type, 'enum')) {
                // MySQL: 'enum(\'a\',\'b\')'
                if (preg_match('/enum\\(([^)]*)\\)/i', $col['type'] ?? '', $m)) {
                    $enumValues = array_map(
                        static fn (string $s): string => trim($s, " '\""),
                        explode(',', $m[1]),
                    );
                }
            }

            $columns[] = [
                'name' => $name,
                'type' => $type,
                'nullable' => (bool) ($col['nullable'] ?? false),
                'default' => $col['default'] ?? null,
                'comment' => $col['comment'] ?? null,
                'is_primary' => $name === $primaryKey,
                'is_unique' => isset($uniques[$name]),
                'is_indexed' => isset($indexed[$name]),
                'enum_values' => $enumValues,
            ];
        }

        return [
            'table' => $table,
            'columns' => $columns,
            'soft_deletes' => $hasSoftDeletes,
            'timestamps' => $hasTimestamps['created_at'] && $hasTimestamps['updated_at'],
            'primary_key' => $primaryKey,
        ];
    }

    /**
     * Анализ модели: fillable, casts, relations через Reflection.
     *
     * @param  class-string<Model>  $modelClass
     * @return array{
     *     model: class-string<Model>,
     *     table: string,
     *     fillable: list<string>,
     *     guarded: list<string>,
     *     hidden: list<string>,
     *     casts: array<string, string>,
     *     dates: list<string>,
     *     relations: list<array{name: string, type: string, related: ?class-string, foreign_key: ?string, owner_key: ?string}>,
     *     soft_deletes: bool
     * }
     */
    public function analyzeModel(string $modelClass): array
    {
        /** @var Model $instance */
        $instance = new $modelClass;

        $relations = $this->discoverRelations($modelClass);

        $usesSoftDeletes = false;
        $traits = class_uses_recursive($modelClass);
        if (in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', $traits, true)) {
            $usesSoftDeletes = true;
        }

        return [
            'model' => $modelClass,
            'table' => $instance->getTable(),
            'fillable' => $instance->getFillable(),
            'guarded' => $instance->getGuarded(),
            'hidden' => $instance->getHidden(),
            'casts' => $instance->getCasts(),
            'dates' => method_exists($instance, 'getDates') ? $instance->getDates() : [],
            'relations' => $relations,
            'soft_deletes' => $usesSoftDeletes,
        ];
    }

    /**
     * Поиск relation-методов через reflection: public-методы без параметров,
     * возвращающие `Relation` instance.
     *
     * @param  class-string<Model>  $modelClass
     * @return list<array{name: string, type: string, related: ?class-string, foreign_key: ?string, owner_key: ?string}>
     */
    private function discoverRelations(string $modelClass): array
    {
        $relations = [];
        $instance = new $modelClass;
        $reflection = new ReflectionClass($modelClass);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class === Model::class) {
                continue;
            }
            if ($method->getNumberOfRequiredParameters() > 0) {
                continue;
            }
            // Skip Laravel framework boilerplate
            if (in_array($method->name, [
                'casts', 'getRouteKey', 'getRouteKeyName', 'newQuery',
                'fresh', 'refresh', 'replicate', 'is', 'isNot', 'load',
                'loadMissing', 'loadMorph', 'getTable', 'usesTimestamps',
            ], true)) {
                continue;
            }
            try {
                $result = $method->invoke($instance);
            } catch (Throwable) {
                continue;
            }
            if (! $result instanceof Relation) {
                continue;
            }

            $type = $this->relationType($result);
            $related = $result->getRelated()::class;

            $foreignKey = method_exists($result, 'getForeignKeyName')
                ? $result->getForeignKeyName()
                : null;
            $ownerKey = method_exists($result, 'getOwnerKeyName')
                ? $result->getOwnerKeyName()
                : null;

            $relations[] = [
                'name' => $method->name,
                'type' => $type,
                'related' => $related,
                'foreign_key' => $foreignKey,
                'owner_key' => $ownerKey,
            ];
        }

        return $relations;
    }

    private function relationType(Relation $relation): string
    {
        return match (true) {
            $relation instanceof BelongsTo => 'BelongsTo',
            $relation instanceof BelongsToMany => 'BelongsToMany',
            $relation instanceof HasMany => 'HasMany',
            $relation instanceof HasOne => 'HasOne',
            $relation instanceof MorphTo => 'MorphTo',
            $relation instanceof MorphOne => 'MorphOne',
            $relation instanceof MorphMany => 'MorphMany',
            $relation instanceof MorphToMany => 'MorphToMany',
            default => class_basename($relation),
        };
    }

    /**
     * Подбирает «человекочитаемую» display-колонку для related-модели.
     * Используется RelationSelect->display(). Берёт первую существующую
     * среди name/title/label/email/code/slug/{primary}.
     *
     * @param  class-string<Model>  $relatedClass
     */
    public function pickDisplayColumn(string $relatedClass): string
    {
        $instance = new $relatedClass;
        $candidates = ['name', 'title', 'label', 'email', 'code', 'slug', 'username'];

        try {
            $cols = collect(Schema::getColumns($instance->getTable()))->pluck('name')->all();
            foreach ($candidates as $cand) {
                if (in_array($cand, $cols, true)) {
                    return $cand;
                }
            }
            // fallback — первая string-column
            foreach (Schema::getColumns($instance->getTable()) as $col) {
                $type = strtolower($col['type_name'] ?? '');
                if (in_array($type, ['varchar', 'char', 'text', 'string'], true)) {
                    return $col['name'];
                }
            }
        } catch (Throwable) {
            // ignore
        }

        return $instance->getKeyName();
    }
}
