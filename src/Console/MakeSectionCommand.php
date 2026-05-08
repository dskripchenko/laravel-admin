<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Dskripchenko\LaravelAdmin\Console\Support\AdminPluginUpdater;
use Dskripchenko\LaravelAdmin\Console\Support\FieldTypeInferrer;
use Dskripchenko\LaravelAdmin\Console\Support\ResourceWriter;
use Dskripchenko\LaravelAdmin\Console\Support\SchemaIntrospector;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * `php artisan admin:make-section`
 *
 * Главный мастер для создания нового раздела в админке. Запускается без
 * аргументов — все вводы интерактивно через Laravel Prompts.
 *
 * Шаги:
 *   1. Имя раздела (label) и singular
 *   2. Источник: Eloquent-модель (auto-discover) ИЛИ DB-таблица
 *   3. Анализ schema + relations
 *   4. Выбор полей формы (multiselect)
 *   5. Выбор колонок таблицы
 *   6. Permission base
 *   7. Иконка
 *   8. Меню — добавить как корневой / под существующий parent
 *   9. (опц.) создать Role с этими permissions
 *
 * На выходе:
 *   - app/Admin/Resources/{Name}Resource.php
 *   - Регистрация в app/Admin/AdminPlugin.php (создаётся если нет)
 *   - Admin::menu()->add(MenuNode::resource(...)) добавлено
 *   - (опц.) Role с permissions admin.{slug}.{view,create,update,delete}
 */
final class MakeSectionCommand extends Command
{
    protected $signature = 'admin:make-section
                            {--force : Перезаписать существующий Resource}';

    protected $description = 'Мастер создания раздела админки на основе таблицы или Eloquent-модели';

    public function handle(
        SchemaIntrospector $schema,
        FieldTypeInferrer $inferrer,
        ResourceWriter $writer,
        AdminPluginUpdater $updater,
        Filesystem $files,
    ): int {
        info('🧙 Wizard: новый раздел админки');
        note('Команда проанализирует таблицу/модель и сгенерирует Resource, '
            ."permissions и пункт меню.\n"
            .'На любом шаге можно отменить (Ctrl+C).');

        // === 1. Метаданные ===
        $singular = text(
            label: 'Singular label (например: Article)',
            placeholder: 'Article',
            required: true,
        );
        $plural = text(
            label: 'Plural label (для таблицы и меню)',
            default: Str::plural($singular),
            required: true,
        );

        // === 2. Источник ===
        $sourceType = select(
            label: 'Источник данных',
            options: [
                'model' => 'Eloquent-модель (auto-discover)',
                'table' => 'DB-таблица (без модели)',
            ],
            default: 'model',
        );

        $analysis = match ($sourceType) {
            'model' => $this->pickModel($schema),
            'table' => $this->pickTable($schema),
            default => null,
        };

        if ($analysis === null) {
            warning('Источник не выбран — отменено.');

            return self::FAILURE;
        }

        $modelClass = $analysis['model'] ?? null;
        $tableName = $analysis['table'];

        // === 3. Поля и колонки ===
        $columns = $analysis['columns'] ?? [];
        $relations = $analysis['relations'] ?? [];

        // RelationSelect display подбираем сразу через introspector
        $relations = array_map(function (array $rel) use ($schema): array {
            if ($rel['type'] === 'BelongsTo' && $rel['related'] !== null) {
                $rel['display'] = $schema->pickDisplayColumn($rel['related']);
            }

            return $rel;
        }, $relations);

        $allColumnNames = array_map(fn ($c) => $c['name'], $columns);
        $defaultFormColumns = array_values(array_filter(
            $allColumnNames,
            static fn (string $n): bool => ! in_array($n, ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'], true),
        ));

        $selectedFormColumns = multiselect(
            label: 'Поля для формы (create/edit)',
            options: array_combine($allColumnNames, $allColumnNames),
            default: $defaultFormColumns,
            scroll: 20,
            hint: 'Space — toggle, Enter — confirm',
        );

        $defaultTableColumns = array_values(array_filter(
            $allColumnNames,
            static fn (string $n): bool => ! in_array($n, ['updated_at', 'deleted_at', 'password', 'remember_token'], true),
        ));
        $selectedTableColumns = multiselect(
            label: 'Колонки в таблице (list)',
            options: array_combine($allColumnNames, $allColumnNames),
            default: $defaultTableColumns,
            scroll: 20,
        );

        // === 4. Permissions ===
        $slug = Str::kebab(Str::pluralStudly($singular));
        $permission = text(
            label: 'Базовое permission (auto-derived: .view/.create/.update/.delete)',
            default: 'admin.'.$slug,
            required: true,
        );

        // === 5. Icon ===
        $icon = text(
            label: 'Lucide icon name (см. lucide.dev)',
            default: $this->guessIcon($plural),
        );

        // === 6. Group (опц.) ===
        $group = text(
            label: 'Группа в sidebar (пусто — без группы)',
            default: '',
        );

        // === 7. Меню ===
        $addMenu = confirm(label: 'Добавить в меню?', default: true);
        $menuParent = '';
        if ($addMenu) {
            $menuParent = text(
                label: 'Parent-key в меню (пусто — корневой пункт)',
                default: '',
                hint: 'Например: shop, content, tools',
            );
        }

        // === 8. Role ===
        $createRole = confirm(label: 'Создать Role с этими permissions?', default: false);
        $roleName = '';
        $rolePerms = [];
        if ($createRole) {
            $roleName = text(label: 'Имя Role', default: $plural.' editor', required: true);
            $rolePerms = multiselect(
                label: 'Permissions для роли',
                options: [
                    "{$permission}.view" => 'View',
                    "{$permission}.create" => 'Create',
                    "{$permission}.update" => 'Update',
                    "{$permission}.delete" => 'Delete',
                ],
                default: ["{$permission}.view", "{$permission}.create", "{$permission}.update"],
            );
        }

        // === Generate ===
        info('🛠  Генерация...');

        $namespace = 'App\\Admin\\Resources';
        $className = $writer->classNameFor($singular, 'Resource');

        // Если таблица без модели — генерим её сначала
        if ($modelClass === null) {
            $modelClass = $this->ensureModel($tableName, $analysis, $writer, $files);
        }

        // Подготовить strings для stub'а
        $fieldsBlock = $this->buildFieldsBlock(
            $columns, $relations, $inferrer, $selectedFormColumns,
        );
        $columnsBlock = $this->buildColumnsBlock(
            $columns, $inferrer, $selectedTableColumns,
        );
        $filtersBlock = $this->buildFiltersBlock($columns, $inferrer);
        $searchableNames = $this->pickSearchable($columns);

        $extraImports = $this->buildExtraImports($columns, $relations);

        $vars = [
            'namespace' => $namespace,
            'class' => $className,
            'modelClass' => $modelClass,
            'modelShort' => class_basename($modelClass),
            'extraImports' => $extraImports,
            'icon' => $icon,
            'group' => $group !== '' ? "'{$group}'" : 'null',
            'slug' => $slug,
            'label' => $plural,
            'singularLabel' => $singular,
            'permission' => $permission,
            'fields' => $fieldsBlock,
            'columns' => $columnsBlock,
            'filters' => $filtersBlock,
            'searchable' => $searchableNames,
            'date' => date('Y-m-d'),
        ];

        $stub = $writer->stubPath('resource.stub');
        $target = $writer->classPath($namespace, $className);
        $created = $writer->fromStub($stub, $target, $vars, force: (bool) $this->option('force'));

        if (! $created) {
            warning("⚠ Файл уже существует: {$target}. Используйте --force для перезаписи.");

            return self::FAILURE;
        }
        info("✓ Создан: {$target}");

        // Регистрация в plugin
        $resourceFqcn = $namespace.'\\'.$className;
        $reg = $updater->registerResource($resourceFqcn);
        info("✓ Plugin: {$reg['path']} ({$reg['action']})");

        // Меню
        if ($addMenu) {
            $updater->ensureImport($reg['path'], 'Dskripchenko\\LaravelAdmin\\Menu\\MenuNode');
            $menu = $updater->addMenuNode('resource', $slug, $menuParent ?: null);
            info("✓ Menu: {$menu['action']}");
        }

        // Role
        if ($createRole && ! empty($rolePerms)) {
            $this->createRole($roleName, $rolePerms);
            info("✓ Role «{$roleName}» создана с ".count($rolePerms).' permissions');
        }

        info('✅ Готово!');
        $this->newLine();
        note('Что дальше:');
        $this->line('  1. Откройте '.$target.' и причешите fields/columns/filters');
        $this->line('  2. Перезапустите admin (composer dump-autoload && npm run build)');
        $this->line("  3. Откройте /admin/r/{$slug} — раздел готов");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pickModel(SchemaIntrospector $schema): ?array
    {
        $models = $schema->discoverModels();
        if ($models === []) {
            warning('Eloquent-модели не найдены в app/Models/.');

            return null;
        }
        $options = [];
        foreach ($models as $cls) {
            $options[$cls] = $cls;
        }
        $picked = select(
            label: 'Выберите модель',
            options: $options,
            scroll: 15,
        );

        $analysis = $schema->analyzeModel($picked);
        // Объединяем data из таблицы (для типов) с relations из модели
        $tableAnalysis = $schema->analyzeTable($analysis['table']);
        $analysis = array_merge($analysis, [
            'columns' => $tableAnalysis['columns'],
            'soft_deletes' => $analysis['soft_deletes'] || $tableAnalysis['soft_deletes'],
        ]);

        return $analysis;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pickTable(SchemaIntrospector $schema): ?array
    {
        $tables = $schema->listTables();
        if ($tables === []) {
            warning('Таблицы не найдены в БД.');

            return null;
        }
        $options = [];
        foreach ($tables as $t) {
            $options[$t] = $t;
        }
        $picked = select(
            label: 'Выберите таблицу',
            options: $options,
            scroll: 15,
        );

        $analysis = $schema->analyzeTable($picked);
        $analysis['relations'] = [];

        return $analysis;
    }

    /**
     * Если таблица без модели — генерируем простую stub-модель.
     *
     * @param  array<string, mixed>  $analysis
     */
    private function ensureModel(string $table, array $analysis, ResourceWriter $writer, Filesystem $files): string
    {
        $modelClass = 'App\\Models\\'.Str::studly(Str::singular($table));
        if (class_exists($modelClass)) {
            return $modelClass;
        }

        $shortName = class_basename($modelClass);
        $path = base_path('app/Models/'.$shortName.'.php');

        $fillable = collect($analysis['columns'] ?? [])
            ->reject(fn (array $c): bool => in_array($c['name'], ['id', 'created_at', 'updated_at', 'deleted_at'], true))
            ->pluck('name')
            ->map(fn (string $n): string => "'{$n}'")
            ->implode(', ');

        $contents = "<?php\n\ndeclare(strict_types=1);\n\n"
            ."namespace App\\Models;\n\n"
            ."use Illuminate\\Database\\Eloquent\\Model;\n\n"
            ."class {$shortName} extends Model\n{\n"
            ."    protected \$table = '{$table}';\n"
            ."    protected \$fillable = [{$fillable}];\n"
            ."}\n";

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $contents);

        return $modelClass;
    }

    /**
     * @param  list<array{name: string, type: string, nullable: bool, default: mixed, comment: ?string, is_primary: bool, is_unique: bool, is_indexed: bool, enum_values: ?list<string>}>  $columns
     * @param  list<array{name: string, type: string, related: ?class-string, foreign_key: ?string}>  $relations
     * @param  list<string>  $selected
     */
    private function buildFieldsBlock(array $columns, array $relations, FieldTypeInferrer $inferrer, array $selected): string
    {
        $lines = [];
        foreach ($columns as $col) {
            if (! in_array($col['name'], $selected, true)) {
                continue;
            }
            $code = $inferrer->inferFieldCode($col, $relations);
            if ($code === null) {
                continue;
            }
            $lines[] = '            '.$code.',';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<string>  $selected
     */
    private function buildColumnsBlock(array $columns, FieldTypeInferrer $inferrer, array $selected): string
    {
        $lines = [];
        foreach ($columns as $col) {
            if (! in_array($col['name'], $selected, true)) {
                continue;
            }
            $code = $inferrer->inferColumnCode($col);
            if ($code === null) {
                continue;
            }
            $lines[] = '            '.$code.',';
        }
        // Built-in row-actions column
        $lines[] = "            TableColumn::make('actions')->view(),";

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     */
    private function buildFiltersBlock(array $columns, FieldTypeInferrer $inferrer): string
    {
        $lines = [];
        foreach ($columns as $col) {
            $code = $inferrer->inferFilterCode($col);
            if ($code === null) {
                continue;
            }
            $lines[] = '            '.$code.',';
        }
        if ($lines === []) {
            return "            // BaseInputFilter::make('search')->searchableFields([...]),";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     */
    private function pickSearchable(array $columns): string
    {
        $candidates = [];
        foreach ($columns as $col) {
            $type = strtolower($col['type']);
            if (in_array($col['name'], ['name', 'title', 'email', 'slug', 'description'], true)
                || in_array($type, ['varchar', 'string', 'text'], true)
            ) {
                $candidates[] = "'".$col['name']."'";
            }
            if (count($candidates) >= 4) {
                break;
            }
        }

        return implode(', ', $candidates);
    }

    /**
     * Собирает use-statement'ы для использованных Field/Filter-классов.
     *
     * @param  list<array<string, mixed>>  $columns
     * @param  list<array<string, mixed>>  $relations
     */
    private function buildExtraImports(array $columns, array $relations): string
    {
        $needed = [];

        foreach ($columns as $col) {
            $type = strtolower($col['type']);
            $name = strtolower($col['name']);

            // По name-pattern'ам:
            if (in_array($name, ['avatar', 'image', 'photo', 'cover', 'logo'], true)
                || str_ends_with($name, '_image') || str_ends_with($name, '_file')
                || in_array($name, ['file', 'attachment', 'document'], true)) {
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\FileUpload';
            }
            if ($name === 'slug') {
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\Slug';
            }
            if (in_array($name, ['body', 'content', 'html'], true)) {
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\Wysiwyg';
            }

            // По типам:
            if (in_array($type, ['boolean', 'bool', 'tinyint(1)'], true)) {
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\Switcher';
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Filter\\BaseSwitcherFilter';
            }
            if (in_array($type, ['date', 'datetime', 'timestamp'], true)) {
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\DatePicker';
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Filter\\BaseDateFilter';
            }
            if (in_array($type, ['time'], true)) {
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\TimePicker';
            }
            if (in_array($type, ['integer', 'int', 'bigint', 'smallint', 'mediumint',
                'decimal', 'float', 'double', 'numeric'], true)) {
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\Number';
            }
            if (in_array($type, ['text', 'mediumtext', 'longtext', 'tinytext'], true)) {
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\Textarea';
            }
            if (in_array($type, ['json', 'jsonb'], true)) {
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\KeyValue';
            }
            // varchar / unknown → Input
            $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\Input';

            if (! empty($col['enum_values'])) {
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\Select';
                $needed[] = 'Dskripchenko\\LaravelAdmin\\Filter\\BaseSelectFromOptionsFilter';
            }
        }

        if ($relations !== []) {
            foreach ($relations as $rel) {
                if ($rel['type'] === 'BelongsTo') {
                    $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\RelationSelect';
                    break;
                }
            }
        }

        // Hidden — для skipped columns
        $needed[] = 'Dskripchenko\\LaravelAdmin\\Field\\Hidden';

        $needed = array_values(array_unique($needed));
        sort($needed);

        return implode("\n", array_map(static fn (string $cls): string => "use {$cls};", $needed));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function createRole(string $name, array $permissions): void
    {
        if (! class_exists(Role::class)) {
            return;
        }

        Role::query()->updateOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'permissions' => $permissions],
        );
    }

    private function guessIcon(string $label): string
    {
        $lower = strtolower($label);
        if (str_contains($lower, 'user') || str_contains($lower, 'people')) {
            return 'users';
        }
        if (str_contains($lower, 'role')) {
            return 'shield';
        }
        if (str_contains($lower, 'setting')) {
            return 'settings';
        }
        if (str_contains($lower, 'article') || str_contains($lower, 'post') || str_contains($lower, 'news')) {
            return 'file-text';
        }
        if (str_contains($lower, 'product')) {
            return 'package';
        }
        if (str_contains($lower, 'order')) {
            return 'shopping-cart';
        }
        if (str_contains($lower, 'tag') || str_contains($lower, 'category')) {
            return 'tag';
        }
        if (str_contains($lower, 'image') || str_contains($lower, 'media') || str_contains($lower, 'gallery')) {
            return 'image';
        }

        return 'box';
    }
}
