<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth;

use Dskripchenko\LaravelAdminHealth\Events\HealthCheckStatusChanged;
use Dskripchenko\LaravelAdminHealth\Models\HealthResultRecord;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;

/**
 * Запускает зарегистрированные HealthCheck'и + persist'ит результаты.
 *
 * Не считает frequency сам — runner всегда выполняет ВСЕ check'и; respect'ить
 * `frequency()` должен caller (scheduler с cron-like интервалом).
 *
 * При смене статуса (ok → failing/warning или обратно) эмитит
 * HealthCheckStatusChanged event.
 */
final class HealthRunner
{
    public function __construct(
        private readonly HealthRegistry $registry,
        private readonly Dispatcher $events,
    ) {}

    /**
     * Запустить все check'и.
     *
     * @return list<array{check: HealthCheck, result: HealthResult, duration_ms: int}>
     */
    public function runAll(): array
    {
        $report = [];

        foreach ($this->registry->all() as $check) {
            $report[] = $this->runOne($check);
        }

        return $report;
    }

    /**
     * Запустить один check + сохранить результат.
     *
     * @return array{check: HealthCheck, result: HealthResult, duration_ms: int}
     */
    public function runOne(HealthCheck $check): array
    {
        $previousStatus = $this->lastStatusFor($check->id());

        $start = (int) (microtime(true) * 1000);
        try {
            $result = $check->run();
        } catch (\Throwable $e) {
            $result = HealthResult::failing(
                'Exception во время run(): '.$e->getMessage(),
                ['exception' => get_class($e)],
            );
        }
        $duration = (int) (microtime(true) * 1000) - $start;

        $this->persist($check, $result, $duration);

        if ($previousStatus !== null && $previousStatus !== $result->status) {
            $this->events->dispatch(new HealthCheckStatusChanged($check, $result, $previousStatus));
        }

        return ['check' => $check, 'result' => $result, 'duration_ms' => $duration];
    }

    /**
     * Получить последний статус (или null если ещё ни разу не запускали).
     */
    public function lastStatusFor(string $checkId): ?string
    {
        $row = HealthResultRecord::query()
            ->where('check_id', $checkId)
            ->orderByDesc('ran_at')
            ->first();

        return $row?->status;
    }

    private function persist(HealthCheck $check, HealthResult $result, int $durationMs): void
    {
        HealthResultRecord::query()->create([
            'check_id' => $check->id(),
            'status' => $result->status,
            'message' => $result->message,
            'meta' => $result->meta,
            'duration_ms' => $durationMs,
            'ran_at' => Carbon::now(),
        ]);
    }

    /**
     * Удалить старые результаты — чистим раз в сутки.
     */
    public function cleanupOlderThan(int $days): int
    {
        return HealthResultRecord::query()
            ->where('ran_at', '<', Carbon::now()->subDays($days))
            ->delete();
    }
}
