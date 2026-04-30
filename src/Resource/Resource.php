<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Field\ValidationRulesExporter;
use Dskripchenko\LaravelAdmin\Filter\Filter;
use Dskripchenko\LaravelAdmin\Infolist\Entry;
use Dskripchenko\LaravelAdmin\Infolist\TextEntry;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
     * Whitelist для eager-loading из ?with[]=... и Resource::with().
     *
     * @return list<string>
     */
    public function with(): array
    {
        return [];
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
            'permissions' => [
                'view' => $base.'.view',
                'create' => $base.'.create',
                'update' => $base.'.update',
                'delete' => $base.'.delete',
                'restore' => $base.'.restore',
                'force_delete' => $base.'.force-delete',
            ],
            'fields' => array_map(static fn (Field $f): array => $f->toArray(), $this->fields()),
            'columns' => array_map(static fn (TableColumn $c): array => $c->toArray(), $this->columns()),
            'filters' => array_map(static fn (Filter $f): array => $f->toArray(), $this->filters()),
            'actions' => array_map(static fn (Action $a): array => $a->toArray(), $this->actions()),
            'searchable' => $this->searchableFields(),
            'with' => $this->with(),
            'features' => [
                'softDeletes' => false,
                'replicable' => false,
                'reorderable' => false,
                'importable' => false,
                'exportable' => ['csv'],
                'polling' => $this->polling(),
                'warnOnUnsavedChanges' => true,
            ],
        ];
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
     * Default: TextEntry для каждого поля из fields() с тем же label. Override
     * в подклассе если нужна кастомизация (BadgeEntry для статусов, ImageEntry
     * для аватаров и т.д.).
     *
     * @return list<Entry>
     */
    public function infolist(): array
    {
        $entries = [];
        foreach ($this->fields() as $field) {
            $name = $field->name();
            $label = (string) ($field->getAttributes()['title'] ?? $name);
            $entries[] = TextEntry::make($name)->label($label);
        }

        return $entries;
    }
}
