<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Services;

use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Illuminate\Support\Carbon;

/**
 * Низкоуровневый sampler — пишет samples в `admin_pulse_samples`.
 *
 * Sample-rate (0..1) применяется в `shouldSample()` через mt_rand(); rate=1.0
 * пишет всё, rate=0.1 — 10%. Persistence inline (синхронно), но host-проект
 * может wrap'нуть в queued job для не-блокирующих вставок.
 *
 * Используется PulseMiddleware'ом для request-метрики и host'ом
 * вручную для job/exception/cache-метрик через `record()`.
 */
final class Sampler
{
    /**
     * Должен ли этот sample пройти отбор?
     */
    public function shouldSample(string $kind): bool
    {
        $rate = (float) config("admin-pulse.sample_rate.$kind", 1.0);
        if ($rate >= 1.0) {
            return true;
        }
        if ($rate <= 0.0) {
            return false;
        }

        return mt_rand(0, 999) < (int) ($rate * 1000);
    }

    /**
     * Записать sample в БД.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function record(
        string $kind,
        string $key,
        int $durationMs,
        ?string $label = null,
        ?int $statusCode = null,
        ?array $meta = null,
    ): void {
        PulseSample::query()->create([
            'kind' => $kind,
            'key' => $key,
            'label' => $label,
            'duration_ms' => $durationMs,
            'status_code' => $statusCode,
            'meta' => $meta,
            'sampled_at' => Carbon::now(),
        ]);
    }

    /**
     * Нормализованный SQL fingerprint — заменяет литералы на ?.
     *
     * Простая версия: PCRE по quoted strings + numeric literals. Для
     * production-grade нормализации нужен полноценный SQL-parser.
     */
    public function fingerprintSql(string $sql): string
    {
        $sql = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", "'?'", $sql) ?? $sql;
        $sql = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '"?"', $sql) ?? $sql;
        $sql = preg_replace('/\b\d+(\.\d+)?\b/', '?', $sql) ?? $sql;

        return trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);
    }

    /**
     * Hash от exception-сигнатуры (class + message + first-stack-line).
     */
    public function fingerprintException(\Throwable $e): string
    {
        $head = get_class($e).':'.$e->getMessage();
        $trace = $e->getFile().':'.$e->getLine();

        return substr(hash('xxh64', $head."\n".$trace), 0, 16);
    }
}
