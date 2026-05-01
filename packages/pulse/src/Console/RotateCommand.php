<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Console;

use Dskripchenko\LaravelAdminPulse\Models\PulseAggregate;
use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * `php artisan admin:pulse:rotate`
 *
 * Удаляет старые samples + aggregates по TTL'ам из config:
 *   - admin-pulse.retention.samples_hours
 *   - admin-pulse.retention.aggregates_days
 *
 * Запускается в scheduler->daily.
 */
final class RotateCommand extends Command
{
    protected $signature = 'admin:pulse:rotate';

    protected $description = 'Rotate (delete) old pulse samples + aggregates per TTL config';

    public function handle(): int
    {
        $samplesHours = (int) config('admin-pulse.retention.samples_hours', 24);
        $aggregatesDays = (int) config('admin-pulse.retention.aggregates_days', 7);

        $deletedSamples = PulseSample::query()
            ->where('sampled_at', '<', Carbon::now()->subHours($samplesHours))
            ->delete();

        $deletedAggregates = PulseAggregate::query()
            ->where('aggregated_at', '<', Carbon::now()->subDays($aggregatesDays))
            ->delete();

        $this->info("Deleted $deletedSamples sample(s), $deletedAggregates aggregate(s)");

        return self::SUCCESS;
    }
}
