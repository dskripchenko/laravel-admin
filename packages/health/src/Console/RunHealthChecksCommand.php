<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Console;

use Dskripchenko\LaravelAdminHealth\HealthRunner;
use Illuminate\Console\Command;

/**
 * `php artisan admin:health:run`
 *
 * Запускает все зарегистрированные health-check'и, сохраняет результаты в
 * `admin_health_results`, эмитит HealthCheckStatusChanged при смене статуса.
 *
 * Используется в scheduler:
 *   $schedule->command('admin:health:run')->everyMinute()->withoutOverlapping();
 */
final class RunHealthChecksCommand extends Command
{
    protected $signature = 'admin:health:run';

    protected $description = 'Run all registered admin health-checks';

    public function handle(HealthRunner $runner): int
    {
        $report = $runner->runAll();

        if ($report === []) {
            $this->info('No health checks registered.');

            return self::SUCCESS;
        }

        $hasFail = false;
        foreach ($report as $row) {
            $status = $row['result']->status;
            $line = "[$status] {$row['check']->id()} ({$row['duration_ms']}ms): {$row['result']->message}";
            if ($status === 'ok') {
                $this->info($line);
            } elseif ($status === 'warning') {
                $this->warn($line);
            } else {
                // 'failing' — единственный остающийся вариант из union-type
                // HealthResult::$status. PHPStan-уверен в exhaustiveness.
                $this->error($line);
                $hasFail = true;
            }
        }

        return $hasFail ? self::FAILURE : self::SUCCESS;
    }
}
