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
 * Абстрактный Resource — точка входа в CRUD-конструктор.
 *
 * Один Resource описывает list (columns + filters) + form (fields) +
 * permissions + actions. Под капотом ResourceCompiler разворачивает его в
 * laravel-api controller со slug = `static::slug()`.
 *
 * Подклассы декларируют:
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
     * FQCN Eloquent-модели. Обязательно переопределяется в подклассе.
     *
     * @var class-string<Model>
     */
    public static string $model;

    public static string $icon = 'cube';

    public static ?string $group = null;

    /**
     * Slug — kebab-case от basename без 'Resource' suffix.
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
     * Базовый permission-key. По умолчанию `admin.{slug}`. Конкретные actions
     * наследуют: `<base>.view`, `.create`, `.update`, `.delete`, ...
     */
    public static function permission(): string
    {
        return 'admin.'.static::slug();
    }

    /**
     * Человекочитаемая метка Resource'а (для меню и manifest).
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
     * Декларация (для подклассов)
     * ----------------------------------------------------------------- */

    /**
     * Поля формы.
     *
     * @return list<Field>
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * Колонки list-таблицы.
     *
     * @return list<TableColumn>
     */
    public function columns(): array
    {
        return [];
    }

    /**
     * Фильтры.
     *
     * @return list<Filter>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Action'ы (commandBar, row, bulk).
     *
     * @return list<Action>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * Поля для ?q= search.
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
     * Заголовок записи для глобального поиска / быстрых ссылок. Default —
     * первое непустое из name/title/label/email/slug, иначе первое
     * searchable-поле, иначе `#{id}`. Host переопределяет для кастомного
     * представления.
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
     * Вторичная строка записи в результатах поиска (например email/slug/status).
     * Возвращает null если подходящего атрибута нет либо он дублирует заголовок.
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
     * Whitelist для eager-loading из ?with[]=... и Resource::with().
     *
     * @return list<string>
     */
    public function with(): array
    {
        return [];
    }

    /**
     * Per-row override для inline-edit. Возвращает false если конкретную
     * ячейку конкретной строки нельзя редактировать (например, "свой email
     * можно, чужой нет"). Default — true, что эквивалентно column-wide
     * `editable()` без дополнительных правил.
     *
     * Вызывается ResourceController::search() для каждой editable-колонки.
     */
    public function editableForRow(Model $row, string $column): bool
    {
        return true;
    }

    /**
     * Базовый query для list-экрана (без фильтров — те применяются ResourceCompiler'ом).
     */
    public function indexQuery(): Builder
    {
        return $this->modelQuery();
    }

    /**
     * Базовый query для read/update/delete.
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
     * Validation rules для контекста create/update.
     *
     * Берёт явные `Field::rules()`-декларации и дополняет их type-specific
     * implicit-rules (numeric/email/file/array/...) через ValidationRulesExporter.
     *
     * @return array<string, list<string>>
     */
    public function validationRules(string $context = 'create'): array
    {
        return ValidationRulesExporter::export($this->fields(), $context);
    }

    /**
     * Hook для маппинга валидированных данных формы в модель.
     *
     * Default: forceFill всё что пришло. Override в Resource'ах, которые
     * имеют производные поля (например JSON-блок `config` собирается из
     * плоских `config_*` инпутов; см. StorageDiskResource).
     *
     * @param  array<string, mixed>  $data
     */
    public function fillModel(Model $model, array $data): void
    {
        $model->forceFill($data);
    }

    /**
     * Кастомная структура формы для контекста create/update.
     *
     * Возвращает список Renderable (Field или Layout), который заменит
     * дефолтный flat-Rows layout в Generated*Screen. Если возвращает
     * пустой массив — используется дефолт (Rows из filterFieldsBy).
     *
     * Все Field-объекты, упомянутые в этом дереве, должны быть теми же
     * instance'ами, что и в `fields()` — иначе validation/persistence
     * их не увидит.
     *
     * @return list<\Dskripchenko\LaravelAdmin\Contracts\Renderable>
     */
    public function formLayout(string $context): array
    {
        return [];
    }

    /**
     * Serializes form-fields для manifest. Если `formLayout('update')`
     * возвращает дерево Renderable — сериализуем его (Tabs/Rows/...);
     * иначе — плоский список Field'ов.
     *
     * @return list<array<string, mixed>>
     */
    private function serializeFormFields(): array
    {
        $layout = $this->formLayout('update');
        if ($layout !== []) {
            return array_map(
                static fn (\Dskripchenko\LaravelAdmin\Contracts\Renderable $r): array => $r->toArray(),
                $layout,
            );
        }

        return array_map(static fn (Field $f): array => $f->toArray(), $this->fields());
    }

    /* -----------------------------------------------------------------
     * Сериализация для манифеста
     * ----------------------------------------------------------------- */

    /**
     * Метаданные для манифеста и для resource.meta action.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $base = static::permission();

        return [
            'slug' => static::slug(),
            'label' => static::label(),
            'icon' => static::$icon,
            'group' => static::$group,
            // Eloquent morph-class модели — нужен фронту для AuditTimeline
            // (subject_type в /audit/timeline endpoint'е). Если modelClass
            // зарегистрирован в morphMap — отдаём alias, иначе FQCN.
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
            'columns' => array_map(static fn (TableColumn $c): array => $c->toArray(), $this->columns()),
            // infolist: используется ResourceViewPage для read-only display.
            // Default — TextEntry per field (см. Resource::infolist).
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
                'polling' => $this->polling(),
                'warnOnUnsavedChanges' => true,
                'creatable' => $this->fields() !== [],
                'editable' => $this->fields() !== [],
            ],
        ];
    }

    /**
     * Сериализованные фильтры с автоматическим добавлением TrashedFilter
     * для SoftDeletes-моделей. Host может явно прописать `TrashedFilter::for(...)`
     * в filters() — тогда auto-inject не дублирует.
     *
     * @return list<array<string, mixed>>
     */
    private function compiledFilters(): array
    {
        return array_map(static fn (Filter $f): array => $f->toArray(), $this->resolvedFilters());
    }

    /**
     * Фактический набор фильтр-ОБЪЕКТОВ (declared + авто-инжект TrashedFilter
     * для SoftDeletes). Единый источник и для манифеста (compiledFilters), и
     * для применения в search — иначе auto-trashed показывался в UI, но не
     * применялся (search шёл по filters(), без него).
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
     * Поддерживает ли модель Eloquent SoftDeletes — детектится через trait_uses.
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
     * Можно ли клонировать запись через ResourceController.replicate.
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
     * Можно ли менять порядок записей drag-n-drop'ом.
     */
    public function reorderable(): bool
    {
        return false;
    }

    /**
     * Можно ли импортировать данные через 4-step Import Wizard.
     */
    public function importable(): bool
    {
        return false;
    }

    /**
     * Форматы экспорта списка (пусто = экспорт скрыт). По умолчанию CSV;
     * read-only ресурсы (аудит) могут вернуть [] чтобы убрать кнопку.
     *
     * @return list<string>
     */
    public function exportable(): array
    {
        return ['csv'];
    }

    /**
     * Имя колонки, отвечающей за порядок (default `position`).
     */
    public function reorderColumn(): string
    {
        return 'position';
    }

    /**
     * FK-колонка self-references для иерархического ресурса (default `parent_id`
     * через автодетект по Eloquent relations). null — ресурс плоский.
     *
     * Override в подклассе чтобы силой включить tree-режим (`return 'parent_id'`)
     * или отключить его при наличии relations (`return null`).
     */
    public function hierarchyParentKey(): ?string
    {
        return self::detectHierarchyParentKey(static::$model ?? null);
    }

    /**
     * `'tree'` если ресурс иерархический, иначе `'list'`. Определяет какой
     * Generated*Screen компилирует ResourceController на `/r/{slug}` маршруте.
     */
    public function viewMode(): string
    {
        return $this->hierarchyParentKey() !== null ? 'tree' : 'list';
    }

    /**
     * Slug ресурса, чей index используется как "родительский" контекст —
     * куда возвращает кнопка «Назад» с form/view-страниц. Default null
     * (back ведёт на собственный index). Применяется когда ресурс показан
     * как leaf другого tree-view: например TemplateResource живёт под
     * GroupResource'ом в дереве, и back должен возвращать к дереву групп.
     */
    public function parentSlug(): ?string
    {
        return null;
    }

    /**
     * Контекстные actions, прикрепляемые к каждой ноде tree-view. Возвращает
     * массив deskriptor'ов — frontend (ResourceTreePage) рендерит их в
     * toolbar выбранного узла. Default — пусто.
     *
     * Каждый descriptor:
     *   - `id` (string)            — уникальный id внутри узла
     *   - `label` (string)         — отображаемый текст
     *   - `icon` (?string)         — lucide-имя иконки (kebab-case)
     *   - `variant` (?string)      — primary|secondary|ghost (default secondary)
     *   - `kind` ('navigate')      — пока единственный поддерживаемый
     *   - `to` (array)             — `{ slug, screen, params }`, params
     *                                подставляются как query при переходе.
     *                                `{id}` плейсхолдер заменяется на id
     *                                текущей записи.
     *
     * @return list<array<string, mixed>>
     */
    public function treeNodeActions(Model $row): array
    {
        return [];
    }

    /**
     * Pre-tree hook: дополнительные id основной модели, которые надо
     * подмешать в выборку tree-view. Применяется когда tree-search должен
     * учитывать вложенные leaf-узлы из другого Resource'а — например,
     * GroupResource при поиске возвращает id групп, в которые попали
     * matching templates (вместе с их предками), чтобы шаблон-leaf
     * визуально оказался под своей группой.
     *
     * Default — пустой массив.
     *
     * @return list<int|string>
     */
    public function treeAdditionalRowIds(?string $searchTerm): array
    {
        return [];
    }

    /**
     * Дополнительные leaf-узлы для tree-view, привязанные по parent_id
     * к узлам основного ресурса. Используется для отображения записей
     * другого Resource'а внутри текущего дерева — например шаблоны
     * под своей группой в дереве групп.
     *
     * `$searchTerm` (если не null) — текущий tree-search; leaves следует
     * отфильтровать по нему, чтобы поиск находил вложенные записи.
     *
     * Каждый leaf — массив с теми же полями что node (`key`, `label`,
     * `record`) плюс опционально:
     *   - `slug` — slug чужого Resource'а для cross-navigation
     *     (ResourceTreePage будет вести на `/admin/r/{slug}/{id}/edit`).
     *   - `kind` — свободный маркер для frontend-логики.
     *
     * Default — пустой массив (нет дополнительных leaf'ов).
     *
     * @param  list<Model>  $rows  Записи основного ресурса, попавшие в tree
     * @param  ?string  $searchTerm  Текущий поисковой запрос (null если нет)
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
     * Автодетект FK self-reference по конвенции:
     *  - метод `parent()` возвращает BelongsTo на ту же модель → берём foreignKey
     *  - метод `children()` возвращает HasMany на ту же модель → берём foreignKey
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
                // Метод parent() с другой сигнатурой — игнорируем.
            }
        }

        if ($key === null && method_exists($instance, 'children')) {
            try {
                $rel = $instance->children();
                if ($rel instanceof HasMany && $rel->getRelated()::class === $model) {
                    $key = $rel->getForeignKeyName();
                }
            } catch (\Throwable) {
                // children() не Eloquent-relation — игнорируем.
            }
        }

        return self::$hierarchyDetectCache[$model] = $key;
    }

    /**
     * Hook для контроля копируемых полей при replicate.
     *
     * Default: Eloquent `Model::replicate()` (копирует все атрибуты кроме
     * primary key и timestamps). Override в подклассе для regenerate'а
     * уникальных полей (slug + ' (copy)', uuid и т.д.).
     */
    public function replicate(Model $original): Model
    {
        $copy = $original->replicate();

        // Если есть title/name — добавим '(копия)' suffix чтобы избежать
        // нарушения unique-индексов на демо-уровне. Hook decorate'тся в
        // подклассах под конкретные поля.
        foreach (['name', 'title', 'slug'] as $col) {
            if ($copy->getAttribute($col) !== null) {
                $copy->setAttribute($col, $copy->getAttribute($col).' (копия)');
            }
        }

        return $copy;
    }

    /**
     * Интервал автообновления list-таблицы в секундах. null = не обновлять.
     * Например, 30 — таблица ре-fetch'ит данные каждые 30 секунд.
     */
    public function polling(): ?int
    {
        return null;
    }

    /**
     * Read-only entries для GeneratedViewScreen.
     *
     * Default: TextEntry для каждого поля из fields() с тем же label, кроме
     * `switch`-полей (Switcher) — те рендерятся как IconEntry с
     * локализованными Да/Нет, чтобы view-страница не показывала «true»/«false»
     * для boolean-флагов. Override в подклассе если нужна кастомизация
     * (BadgeEntry для статусов, ImageEntry для аватаров и т.д.).
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
