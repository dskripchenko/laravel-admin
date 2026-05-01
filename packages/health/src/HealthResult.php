<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth;

/**
 * Результат одного запуска check'а.
 *
 * Иммутабельный value-object. Создаётся через factories ok/warning/failing.
 *
 * @phpstan-type HealthStatus 'ok'|'warning'|'failing'
 */
final class HealthResult
{
    /** @phpstan-var HealthStatus */
    public readonly string $status;

    /**
     * @param  array<string, mixed>  $meta
     *
     * @phpstan-param HealthStatus $status
     */
    public function __construct(
        string $status,
        public readonly string $message = '',
        public readonly array $meta = [],
    ) {
        $this->status = $status;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function ok(string $message = 'OK', array $meta = []): self
    {
        return new self('ok', $message, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function warning(string $message, array $meta = []): self
    {
        return new self('warning', $message, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function failing(string $message, array $meta = []): self
    {
        return new self('failing', $message, $meta);
    }

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }

    public function isWarning(): bool
    {
        return $this->status === 'warning';
    }

    public function isFailing(): bool
    {
        return $this->status === 'failing';
    }
}
