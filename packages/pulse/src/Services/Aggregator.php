<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Services;

use Dskripchenko\LaravelAdminPulse\Models\PulseAggregate;
use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Illuminate\Support\Carbon;

/**
 * Агрегирует samples в percentile-метрики.
 *
 * Bucket'ы:
 *   - 'route.percentiles' — p50/p95/p99/count для каждой связки route+method
 *     за period
 *   - 'top_slow_route' — топ-10 routes по p95 (separate row per route)
 *
 * Period — окно [period_start, period_end). Caller (Aggregate command)
 * передаёт нужный диапазон (например последние 5 минут).
 */
final class Aggregator
{
    /**
     * Запустить aggregation за окно [from, to).
     *
     * @return int Количество записанных aggregate-rows
     */
    public function aggregate(Carbon $from, Carbon $to): int
    {
        $now = Carbon::now();
        $written = 0;

        // Route percentiles per kind=request
        $rows = PulseSample::query()
            ->where('kind', 'request')
            ->whereBetween('sampled_at', [$from, $to])
            ->get(['key', 'duration_ms']);

        $byRoute = [];
        foreach ($rows as $row) {
            $byRoute[$row->key][] = (int) $row->duration_ms;
        }

        foreach ($byRoute as $route => $durations) {
            sort($durations);
            $count = count($durations);
            $metrics = [
                'count' => $count,
                'p50' => $this->percentile($durations, 0.50),
                'p95' => $this->percentile($durations, 0.95),
                'p99' => $this->percentile($durations, 0.99),
                'min' => $durations[0],
                'max' => $durations[$count - 1],
                'avg' => (int) (array_sum($durations) / $count),
            ];

            PulseAggregate::query()->create([
                'bucket' => 'route.percentiles',
                'key' => $route,
                'metrics' => $metrics,
                'period_start' => $from,
                'period_end' => $to,
                'aggregated_at' => $now,
            ]);
            $written++;
        }

        return $written;
    }

    /**
     * Linear-interpolation percentile (p ∈ [0, 1]) на отсортированном массиве.
     *
     * @param  list<int>  $sorted
     */
    public function percentile(array $sorted, float $p): int
    {
        if ($sorted === []) {
            return 0;
        }
        $count = count($sorted);
        if ($count === 1) {
            return $sorted[0];
        }
        $rank = $p * ($count - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);
        if ($low === $high) {
            return $sorted[$low];
        }
        $weight = $rank - $low;

        return (int) round($sorted[$low] * (1 - $weight) + $sorted[$high] * $weight);
    }
}
