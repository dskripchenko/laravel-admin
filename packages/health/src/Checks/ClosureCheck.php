<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Checks;

use Closure;
use Dskripchenko\LaravelAdminHealth\HealthCheck;
use Dskripchenko\LaravelAdminHealth\HealthResult;
use Throwable;

/**
 * Custom check через closure. Используется для one-off проверок, для
 * которых не стоит писать отдельный класс.
 *
 * Closure возвращает HealthResult либо bool (true=ok, false=failing).
 * Throwable от closure → failing с message от exception'а.
 */
final class ClosureCheck implements HealthCheck
{
    /** @var Closure(): (HealthResult|bool) */
    private Closure $closure;

    /**
     * @param  Closure(): (HealthResult|bool)  $closure
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        Closure $closure,
        private readonly string $category = 'custom',
        private readonly string $frequency = '5m',
        private readonly int $timeout = 5,
    ) {
        $this->closure = $closure;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function frequency(): string
    {
        return $this->frequency;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    public function run(): HealthResult
    {
        try {
            $result = ($this->closure)();
        } catch (Throwable $e) {
            return HealthResult::failing(
                'Exception в check\'е: '.$e->getMessage(),
                ['exception' => get_class($e)],
            );
        }

        if ($result instanceof HealthResult) {
            return $result;
        }

        if ($result === true) {
            return HealthResult::ok();
        }

        return HealthResult::failing('Closure returned falsy');
    }
}
