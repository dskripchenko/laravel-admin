<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * Абстрактный базовый класс всех Field-виджетов.
 *
 * Fluent API через `__call`: любой неопределённый метод сохраняется в
 * `attributes[]` (без значения = `true`, с одним = это значение, с массивом
 * = массив). Это позволяет писать:
 *
 *     Input::make('email')
 *         ->title('Email')
 *         ->placeholder('user@example.com')
 *         ->required()
 *         ->type('email');
 *
 * Конкретные подклассы переопределяют `type()` и могут добавлять named
 * методы (для лучшего IDE-автокомплита) — те будут вызывать те же
 * `attributes[]`-сеттеры.
 *
 * @phpstan-consistent-constructor
 *
 * @method static placeholder(string $placeholder)
 * @method static help(string $help)
 * @method static title(string $title)
 * @method static disabled(bool $disabled = true)
 * @method static readonly(bool $readonly = true)
 * @method static autofocus()
 */
abstract class Field implements Renderable
{
    protected string $name;

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> Опции, type-specific (options для select, mask для input, ...). */
    protected array $options = [];

    /** @var list<string|array<string, mixed>> Laravel-style validation rules. */
    protected array $rules = [];

    /** @var bool|callable(): bool */
    protected $visibility = true;

    protected mixed $defaultValue = null;

    protected ?bool $onCreate = null;

    protected ?bool $onUpdate = null;

    protected ?bool $onView = null;

    /**
     * Type-имя для SPA-renderer'а (input/select/switch/...).
     *
     * Намеренно НЕ называется `type()` — у Field есть fluent-сеттер `->type('email')`
     * для HTML input-type, который проходит через __call. Если бы абстракт назывался
     * `type()`, getter и setter конфликтовали бы.
     */
    abstract public function fieldType(): string;

    /* -----------------------------------------------------------------
     * Создание
     * ----------------------------------------------------------------- */

    public static function make(string $name): static
    {
        /** @var static $instance */
        $instance = new static;
        $instance->name = $name;

        return $instance;
    }

    public function name(): string
    {
        return $this->name;
    }

    /* -----------------------------------------------------------------
     * Fluent API
     * ----------------------------------------------------------------- */

    /**
     * Catch-all сеттер. Любой неизвестный метод сохраняется в attributes.
     *
     * @param  list<mixed>  $args
     */
    public function __call(string $method, array $args): static
    {
        $this->attributes[$method] = match (count($args)) {
            0 => true,
            1 => $args[0],
            default => $args,
        };

        return $this;
    }

    /**
     * Ширина поля в 12-колоночной сетке RowsLayout.
     *
     * По умолчанию (без вызова) поле занимает полную ширину строки.
     * Если хотя бы один Field в RowsLayout имеет span — RowsLayout
     * переключается в grid-12 mode (UidGrid).
     */
    public function span(int $cols): static
    {
        $this->attributes['span'] = max(1, min(12, $cols));

        return $this;
    }

    /**
     * Поле видимо только когда другое поле формы имеет указанное значение.
     * Несколько вызовов — условия объединяются по «И». Значение может быть
     * скаляром (строгий ===) либо list (any-of).
     *
     * Использование:
     *   Input::make('config_root')->visibleWhen('driver', 'local')
     *   Input::make('s3_endpoint')->visibleWhen('driver', ['s3', 'minio'])
     */
    public function visibleWhen(string $field, mixed $expected): static
    {
        /** @var array<string, mixed> $reactive */
        $reactive = $this->attributes['reactive'] ?? [];
        $reactive[$field] = $expected;
        $this->attributes['reactive'] = $reactive;

        return $this;
    }

    /** Установить значение field (initial state формы). */
    public function default(mixed $value): static
    {
        $this->defaultValue = $value;

        return $this;
    }

    /**
     * Тип-specific опции (например, options для select).
     *
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): static
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * @param  list<string|array<string, mixed>>  $rules  Laravel-style.
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->attributes['required'] = $required;
        if ($required && ! in_array('required', $this->rules, true)) {
            $this->rules[] = 'required';
        }

        return $this;
    }

    /**
     * @param  bool|callable(): bool  $cond
     */
    public function canSee(bool|callable $cond): static
    {
        $this->visibility = $cond;

        return $this;
    }

    public function isVisible(): bool
    {
        return is_callable($this->visibility)
            ? (bool) ($this->visibility)()
            : (bool) $this->visibility;
    }

    public function onCreate(bool $on = true): static
    {
        $this->onCreate = $on;

        return $this;
    }

    public function onUpdate(bool $on = true): static
    {
        $this->onUpdate = $on;

        return $this;
    }

    public function onView(bool $on = true): static
    {
        $this->onView = $on;

        return $this;
    }

    /**
     * Применяется ли field в указанном контексте (create/update/view).
     */
    public function appliesTo(string $context): bool
    {
        return match ($context) {
            'create' => $this->onCreate ?? true,
            'update' => $this->onUpdate ?? true,
            'view' => $this->onView ?? true,
            default => true,
        };
    }

    /* -----------------------------------------------------------------
     * Чтение
     * ----------------------------------------------------------------- */

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return list<string|array<string, mixed>>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /* -----------------------------------------------------------------
     * Сериализация
     * ----------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => 'field',
            'name' => $this->name,
            'type' => $this->fieldType(),
            'label' => (string) ($this->attributes['title'] ?? ''),
            'placeholder' => $this->attributes['placeholder'] ?? null,
            'help' => $this->attributes['help'] ?? null,
            'required' => (bool) ($this->attributes['required'] ?? false),
            // В манифест едут только строковые правила: object-rules
            // (Rule::unique и т.п.) не JSON-сериализуемы и нужны только валидатору.
            'rules' => array_values(array_filter($this->rules, 'is_string')),
            'options' => $this->options,
            'visibility' => [
                'create' => $this->onCreate ?? true,
                'update' => $this->onUpdate ?? true,
                'view' => $this->onView ?? true,
            ],
            'reactive' => $this->attributes['reactive'] ?? null,
            'defaultValue' => $this->defaultValue,
            'attributes' => $this->attributes,
        ];
    }
}
