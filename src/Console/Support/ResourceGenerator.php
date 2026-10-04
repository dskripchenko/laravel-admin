<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console\Support;

use Illuminate\Filesystem\Filesystem;

/**
 * Renders the source of a resource class from a table's analysis — the
 * non-interactive half of `admin:make-section`.
 *
 * The wizard collects the answers and hands them over as a spec; the result
 * is the PHP source, ready to be written. Kept apart from the command so that
 * the generated code can be loaded and served in a test, which is what
 * GeneratedResourceContractTest does for a set of typical schemas.
 *
 * The `use` statements are derived from the code actually emitted, so a class
 * is imported exactly when it is used.
 *
 * @phpstan-import-type Column from FieldTypeInferrer
 * @phpstan-import-type Relation from FieldTypeInferrer
 * @phpstan-import-type Context from FieldTypeInferrer
 */
final class ResourceGenerator
{
    /** Short name → FQCN of every class the inferrer may emit. */
    private const KNOWN_CLASSES = [
        'Code' => \Dskripchenko\LaravelAdmin\Field\Code::class,
        'ColorPicker' => \Dskripchenko\LaravelAdmin\Field\ColorPicker::class,
        'DatePicker' => \Dskripchenko\LaravelAdmin\Field\DatePicker::class,
        'FileUpload' => \Dskripchenko\LaravelAdmin\Field\FileUpload::class,
        'Hidden' => \Dskripchenko\LaravelAdmin\Field\Hidden::class,
        'Input' => \Dskripchenko\LaravelAdmin\Field\Input::class,
        'KeyValue' => \Dskripchenko\LaravelAdmin\Field\KeyValue::class,
        'Number' => \Dskripchenko\LaravelAdmin\Field\Number::class,
        'RelationSelect' => \Dskripchenko\LaravelAdmin\Field\RelationSelect::class,
        'Select' => \Dskripchenko\LaravelAdmin\Field\Select::class,
        'Slug' => \Dskripchenko\LaravelAdmin\Field\Slug::class,
        'Switcher' => \Dskripchenko\LaravelAdmin\Field\Switcher::class,
        'Textarea' => \Dskripchenko\LaravelAdmin\Field\Textarea::class,
        'TimePicker' => \Dskripchenko\LaravelAdmin\Field\TimePicker::class,
        'Wysiwyg' => \Dskripchenko\LaravelAdmin\Field\Wysiwyg::class,
        'DateRangeFilter' => \Dskripchenko\LaravelAdmin\Filter\DateRangeFilter::class,
        'OptionsFilter' => \Dskripchenko\LaravelAdmin\Filter\OptionsFilter::class,
        'SelectFromModelFilter' => \Dskripchenko\LaravelAdmin\Filter\SelectFromModelFilter::class,
        'SwitcherFilter' => \Dskripchenko\LaravelAdmin\Filter\SwitcherFilter::class,
    ];

    public function __construct(
        private readonly FieldTypeInferrer $inferrer,
        private readonly Filesystem $files,
    ) {}

    /**
     * The form columns offered by default: everything but the system and
     * secret columns.
     *
     * @param  list<Column>  $columns
     * @param  Context  $context
     * @return list<string>
     */
    public function defaultFormColumns(array $columns, array $context = []): array
    {
        $names = [];
        foreach ($columns as $col) {
            if ($this->inferrer->inferFieldCode($col, [], $context) !== null) {
                $names[] = $col['name'];
            }
        }

        return $names;
    }

    /**
     * The list columns offered by default.
     *
     * @param  list<Column>  $columns
     * @param  Context  $context
     * @return list<string>
     */
    public function defaultTableColumns(array $columns, array $context = []): array
    {
        $names = [];
        foreach ($columns as $col) {
            if ($this->inferrer->inferColumnCode($col, $context) !== null) {
                $names[] = $col['name'];
            }
        }

        return $names;
    }

    /**
     * Renders the resource class.
     *
     * @param  array{
     *     namespace: string,
     *     class: string,
     *     model: string,
     *     slug: string,
     *     label: string,
     *     singularLabel: string,
     *     permission: string,
     *     icon?: string,
     *     group?: ?string,
     *     columns: list<Column>,
     *     relations?: list<Relation>,
     *     context?: Context,
     *     formColumns?: ?list<string>,
     *     tableColumns?: ?list<string>,
     *     hierarchyKey?: ?string,
     *     stub: string,
     *     date?: string,
     * }  $spec
     */
    public function render(array $spec): string
    {
        $columns = $spec['columns'];
        $relations = $spec['relations'] ?? [];
        $context = $spec['context'] ?? [];
        $context['columns'] ??= array_map(static fn (array $c): string => $c['name'], $columns);

        $formColumns = $spec['formColumns'] ?? $this->defaultFormColumns($columns, $context);
        $tableColumns = $spec['tableColumns'] ?? $this->defaultTableColumns($columns, $context);

        $fields = [];
        $listColumns = [];
        $filters = [];
        foreach ($columns as $col) {
            if (in_array($col['name'], $formColumns, true)) {
                $code = $this->inferrer->inferFieldCode($col, $relations, $context);
                if ($code !== null) {
                    $fields[] = '            '.$code.',';
                }
            }
            if (in_array($col['name'], $tableColumns, true)) {
                $code = $this->inferrer->inferColumnCode($col, $context);
                if ($code !== null) {
                    $listColumns[] = '            '.$code.',';
                }
            }
            $code = $this->inferrer->inferFilterCode($col, $relations, $context);
            if ($code !== null) {
                $filters[] = '            '.$code.',';
            }
        }

        $searchable = array_map(
            fn (string $name): string => $this->q($name),
            $this->inferrer->searchableColumns($columns, $context),
        );

        $hierarchyKey = $spec['hierarchyKey'] ?? null;
        $hierarchyMethod = $hierarchyKey !== null
            ? "\n\n    public function hierarchyParentKey(): ?string\n    {\n        return ".$this->q($hierarchyKey).";\n    }"
            : '';

        $body = implode("\n", [...$fields, ...$listColumns, ...$filters]);

        // A model named like a class the resource uses (Code, Number, Resource…)
        // is imported under an alias.
        $modelFqcn = ltrim($spec['model'], '\\');
        $modelShort = class_basename($modelFqcn);
        $modelImport = $modelFqcn;
        if (isset(self::KNOWN_CLASSES[$modelShort]) || in_array($modelShort, ['Resource', 'TableColumn', $spec['class']], true)) {
            $modelShort .= 'Model';
            $modelImport .= ' as '.$modelShort;
        }

        $vars = [
            'namespace' => $spec['namespace'],
            'class' => $spec['class'],
            'modelClass' => $modelImport,
            'modelShort' => $modelShort,
            'extraImports' => $this->imports($body),
            'imports' => $this->imports($body, [
                $modelImport,
                \Dskripchenko\LaravelAdmin\Resource\Resource::class,
                \Dskripchenko\LaravelAdmin\Table\TableColumn::class,
            ]),
            'icon' => $this->escape($spec['icon'] ?? 'box'),
            'group' => ($spec['group'] ?? null) !== null && $spec['group'] !== '' ? $this->q((string) $spec['group']) : 'null',
            'slug' => $this->escape($spec['slug']),
            'label' => $this->escape($spec['label']),
            'singularLabel' => $this->escape($spec['singularLabel']),
            'permission' => $this->escape($spec['permission']),
            'fields' => implode("\n", $fields),
            'columns' => implode("\n", $listColumns),
            'filters' => implode("\n", $filters),
            'searchable' => implode(', ', $searchable),
            'hierarchyMethod' => $hierarchyMethod,
            'date' => $spec['date'] ?? date('Y-m-d'),
        ];

        $source = $this->files->get($spec['stub']);
        foreach ($vars as $key => $value) {
            $source = str_replace('{{ '.$key.' }}', $value, $source);
        }

        // An empty block leaves a blank line behind; tidy it away.
        return (string) preg_replace("/\[\n\n(\s*)\]/", "[\n$1]", $source);
    }

    /**
     * The use statements for the classes the code calls statically, plus the
     * given ones.
     *
     * @param  list<string>  $always
     */
    private function imports(string $code, array $always = []): string
    {
        preg_match_all('/(?<![\\\\\w])([A-Z]\w*)::/', $code, $m);
        $used = array_unique($m[1]);
        $lines = array_map(static fn (string $fqcn): string => 'use '.$fqcn.';', $always);
        foreach ($used as $short) {
            if (isset(self::KNOWN_CLASSES[$short])) {
                $lines[] = 'use '.self::KNOWN_CLASSES[$short].';';
            }
        }
        $lines = array_values(array_unique($lines));
        sort($lines);

        return implode("\n", $lines);
    }

    /**
     * Escapes a value that goes between single quotes in the stub.
     */
    private function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    private function q(string $value): string
    {
        return "'".$this->escape($value)."'";
    }
}
