<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;
use Dskripchenko\LaravelAdmin\I18n\Localize;

/**
 * The abstract base class of every field widget.
 *
 * The fluent API goes through `__call`: any method that is not defined is
 * stored in `attributes[]` — with no argument it becomes `true`, with one it
 * becomes that value, with several it becomes an array. That lets one write:
 *
 *     Input::make('email')
 *         ->title('Email')
 *         ->placeholder('user@example.com')
 *         ->required()
 *         ->type('email');
 *
 * Concrete subclasses override `type()` and may add named methods for better
 * IDE completion — those end up calling the same `attributes[]` setters.
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

    /** @var array<string, mixed> Type-specific options: options for a select, a mask for an input, and so on. */
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
     * The type name for the SPA renderer: input, select, switch and so on.
     *
     * It is deliberately NOT called `type()`: Field has a fluent setter
     * `->type('email')` for the HTML input type, which goes through __call. Had
     * the abstract method been named `type()`, the getter and the setter would
     * have collided.
     */
    abstract public function fieldType(): string;

    /* -----------------------------------------------------------------
     * Construction
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
     * The catch-all setter: any unknown method is stored in the attributes.
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
     * The field's width in RowsLayout's twelve-column grid.
     *
     * By default — when this is never called — the field takes the whole width
     * of its row. As soon as at least one field in a RowsLayout has a span, the
     * layout switches into the twelve-column grid mode (UidGrid).
     */
    public function span(int $cols): static
    {
        $this->attributes['span'] = max(1, min(12, $cols));

        return $this;
    }

    /**
     * Makes the field visible only while another field of the form holds the
     * given value. Several calls are combined with AND. The value may be a
     * scalar, compared strictly, or a list, meaning any-of.
     *
     * Usage:
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

    /** Sets the field's value — the form's initial state. */
    public function default(mixed $value): static
    {
        $this->defaultValue = $value;

        return $this;
    }

    /**
     * The type-specific options, such as a select's options.
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
     * Tells whether the field applies in the given context: create, update or view.
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
     * Reading
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
     * Serialization
     * ----------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // The user-facing strings are translated during serialization, so the
        // host does not have to wrap its labels in __().
        $placeholder = $this->attributes['placeholder'] ?? null;
        $help = $this->attributes['help'] ?? null;

        return [
            'kind' => 'field',
            'name' => $this->name,
            'type' => $this->fieldType(),
            'label' => (string) Localize::string((string) ($this->attributes['title'] ?? '')),
            'placeholder' => is_string($placeholder) ? Localize::string($placeholder) : $placeholder,
            'help' => is_string($help) ? Localize::string($help) : $help,
            'required' => (bool) ($this->attributes['required'] ?? false),
            // Only string rules go into the manifest: object rules
            // (Rule::unique and the like) are not JSON-serializable and are
            // needed by the validator alone.
            'rules' => array_values(array_filter($this->rules, 'is_string')),
            'options' => Localize::options($this->options),
            'visibility' => [
                'create' => $this->onCreate ?? true,
                'update' => $this->onUpdate ?? true,
                'view' => $this->onView ?? true,
            ],
            'reactive' => $this->attributes['reactive'] ?? null,
            'defaultValue' => $this->defaultValue,
            'attributes' => Localize::attributes($this->attributes),
        ];
    }
}
