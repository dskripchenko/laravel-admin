<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Field\ValidationRulesExporter;
use Dskripchenko\LaravelAdmin\Filter\Filter;
use Dskripchenko\LaravelAdmin\Infolist\Entry;
use Dskripchenko\LaravelAdmin\Infolist\IconEntry;
use Dskripchenko\LaravelAdmin\Infolist\TextEntry;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The abstract Resource — the entry point of the CRUD builder.
 *
 * One Resource describes a list (columns + filters), a form (fields),
 * permissions and actions. Under the hood ResourceCompiler expands it into a
 * laravel-api controller with slug = `static::slug()`.
 *
 * Subclasses declare:
 *
 *     final class UserResource extends Resource
 *     {
 *         public static string $model = \App\Models\User::class;
 *
 *         public function fields(): array { return [
 *             Field\Input::make('name')->required(),
 *             Field\Input::make('email')->type('email')->required(),
 *         ]; }
 *
 *         public function columns(): array { return [
 *             TableColumn::make('id')->sort(),
 *             TableColumn::make('name')->sort()->search(),
 *         ]; }
 *     }
 */
abstract class Resource
{
    /**
     * FQCN of the Eloquent model. A subclass must override it.
     *
     * @var class-string<Model>
     */
    public static string $model;

    public static string $icon = 'cube';

    public static ?string $group = null;

    /**
     * Slug — the basename in kebab-case, without the 'Resource' suffix.
     */
    public static function slug(): string
    {
        $base = class_basename(static::class);
        if (str_ends_with($base, 'Resource')) {
            $base = substr($base, 0, -strlen('Resource'));
        }

        return Str::kebab(Str::pluralStudly($base));
    }

    /**
     * The base permission key, `admin.{slug}` by default. Individual actions
     * derive from it: `<base>.view`, `.create`, `.update`, `.delete`, …
     */
    public static function permission(): string
    {
        return 'admin.'.static::slug();
    }

    /**
     * Human-readable label of the resource — for the menu and the manifest.
     */
    public static function label(): string
    {
        $base = class_basename(static::class);
        if (str_ends_with($base, 'Resource')) {
            $base = substr($base, 0, -strlen('Resource'));
        }

        return Str::headline(Str::pluralStudly($base));
    }

    /* -----------------------------------------------------------------
     * Declaration (for subclasses)
     * ----------------------------------------------------------------- */

    /**
     * Form fields.
     *
     * @return list<Field>
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * Columns of the list table.
     *
     * @return list<TableColumn>
     */
    public function columns(): array
    {
        return [];
    }

    /**
     * Filters.
     *
     * @return list<Filter>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Actions — command bar, per row and bulk.
     *
     * @return list<Action>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * Fields the `?q=` search runs over.
     *
     * @return list<string>
     */
    public function searchableFields(): array
    {
        $searchable = [];
        foreach ($this->columns() as $column) {
            if ($column->isSearchable()) {
                $searchable[] = $column->name();
            }
        }

        return $searchable;
    }

    /**
     * Serialises a record for the SPA (the record/state of read/create/update
     * responses). A host overrides this for virtual form fields — to unpack a
     * JSON column into flat fields, say: the other side of fillModel.
     *
     * @return array<string, mixed>
     */
    public function transformRecord(Model $record): array
    {
        return $record->toArray();
    }

    /**
     * The record's title for global search and quick links. By default the
     * first non-empty of name/title/label/email/slug, otherwise the first
     * searchable field, otherwise `#{id}`. A host overrides it for its own
     * presentation.
     */
    public function recordTitle(Model $row): string
    {
        foreach (['name', 'title', 'label', 'email', 'slug'] as $attr) {
            $value = $row->getAttribute($attr);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        foreach ($this->searchableFields() as $field) {
            $value = $row->getAttribute($field);
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '#'.$row->getKey();
    }

    /**
     * The record's secondary line in search results — email/slug/status and
     * the like. Returns null when no suitable attribute exists, or when the
     * one found merely repeats the title.
     */
    public function recordSubtitle(Model $row): ?string
    {
        $title = $this->recordTitle($row);
        foreach (['email', 'slug', 'status', 'code'] as $attr) {
            $value = $row->getAttribute($attr);
            if (is_string($value) && $value !== '' && $value !== $title) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Allowed relations for eager loading via `?with[]=…` and `Resource::with()`.
     *
     * @return list<string>
     */
    public function with(): array
    {
        return [];
    }

    /**
     * Per-row override for inline editing. Returns false when this particular
     * cell of this particular row must not be edited — "your own email yes,
     * somebody else's no". The default is true, which is the same as a
     * column-wide `editable()` with no further rules.
     *
     * Called by ResourceController::search() for every editable column.
     */
    public function editableForRow(Model $row, string $column): bool
    {
        return true;
    }

    /**
     * The base query of the list screen. Without filters — those are applied
     * by ResourceCompiler.
     */
    public function indexQuery(): Builder
    {
        return $this->modelQuery();
    }

    /**
     * The base query for read/update/delete.
     */
    public function modelQuery(): Builder
    {
        if (! isset(static::$model) || ! is_subclass_of(static::$model, Model::class)) {
            throw new RuntimeException(
                static::class.'::$model must be set to an Eloquent model FQCN',
            );
        }

        /** @var Model $instance */
        $instance = new static::$model;

        return $instance->newQuery();
    }

    /**
     * Validation rules for the create/update context.
     *
     * Takes the explicit `Field::rules()` declarations and adds the implicit
     * type-specific ones (numeric/email/file/array/…) through
     * ValidationRulesExporter.
     *
     * @return array<string, list<string>>
     */
    public function validationRules(string $context = 'create'): array
    {
        return ValidationRulesExporter::export($this->fields(), $context);
    }

    /**
     * Hook for mapping validated form data onto the model.
     *
     * By default it forceFills whatever arrived. Override it in resources with
     * derived fields — where a JSON `config` block is assembled from flat
     * `config_*` inputs, for instance; see StorageDiskResource.
     *
     * @param  array<string, mixed>  $data
     */
    public function fillModel(Model $model, array $data): void
    {
        $model->forceFill($data);
    }

    /**
     * A custom form structure for the create/update context.
     *
     * Returns a list of Renderable (Field or Layout) that replaces the default
     * flat Rows layout in Generated*Screen. An empty array means the default
     * is used (Rows built by filterFieldsBy).
     *
     * Every Field mentioned in this tree must be the SAME instance as in
     * `fields()` — otherwise validation and persistence will not see it.
     *
     * @return list<\Dskripchenko\LaravelAdmin\Contracts\Renderable>
     */
    public function formLayout(string $context): array
    {
        return [];
    }

    /**
     * Serialises the form fields for the manifest. When `formLayout('update')`
     * returns a Renderable tree it is serialised as is (Tabs/Rows/…);
     * otherwise a flat list of fields is produced.
     *
     * @return list<array<string, mixed>>
     */
    private function serializeFormFields(string $context = 'update'): array
    {
        $layout = $this->formLayout($context);
        if ($layout !== []) {
            return array_map(
                static fn (\Dskripchenko\LaravelAdmin\Contracts\Renderable $r): array => $r->toArray(),
                $layout,
            );
        }

        return array_map(static fn (Field $f): array => $f->toArray(), $this->fields());
    }

    /**
     * The create layout — only when it DIFFERS from the edit one.
     *
     * `formLayout()` has taken a context from the start, but the core always
     * called it with `'update'`: the parameter was declared and meant nothing,
     * and the create form got tabs with nothing to show until the record
     * exists — empty "API", "Assistant", "Preview".
     *
     * The key is omitted when the two layouts match: the manifest travels with
     * every bootstrap, and a second copy of the field tree for resources that
     * do not need it is weight on every panel load.
     *
     * @return list<array<string, mixed>>|null
     */
    private function serializeCreateFormFields(): ?array
    {
        $create = $this->serializeFormFields('create');

        // The trees are compared WITHOUT `id`: it is generated per layout
        // instance, so two serialisations of the same tree are never equal
        // literally. The first version compared them as they came and sent a
        // second copy to every resource alike.
        return self::withoutIds($create) === self::withoutIds($this->serializeFormFields('update'))
            ? null
            : $create;
    }

    /**
     * @param  array<mixed>  $tree
     * @return array<mixed>
     */
    private static function withoutIds(array $tree): array
    {
        $out = [];
        foreach ($tree as $key => $value) {
            if ($key === 'id') {
                continue;
            }
            $out[$key] = is_array($value) ? self::withoutIds($value) : $value;
        }

        return $out;
    }

    /* -----------------------------------------------------------------
     * Serialisation for the manifest
     * ----------------------------------------------------------------- */

    /**
     * Metadata for the manifest and for the resource.meta action.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $base = static::permission();

        return [
            'slug' => static::slug(),
            'label' => \Dskripchenko\LaravelAdmin\I18n\Localize::string(static::label()),
            'icon' => static::$icon,
            'group' => \Dskripchenko\LaravelAdmin\I18n\Localize::string(static::$group),
            // The model's Eloquent morph class — the frontend needs it for
            // AuditTimeline (`subject_type` in the /audit/timeline endpoint).
            // When the class is registered in the morphMap the alias is sent,
            // otherwise the FQCN.
            'subject_type' => isset(static::$model)
                ? (array_search(
                    static::$model,
                    \Illuminate\Database\Eloquent\Relations\Relation::morphMap(),
                    true,
                ) ?: static::$model)
                : null,
            'permissions' => [
                'view' => $base.'.view',
                'create' => $base.'.create',
                'update' => $base.'.update',
                'delete' => $base.'.delete',
                'restore' => $base.'.restore',
                'force_delete' => $base.'.force-delete',
                'replicate' => $base.'.replicate',
                'reorder' => $base.'.reorder',
            ],
            'fields' => $this->serializeFormFields(),
            // Absent when create and edit look the same.
            'create_fields' => $this->serializeCreateFormFields(),
            'columns' => array_map(static fn (TableColumn $c): array => $c->toArray(), $this->columns()),
            // infolist: used by ResourceViewPage for the read-only display.
            // Default — a TextEntry per field, see Resource::infolist.
            'infolist' => array_map(static fn (Entry $e): array => $e->toArray(), $this->infolist()),
            'filters' => $this->compiledFilters(),
            'actions' => array_map(static fn (Action $a): array => $a->toArray(), $this->actions()),
            'searchable' => $this->searchableFields(),
            'with' => $this->with(),
            'view_mode' => $this->viewMode(),
            'hierarchy_parent_key' => $this->hierarchyParentKey(),
            'parent_slug' => $this->parentSlug(),
            'features' => [
                'softDeletes' => static::supportsSoftDeletes(),
                'replicable' => $this->replicable(),
                'reorderable' => $this->reorderable(),
                'reorderColumn' => $this->reorderable() ? $this->reorderColumn() : null,
                'importable' => $this->importable(),
                'exportable' => $this->exportable(),
                'savedViews' => $this->savedViews(),
                'polling' => $this->polling(),
                'warnOnUnsavedChanges' => true,
                'creatable' => $this->fields() !== [],
                'editable' => $this->fields() !== [],
            ],
        ];
    }

    /**
     * Serialised filters, with TrashedFilter added automatically for
     * SoftDeletes models. A host may declare `TrashedFilter::for(...)` in
     * `filters()` explicitly — then the auto-injection does not duplicate it.
     *
     * @return list<array<string, mixed>>
     */
    private function compiledFilters(): array
    {
        return array_map(static fn (Filter $f): array => $f->toArray(), $this->resolvedFilters());
    }

    /**
     * The actual set of filter OBJECTS: declared ones plus the auto-injected
     * TrashedFilter for SoftDeletes. One source both for the manifest
     * (compiledFilters) and for applying them in search — otherwise the
     * auto-trashed filter was shown in the UI and never applied, because
     * search went through `filters()`, which does not contain it.
     *
     * @return list<Filter>
     */
    public function resolvedFilters(): array
    {
        $declared = $this->filters();
        $hasTrashed = false;
        foreach ($declared as $f) {
            if ($f instanceof \Dskripchenko\LaravelAdmin\Filter\TrashedFilter) {
                $hasTrashed = true;
                break;
            }
        }
        if (! $hasTrashed && static::supportsSoftDeletes()) {
            $declared[] = \Dskripchenko\LaravelAdmin\Filter\TrashedFilter::for('trashed')
                ->label('Удалённые');
        }

        return $declared;
    }

    /**
     * Whether the model supports Eloquent SoftDeletes — detected via trait_uses.
     */
    public static function supportsSoftDeletes(): bool
    {
        if (! isset(static::$model)) {
            return false;
        }

        return in_array(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive(static::$model),
            true,
        );
    }

    /**
     * Whether a record may be cloned through ResourceController.replicate.
     */
    public function replicable(): bool
    {
        return false;
    }

    /**
     * Default ordering applied by the index endpoint when the request
     * carries no explicit `order[]`. Returns a list of {column, direction}
     * tuples; multiple entries become chained orderBy calls.
     *
     * Default: newest-first by the model's primary key (works on every
     * table and matches the typical "last touched at the top" expectation
     * of admin lists). Resources that need positional / chronological /
     * custom defaults can override.
     *
     * Reorderable resources still use their reorder column ASC — that
     * branch is special-cased in ResourceController.
     *
     * @return list<array{column: string, direction: 'asc'|'desc'}>
     */
    public function defaultOrder(): array
    {
        $key = (new (static::$model))->getKeyName();

        return [['column' => $key, 'direction' => 'desc']];
    }

    /**
     * Whether records may be reordered by dragging.
     */
    public function reorderable(): bool
    {
        return false;
    }

    /**
     * Whether saved list views are available — named sets of filters, sorting
     * and visible columns.
     *
     * Off by default: the feature adds four routes per resource, and it only
     * makes sense on long lists that people actually filter. Those routes used
     * to appear for every resource alike — including the ones with nothing
     * worth saving.
     */
    public function savedViews(): bool
    {
        return false;
    }

    /**
     * Whether data may be imported through the four-step import wizard.
     */
    public function importable(): bool
    {
        return false;
    }

    /**
     * Export formats of the list; empty hides the export entirely. CSV by
     * default — read-only resources such as the audit log may return [] to
     * drop the button.
     *
     * @return list<string>
     */
    public function exportable(): array
    {
        return ['csv'];
    }

    /**
     * Name of the column that holds the order, `position` by default.
     */
    public function reorderColumn(): string
    {
        return 'position';
    }

    /**
     * The self-referencing FK column of a hierarchical resource; `parent_id`
     * by default, auto-detected from the Eloquent relations. null means the
     * resource is flat.
     *
     * Override it in a subclass to force tree mode (`return 'parent_id'`) or
     * to switch it off even though the relations are there (`return null`).
     */
    public function hierarchyParentKey(): ?string
    {
        return self::detectHierarchyParentKey(static::$model ?? null);
    }

    /**
     * `'tree'` for a hierarchical resource, otherwise `'list'`. Decides which
     * Generated*Screen ResourceController compiles for the `/r/{slug}` route.
     */
    public function viewMode(): string
    {
        return $this->hierarchyParentKey() !== null ? 'tree' : 'list';
    }

    /**
     * Slug of the resource whose index serves as the "parent" context — where
     * the Back button of a form or view page returns to. Null by default: back
     * leads to the resource's own index. Used when a resource is shown as a
     * leaf of another tree: TemplateResource lives under GroupResource in the
     * tree, and Back must return to the tree of groups.
     */
    public function parentSlug(): ?string
    {
        return null;
    }

    /**
     * Context actions attached to every node of the tree view. Returns a list
     * of descriptors; the frontend (ResourceTreePage) renders them in the
     * toolbar of the selected node. Empty by default.
     *
     * Each descriptor:
     *   - `id` (string)            — unique within the node
     *   - `label` (string)         — the text shown
     *   - `icon` (?string)         — lucide icon name, kebab-case
     *   - `variant` (?string)      — primary|secondary|ghost (secondary by default)
     *   - `kind` ('navigate')      — the only kind supported so far
     *   - `to` (array)             — `{ slug, screen, params }`; params become
     *                                the query string of the transition, and
     *                                the `{id}` placeholder is replaced with
     *                                the current record's id.
     *
     * @return list<array<string, mixed>>
     */
    public function treeNodeActions(Model $row): array
    {
        return [];
    }

    /**
     * Pre-tree hook: extra ids of the main model to mix into the tree-view
     * selection. Used when a tree search has to account for leaf nodes coming
     * from another resource — GroupResource, searching, returns the ids of the
     * groups that hold matching templates (together with their ancestors), so
     * that a template leaf appears under its own group.
     *
     * Empty by default.
     *
     * @return list<int|string>
     */
    public function treeAdditionalRowIds(?string $searchTerm): array
    {
        return [];
    }

    /**
     * Extra leaf nodes for the tree view, attached to the nodes of the main
     * resource by parent_id. Used to show records of another resource inside
     * the current tree — templates under their group in the tree of groups.
     *
     * `$searchTerm`, when not null, is the current tree search; the leaves
     * should be filtered by it so that the search finds nested records.
     *
     * Each leaf is an array with the same keys as a node (`key`, `label`,
     * `record`) plus, optionally:
     *   - `slug` — slug of the other resource, for cross-navigation
     *     (ResourceTreePage will link to `/admin/r/{slug}/{id}/edit`).
     *   - `kind` — a free-form marker for frontend logic.
     *
     * Empty by default: no extra leaves.
     *
     * @param  list<Model>  $rows  Records of the main resource that made it into the tree
     * @param  ?string  $searchTerm  The current search term, null when there is none
     * @return array<int|string, list<array<string, mixed>>> parent_id → leaves
     */
    public function treeExtraLeaves(array $rows, ?string $searchTerm = null): array
    {
        return [];
    }

    /**
     * @var array<class-string<Model>, string|null>
     */
    private static array $hierarchyDetectCache = [];

    /**
     * Auto-detects the self-referencing FK by convention:
     *  - `parent()` returns a BelongsTo to the same model → take its foreignKey
     *  - `children()` returns a HasMany to the same model → take its foreignKey
     *
     * @param  class-string<Model>|null  $model
     */
    private static function detectHierarchyParentKey(?string $model): ?string
    {
        if ($model === null || ! is_subclass_of($model, Model::class)) {
            return null;
        }

        if (array_key_exists($model, self::$hierarchyDetectCache)) {
            return self::$hierarchyDetectCache[$model];
        }

        $key = null;
        $instance = new $model;

        if (method_exists($instance, 'parent')) {
            try {
                $rel = $instance->parent();
                if ($rel instanceof BelongsTo && $rel->getRelated()::class === $model) {
                    $key = $rel->getForeignKeyName();
                }
            } catch (\Throwable) {
                // A parent() with a different signature — ignored.
            }
        }

        if ($key === null && method_exists($instance, 'children')) {
            try {
                $rel = $instance->children();
                if ($rel instanceof HasMany && $rel->getRelated()::class === $model) {
                    $key = $rel->getForeignKeyName();
                }
            } catch (\Throwable) {
                // children() is not an Eloquent relation — ignored.
            }
        }

        return self::$hierarchyDetectCache[$model] = $key;
    }

    /**
     * Hook for controlling which fields are copied on replicate.
     *
     * By default Eloquent's `Model::replicate()` — every attribute except the
     * primary key and the timestamps. Override it in a subclass to regenerate
     * unique fields: slug + ' (copy)', a fresh uuid and so on.
     */
    public function replicate(Model $original): Model
    {
        $copy = $original->replicate();

        // When a title/name exists, append a '(copy)' suffix to avoid
        // breaking unique indexes at the demo level. Subclasses decorate this
        // hook for their own fields.
        foreach (['name', 'title', 'slug'] as $col) {
            if ($copy->getAttribute($col) !== null) {
                $copy->setAttribute($col, $copy->getAttribute($col).' (копия)');
            }
        }

        return $copy;
    }

    /**
     * Auto-refresh interval of the list table, in seconds; null means never.
     * With 30, the table re-fetches its data every 30 seconds.
     */
    public function polling(): ?int
    {
        return null;
    }

    /**
     * Read-only entries for GeneratedViewScreen.
     *
     * By default a TextEntry per field from `fields()` with the same label,
     * except `switch` fields: those render as an IconEntry with a localised
     * Yes/No, so that the view page does not show «true»/«false» for boolean
     * flags. Override it in a subclass when you need something else — a
     * BadgeEntry for statuses, an ImageEntry for avatars and so on.
     *
     * @return list<Entry>
     */
    public function infolist(): array
    {
        $entries = [];
        foreach ($this->fields() as $field) {
            $name = $field->name();
            $label = (string) ($field->getAttributes()['title'] ?? $name);
            $entries[] = match ($field->fieldType()) {
                'switch' => IconEntry::make($name)
                    ->label($label)
                    ->trueLabel((string) __('admin.common.yes'))
                    ->falseLabel((string) __('admin.common.no'))
                    ->trueIcon('check-circle-2')
                    ->falseIcon('x-circle'),
                default => TextEntry::make($name)->label($label),
            };
        }

        return $entries;
    }
}
