<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Console;

use Dskripchenko\LaravelAdminHealth\HealthRunner;
use Illuminate\Console\Command;

/**
 * `php artisan admin:health:cleanup`
 *
 * Удаляет старые записи из `admin_health_results` (TTL из config). Запускать
 * раз в сутки в scheduler.
 */
final class CleanupHealthResultsCommand extends Command
{
    protected $signature = 'admin:health:cleanup';

    protected $description = 'Cleanup old health-check results (TTL from config)';

    public function handle(HealthRunner $runner): int
    {
        $days = (int) config('admin-health.history_days', 7);
        $deleted = $runner->cleanupOlderThan($days);

        $this->info("Deleted $deleted health-result row(s) older than $days days");

        return self::SUCCESS;
    }
}
