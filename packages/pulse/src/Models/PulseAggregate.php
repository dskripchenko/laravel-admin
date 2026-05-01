<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Агрегированные метрики (p50/p95/p99 по route, top-slowest queries и т.п.).
 *
 * @property int $id
 * @property string $bucket 'route.p95' | 'top_slow_query' | ...
 * @property string $key
 * @property array<string, mixed> $metrics
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property \Illuminate\Support\Carbon $aggregated_at
 */
final class PulseAggregate extends Model
{
    protected $table = 'admin_pulse_aggregates';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'metrics' => 'array',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'aggregated_at' => 'datetime',
    ];
}
