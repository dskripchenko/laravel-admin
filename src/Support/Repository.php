<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use ArrayAccess;
use Illuminate\Support\Arr;

/**
 * State-репозиторий с dot-notation доступом.
 *
 * Используется как state контейнер для Screen/Resource: `query()` возвращает
 * Repository, layout/field читают значения по dot-notation
 * (`addresses.0.city`).
 *
 * Тонкая обёртка над `Illuminate\Support\Arr::get/set/has/forget`.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class Repository implements ArrayAccess
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data = []) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(array $data = []): self
    {
        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    public function set(string $key, mixed $value): self
    {
        Arr::set($this->data, $key, $value);

        return $this;
    }

    public function has(string $key): bool
    {
        return Arr::has($this->data, $key);
    }

    public function forget(string $key): self
    {
        Arr::forget($this->data, $key);

        return $this;
    }

    /**
     * @param  array<string, mixed>|self  $other
     */
    public function merge(array|self $other): self
    {
        $other = $other instanceof self ? $other->toArray() : $other;
        $this->data = array_merge_deep($this->data, $other);

        return $this;
    }

    /**
     * @param  list<string>  $keys
     */
    public function only(array $keys): self
    {
        $result = [];
        foreach ($keys as $key) {
            if (Arr::has($this->data, $key)) {
                Arr::set($result, $key, Arr::get($this->data, $key));
            }
        }

        return new self($result);
    }

    /**
     * @param  list<string>  $keys
     */
    public function except(array $keys): self
    {
        $clone = $this->data;
        foreach ($keys as $key) {
            Arr::forget($clone, $key);
        }

        return new self($clone);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson(): string
    {
        return (string) json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function isEmpty(): bool
    {
        return $this->data === [];
    }

    /* -----------------------------------------------------------------
     * ArrayAccess
     * ----------------------------------------------------------------- */

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;

            return;
        }
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->forget((string) $offset);
    }
}
