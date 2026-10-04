<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Dskripchenko\LaravelAdmin\Console\Support\AdminPluginUpdater;
use Dskripchenko\LaravelAdmin\Console\Support\FieldTypeInferrer;
use Dskripchenko\LaravelAdmin\Console\Support\PluginRegistrationReport;
use Dskripchenko\LaravelAdmin\Console\Support\ResourceGenerator;
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
 * The main wizard for adding a new section to the admin panel. It is started
 * without arguments — everything is entered interactively through Laravel
 * Prompts.
 *
 * The steps:
 *   1. The section's label and its singular form
 *   2. The source: an Eloquent model (auto-discovered) OR a database table
 *   3. Analysis of the schema and the relations
 *   4. Picking the form fields (multiselect)
 *   5. Picking the table columns
 *   6. The permission base
 *   7. The icon
 *   8. The menu — as a root item or under an existing parent
 *   9. Optionally, a role carrying these permissions
 *
 * What comes out:
 *   - app/Admin/Resources/{Name}Resource.php
 *   - a registration in app/Admin/AdminPlugin.php, created if missing
 *   - an Admin::menu()->add(MenuNode::resource(...)) entry
 *   - optionally a role with the permissions admin.{slug}.{view,create,update,delete}
 */
final class MakeSectionCommand extends Command
{
    protected $signature = 'admin:make-section
                            {--force : Overwrite an existing resource}
                            {--tree : Force tree mode (generates hierarchyParentKey() = parent_id)}
                            {--no-menu : Do not add a menu item}
                            {--no-role : Do not offer to create a role}';

    protected $description = 'Create an admin section from a database table or an Eloquent model (interactive)';

    public function handle(
        SchemaIntrospector $schema,
        ResourceGenerator $generator,
        ResourceWriter $writer,
        AdminPluginUpdater $updater,
        Filesystem $files,
    ): int {
        info('New admin section');
        note('The command inspects the table or model and generates a resource, '
            ."its permissions and a menu item.\n"
            .'Press Ctrl+C at any step to cancel.');

        // === 1. Metadata ===
        $singular = text(
            label: 'Singular label (e.g. Article)',
            placeholder: 'Article',
            required: true,
        );
        $plural = text(
            label: 'Plural label (for the table and the menu)',
            default: Str::plural($singular),
            required: true,
        );

        // === 2. The source ===
        $sourceType = select(
            label: 'Data source',
            options: [
                'model' => 'Eloquent model (auto-discovered)',
                'table' => 'Database table (no model)',
            ],
            default: 'model',
        );

        $analysis = match ($sourceType) {
            'model' => $this->pickModel($schema),
            'table' => $this->pickTable($schema),
            default => null,
        };

        if ($analysis === null) {
            warning('No source selected; cancelled.');

            return self::FAILURE;
        }

        $modelClass = $analysis['model'] ?? null;
        $tableName = $analysis['table'];

        // === 3. Fields and columns ===
        $columns = $analysis['columns'] ?? [];
        $relations = $analysis['relations'] ?? [];

        // The RelationSelect display column is chosen right away through the introspector
        $relations = array_map(function (array $rel) use ($schema): array {
            if ($rel['type'] === 'BelongsTo' && $rel['related'] !== null) {
                $rel['display'] = $schema->pickDisplayColumn($rel['related']);
            }

            return $rel;
        }, $relations);

        // What the model adds to the table: its hidden attributes and casts.
        // Secrets and hidden attributes never reach the form, the list or the
        // search, and are not offered at all.
        $context = [
            'hidden' => array_values($analysis['hidden'] ?? []),
            'casts' => $analysis['casts'] ?? [],
            'columns' => array_map(static fn (array $c): string => $c['name'], $columns),
        ];
        $inferrer = new FieldTypeInferrer;
        $offered = array_values(array_filter(
            $context['columns'],
            static fn (string $n): bool => ! $inferrer->isSecret($n, $context),
        ));
        $skipped = array_values(array_diff($context['columns'], $offered));
        if ($skipped !== []) {
            note('Left out as secret or hidden: '.implode(', ', $skipped)
                .'. Add a Password field by hand if the form must set one.');
        }

        $selectedFormColumns = multiselect(
            label: 'Form fields (create/edit)',
            options: array_combine($offered, $offered),
            default: $generator->defaultFormColumns($columns, $context),
            scroll: 20,
            hint: 'Space — toggle, Enter — confirm',
        );

        $selectedTableColumns = multiselect(
            label: 'Table columns (list)',
            options: array_combine($offered, $offered),
            default: $generator->defaultTableColumns($columns, $context),
            scroll: 20,
        );

        // === 4. Permissions ===
        $slug = $writer->resourceSlugFor($singular);
        $permission = text(
            label: 'Base permission (derived: .view/.create/.update/.delete)',
            default: 'admin.'.$slug,
            required: true,
        );

        // === 5. Icon ===
        $icon = text(
            label: 'Lucide icon name (see lucide.dev)',
            default: $this->guessIcon($plural),
        );

        // === 6. Group (optional) ===
        $group = text(
            label: 'Sidebar group (empty for none)',
            default: '',
        );

        // === 7. The menu ===
        $addMenu = ! $this->option('no-menu') && confirm(label: 'Add to the menu?', default: true);
        $menuParent = '';
        if ($addMenu) {
            $menuParent = text(
                label: 'Parent menu key (empty for a top-level item)',
                default: '',
                hint: 'For example: shop, content, tools',
            );
        }

        // === 8. Role ===
        $createRole = ! $this->option('no-role') && confirm(label: 'Create a role with these permissions?', default: false);
        $roleName = '';
        $rolePerms = [];
        if ($createRole) {
            $roleName = text(label: 'Role name', default: $plural.' editor', required: true);
            $rolePerms = multiselect(
                label: 'Permissions of the role',
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
        info('Generating...');

        $namespace = 'App\\Admin\\Resources';
        $className = $writer->classNameFor($singular, 'Resource');

        // A table with no model: generate the model first
        if ($modelClass === null) {
            $modelClass = $this->ensureModel($tableName, $analysis, $writer, $files);
        }

        // Hierarchy autodetection: a BelongsTo pointing at the same model, a
        // parent_id self-reference. --tree forces the tree mode on even when
        // the detection missed.
        $hierarchyKey = $this->detectHierarchyKey($relations, $modelClass);
        $treeMode = $hierarchyKey !== null || (bool) $this->option('tree');
        if ($treeMode && $hierarchyKey === null) {
            $hierarchyKey = 'parent_id';
        }
        if ($treeMode) {
            info("Tree mode: hierarchy through the `{$hierarchyKey}` self-reference.");
        }

        $source = $generator->render([
            'namespace' => $namespace,
            'class' => $className,
            'model' => $modelClass,
            'slug' => $slug,
            'label' => $plural,
            'singularLabel' => $singular,
            'permission' => $permission,
            'icon' => $icon,
            'group' => $group !== '' ? $group : null,
            'columns' => $columns,
            'relations' => $relations,
            'context' => $context,
            'formColumns' => $selectedFormColumns,
            'tableColumns' => $selectedTableColumns,
            'hierarchyKey' => $treeMode ? $hierarchyKey : null,
            'stub' => $writer->stubPath('resource.stub'),
        ]);

        $target = $writer->classPath($namespace, $className);
        $created = $writer->write($target, $source, force: (bool) $this->option('force'));

        if (! $created) {
            warning("File already exists: {$target}. Use --force to overwrite it.");

            return self::FAILURE;
        }
        info("Created: {$target}");

        // Registration in the plugin
        $resourceFqcn = $namespace.'\\'.$className;
        $reg = $updater->registerResource($resourceFqcn);
        info("Plugin: {$reg['path']} ({$reg['action']})");

        // The menu
        if ($addMenu) {
            $updater->ensureImport($reg['path'], 'Dskripchenko\\LaravelAdmin\\Menu\\MenuNode');
            $menu = $updater->addMenuNode('resource', $slug, $menuParent ?: null);
            info("Menu: {$menu['action']}");
        }

        // Role
        if ($createRole && ! empty($rolePerms)) {
            $this->createRole($roleName, $rolePerms);
            info("Role \"{$roleName}\" created with ".count($rolePerms).' permissions');
        }

        $plugin = $updater->ensurePluginRegistered($reg['path']);
        PluginRegistrationReport::print($this, $plugin);

        info('Done.');
        $this->newLine();
        note('Next steps:');
        $this->line('  1. Open '.$target.' and tidy up the fields, columns and filters');
        $this->line('  2. Open /'.trim((string) config('admin.path', 'admin'), '/')."/r/{$slug}");
        $this->line('  The panel reads the resource from the manifest: no frontend rebuild is needed.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pickModel(SchemaIntrospector $schema): ?array
    {
        $models = $schema->discoverModels();
        if ($models === []) {
            warning('No Eloquent models found in app/Models/.');

            return null;
        }
        $options = [];
        foreach ($models as $cls) {
            $options[$cls] = $cls;
        }
        $picked = select(
            label: 'Choose a model',
            options: $options,
            scroll: 15,
        );

        $analysis = $schema->analyzeModel($picked);
        // Merge the table's data, which carries the types, with the model's relations
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
            warning('No tables found in the database.');

            return null;
        }
        $options = [];
        foreach ($tables as $t) {
            $options[$t] = $t;
        }
        $picked = select(
            label: 'Choose a table',
            options: $options,
            scroll: 15,
        );

        $analysis = $schema->analyzeTable($picked);
        $analysis['relations'] = [];
        // The model generated for the table casts its columns, so that the
        // fields round-trip: a JSON column as an array, a boolean as a bool.
        $analysis['casts'] = (new FieldTypeInferrer)->modelCasts($analysis['columns']);

        return $analysis;
    }

    /**
     * When a table has no model, generate a simple stub model for it.
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

        $inferrer = new FieldTypeInferrer;
        $hidden = collect($analysis['columns'] ?? [])
            ->pluck('name')
            ->filter(fn (string $n): bool => $inferrer->isSecret($n))
            ->map(fn (string $n): string => "'{$n}'")
            ->implode(', ');

        $casts = collect($analysis['casts'] ?? [])
            ->map(fn (string $cast, string $column): string => "        '{$column}' => '{$cast}',")
            ->implode("\n");

        $contents = "<?php\n\ndeclare(strict_types=1);\n\n"
            ."namespace App\\Models;\n\n"
            ."use Illuminate\\Database\\Eloquent\\Model;\n\n"
            ."class {$shortName} extends Model\n{\n"
            ."    protected \$table = '{$table}';\n\n"
            ."    protected \$fillable = [{$fillable}];\n"
            .($hidden !== '' ? "\n    protected \$hidden = [{$hidden}];\n" : '')
            .($casts !== '' ? "\n    protected \$casts = [\n{$casts}\n    ];\n" : '')
            ."}\n";

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $contents);

        return $modelClass;
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

    /**
     * Looks for a BelongsTo relation pointing at the same model — a
     * self-reference, and so a hierarchy. Returns the foreign-key column's
     * name (`parent_id` and the like) or null.
     *
     * @param  list<array{name: string, type: string, related: ?class-string, foreign_key: ?string}>  $relations
     */
    private function detectHierarchyKey(array $relations, ?string $modelClass): ?string
    {
        if ($modelClass === null) {
            return null;
        }
        foreach ($relations as $rel) {
            if ($rel['type'] === 'BelongsTo' && $rel['related'] === $modelClass && ! empty($rel['foreign_key'])) {
                return (string) $rel['foreign_key'];
            }
        }

        return null;
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
