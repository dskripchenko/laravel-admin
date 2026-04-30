<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * Read-only Entry для Infolist'а — display-аналог Field.
 *
 * Используется в GeneratedViewScreen для отображения записи без формы.
 * Source указывает на поле модели/state. Default value применяется когда
 * source отсутствует или равен null.
 *
 * @phpstan-consistent-constructor
 *
 * @method static label(string $label)
 * @method static help(string $help)
 * @method static placeholder(string $placeholder)
 */
abstract class Entry implements Renderable
{
    protected string $name;

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var bool|callable(): bool */
    protected $visibility = true;

    protected mixed $defaultValue = null;

    /**
     * Type-имя для SPA-renderer'а: text/badge/icon/color/key_value/...
     */
    abstract public function entryType(): string;

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

    public function default(mixed $value): static
    {
        $this->defaultValue = $value;

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

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => 'entry',
            'name' => $this->name,
            'type' => $this->entryType(),
            'label' => (string) ($this->attributes['label'] ?? ''),
            'help' => $this->attributes['help'] ?? null,
            'defaultValue' => $this->defaultValue,
            'attributes' => $this->attributes,
        ];
    }
}
