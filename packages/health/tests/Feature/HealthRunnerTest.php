<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Tests\Feature;

use Dskripchenko\LaravelAdminHealth\Checks\ClosureCheck;
use Dskripchenko\LaravelAdminHealth\Events\HealthCheckStatusChanged;
use Dskripchenko\LaravelAdminHealth\HealthRegistry;
use Dskripchenko\LaravelAdminHealth\HealthResult;
use Dskripchenko\LaravelAdminHealth\HealthRunner;
use Dskripchenko\LaravelAdminHealth\Models\HealthResultRecord;
use Dskripchenko\LaravelAdminHealth\Tests\TestCase;
use Illuminate\Support\Facades\Event;

final class HealthRunnerTest extends TestCase
{
    public function test_run_all_executes_all_registered_checks(): void
    {
        /** @var HealthRegistry $registry */
        $registry = $this->app->make(HealthRegistry::class);

        $okCheck = new ClosureCheck('test.ok', 'OK', fn () => true);
        $warningCheck = new ClosureCheck('test.warn', 'Warn', fn () => HealthResult::warning('high'));

        // ClosureCheck требует closure в конструкторе → не через class-string.
        // In-place reflective set checks-property (только в тестах).
        $reflection = new \ReflectionClass($registry);
        $property = $reflection->getProperty('checks');
        $property->setValue($registry, [
            'test.ok' => $okCheck,
            'test.warn' => $warningCheck,
        ]);

        /** @var HealthRunner $runner */
        $runner = $this->app->make(HealthRunner::class);
        $report = $runner->runAll();

        $this->assertCount(2, $report);
        $this->assertSame(2, HealthResultRecord::query()->count());
    }

    public function test_run_one_persists_result(): void
    {
        $check = new ClosureCheck('persist.test', 'Persist', fn () => HealthResult::ok('hello', ['k' => 'v']));

        /** @var HealthRunner $runner */
        $runner = $this->app->make(HealthRunner::class);
        $row = $runner->runOne($check);

        $this->assertSame('ok', $row['result']->status);

        $persisted = HealthResultRecord::query()->where('check_id', 'persist.test')->first();
        $this->assertNotNull($persisted);
        $this->assertSame('ok', $persisted->status);
        $this->assertSame('hello', $persisted->message);
        $this->assertSame(['k' => 'v'], $persisted->meta);
    }

    public function test_emits_event_on_status_change(): void
    {
        Event::fake();

        $check = new ClosureCheck('change.test', 'Change', fn () => HealthResult::failing('down'));

        /** @var HealthRunner $runner */
        $runner = $this->app->make(HealthRunner::class);
        // Первый run — нет previous → no event
        $runner->runOne($check);
        Event::assertNotDispatched(HealthCheckStatusChanged::class);

        // Подменяем closure на ok → status change → event
        $okCheck = new ClosureCheck('change.test', 'Change', fn () => HealthResult::ok());
        $runner->runOne($okCheck);
        Event::assertDispatched(HealthCheckStatusChanged::class);
    }

    public function test_cleanup_deletes_old_records(): void
    {
        // Создаём запись 10 дней назад
        HealthResultRecord::query()->create([
            'check_id' => 'old',
            'status' => 'ok',
            'message' => '',
            'meta' => null,
            'duration_ms' => 1,
            'ran_at' => now()->subDays(10),
        ]);
        HealthResultRecord::query()->create([
            'check_id' => 'recent',
            'status' => 'ok',
            'message' => '',
            'meta' => null,
            'duration_ms' => 1,
            'ran_at' => now()->subDays(2),
        ]);

        /** @var HealthRunner $runner */
        $runner = $this->app->make(HealthRunner::class);
        $deleted = $runner->cleanupOlderThan(7);

        $this->assertSame(1, $deleted);
        $this->assertSame(1, HealthResultRecord::query()->where('check_id', 'recent')->count());
    }
}
