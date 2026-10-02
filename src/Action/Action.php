<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;

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

    /**
     * The permission a user needs to see and to run the action. It is checked
     * on the server: an action the user lacks it for is left out of the
     * manifest and the command bar, and a request that runs it anyway is
     * answered with a 403 (`errorKey: action_forbidden`).
     */
    public function permission(string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * One of the action's attributes — `method`, `handler`, `opens` — as set.
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    /**
     * Whether the user — by default the one signed in to the current panel —
     * holds the action's permission; true when it declares none.
     */
    public function isPermitted(?object $user = null): bool
    {
        return PermissionCheck::allows($this->permission, $user);
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
     * Opens a Modal or Drawer layout of the screen instead of calling a
     * method. The layout is found by its id, so give it a stable one:
     *
     *     Layout::modal('Edit', [...])->withId('edit-modal')
     *     Button::make('Edit')->opens('edit-modal')
     */
    public function opens(string $layoutId): static
    {
        $this->attributes['opens'] = $layoutId;

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
     * Marks an action that runs on its own, without selected records — an
     * import, a recalculation, a sync. It is sent with no ids and its method
     * receives an empty list. An action in a row or in the bulk bar applies to
     * records unless marked so; anywhere else it is standalone already.
     */
    public function standalone(bool $standalone = true): static
    {
        $this->attributes['standalone'] = $standalone;

        return $this;
    }

    /**
     * Whether the action applies to records and cannot run without at least
     * one: an explicit standalone() decides, otherwise the position does — a
     * row or bulk action needs records, a command bar or header one does not.
     */
    public function requiresSelection(): bool
    {
        $standalone = $this->attributes['standalone'] ?? null;
        if (is_bool($standalone)) {
            return ! $standalone;
        }

        return in_array('row', $this->position, true) || in_array('bulk', $this->position, true);
    }

    /**
     * @param  bool|callable(): bool  $cond
     */
    public function canSee(bool|callable $cond): static
    {
        $this->visibility = $cond;

        return $this;
    }

    /**
     * Whether the action is shown — and may be run: its canSee() condition
     * holds and the current user has its permission().
     */
    public function isVisible(): bool
    {
        return $this->passesVisibility() && $this->isPermitted();
    }

    /**
     * The canSee() condition alone, without the permission.
     */
    public function passesVisibility(): bool
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
            // modalTitle, submitLabel and the other captions among them.
            'attributes' => \Dskripchenko\LaravelAdmin\I18n\Localize::attributes($this->attributes),
        ];
    }
}
