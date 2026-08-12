<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * The abstract action — a button, a link or a dropdown in a command bar, a
 * row or a bulk operation.
 *
 * The concrete subclasses (Button, Link, DropDown, Modal, Bulk and the rest)
 * supply type() plus their own fluent methods; arbitrary attributes go through
 * the shared `__call`.
 *
 * @phpstan-consistent-constructor
 *
 * @method $this icon(string $icon)
 * @method $this color(string $color)
 * @method $this primary(bool $primary = true)
 * @method $this destructive(bool $destructive = true)
 */
abstract class Action implements Renderable
{
    protected string $name;

    protected string $label;

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var bool|callable(): bool */
    protected $visibility = true;

    protected ?string $permission = null;

    /** @var array{message: string, title?: string}|null */
    protected ?array $confirm = null;

    /** @var list<'command_bar'|'row'|'bulk'|'header'> */
    protected array $position = ['command_bar'];

    abstract public function type(): string;

    public static function make(string $label): static
    {
        /** @var static $instance */
        $instance = new static;
        $instance->label = $label;
        $instance->name = self::deriveName($label);

        return $instance;
    }

    private static function deriveName(string $label): string
    {
        $name = preg_replace('/[^a-zA-Z0-9]+/', '_', $label) ?? '';

        return strtolower(trim($name, '_')) ?: 'action';
    }

    public function name(): string
    {
        return $this->name;
    }

    public function withName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function permission(string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * @param  array<string, mixed>|string  $confirm  A message, or `[message, title]`.
     */
    public function confirm(array|string $confirm): static
    {
        if (is_string($confirm)) {
            $confirm = ['message' => $confirm];
        }
        if (! isset($confirm['title'])) {
            $confirm['title'] = 'Подтверждение';
        }
        /** @var array{message: string, title: string} $confirm */
        $this->confirm = $confirm;

        return $this;
    }

    /**
     * @param  list<'command_bar'|'row'|'bulk'|'header'>  $positions
     */
    public function position(array $positions): static
    {
        $this->position = $positions;

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

    /**
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $confirm = $this->confirm;
        if (is_array($confirm)) {
            foreach (['title', 'message', 'confirmLabel', 'cancelLabel'] as $key) {
                $value = $confirm[$key] ?? null;
                if (is_string($value)) {
                    $confirm[$key] = \Dskripchenko\LaravelAdmin\I18n\Localize::string($value);
                }
            }
        }

        return [
            'kind' => 'action',
            'name' => $this->name,
            'label' => \Dskripchenko\LaravelAdmin\I18n\Localize::string($this->label),
            'type' => $this->type(),
            'icon' => $this->attributes['icon'] ?? null,
            'permission' => $this->permission,
            'confirm' => $confirm,
            'primary' => (bool) ($this->attributes['primary'] ?? false),
            'destructive' => (bool) ($this->attributes['destructive'] ?? false),
            'position' => $this->position,
            'attributes' => $this->attributes,
        ];
    }
}
