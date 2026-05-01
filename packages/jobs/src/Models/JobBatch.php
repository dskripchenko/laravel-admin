<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent-обёртка над таблицей Laravel `job_batches` (Bus::batch).
 *
 * @property string $id
 * @property string $name
 * @property int $total_jobs
 * @property int $pending_jobs
 * @property int $failed_jobs
 * @property string $failed_job_ids
 * @property string $options
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property-read int $processed_jobs
 * @property-read float $progress_pct
 */
final class JobBatch extends Model
{
    protected $table = 'job_batches';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id'];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'finished_at' => 'datetime',
        'total_jobs' => 'integer',
        'pending_jobs' => 'integer',
        'failed_jobs' => 'integer',
    ];

    /**
     * Сколько job'ов уже обработано (success или failed).
     */
    protected function processedJobs(): Attribute
    {
        return Attribute::get(
            fn (): int => max(0, (int) $this->total_jobs - (int) $this->pending_jobs),
        );
    }

    /**
     * Процент прогресса (0..100). Если total=0 — 0.
     */
    protected function progressPct(): Attribute
    {
        return Attribute::get(function (): float {
            $total = (int) $this->total_jobs;
            if ($total === 0) {
                return 0.0;
            }

            return round(($this->processed_jobs / $total) * 100, 1);
        });
    }

    /**
     * Состояние ('running' | 'cancelled' | 'finished' | 'finished_with_failures').
     */
    public function status(): string
    {
        if ($this->cancelled_at !== null) {
            return 'cancelled';
        }
        if ($this->finished_at === null) {
            return 'running';
        }

        return $this->failed_jobs > 0 ? 'finished_with_failures' : 'finished';
    }
}
