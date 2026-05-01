<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Console;

use Dskripchenko\LaravelAdminPulse\Services\Aggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * `php artisan admin:pulse:aggregate [--minutes=5]`
 *
 * Запускается в scheduler everyFiveMinutes:
 *   $schedule->command('admin:pulse:aggregate')->everyFiveMinutes();
 */
final class AggregateCommand extends Command
{
    protected $signature = 'admin:pulse:aggregate {--minutes=5 : Window size in minutes}';

    protected $description = 'Aggregate raw pulse samples into percentile metrics';

    public function handle(Aggregator $aggregator): int
    {
        $minutes = (int) $this->option('minutes');
        $to = Carbon::now()->floor("{$minutes}minutes");
        $from = $to->copy()->subMinutes($minutes);

        $written = $aggregator->aggregate($from, $to);

        $this->info("Wrote $written aggregate row(s) for window [$from, $to)");

        return self::SUCCESS;
    }
}
