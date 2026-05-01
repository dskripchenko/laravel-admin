<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs\Tests\Unit;

use Dskripchenko\LaravelAdminJobs\Models\JobBatch;
use Dskripchenko\LaravelAdminJobs\Tests\TestCase;

final class JobBatchModelTest extends TestCase
{
    public function test_processed_jobs_is_total_minus_pending(): void
    {
        $batch = new JobBatch(['id' => 'a', 'total_jobs' => 100, 'pending_jobs' => 30, 'failed_jobs' => 0]);
        $this->assertSame(70, $batch->processed_jobs);
    }

    public function test_progress_pct_rounded(): void
    {
        $batch = new JobBatch(['id' => 'a', 'total_jobs' => 333, 'pending_jobs' => 100, 'failed_jobs' => 0]);
        $this->assertSame(70.0, $batch->progress_pct);
    }

    public function test_progress_pct_zero_when_total_is_zero(): void
    {
        $batch = new JobBatch(['id' => 'a', 'total_jobs' => 0, 'pending_jobs' => 0, 'failed_jobs' => 0]);
        $this->assertSame(0.0, $batch->progress_pct);
    }

    public function test_status_running_default(): void
    {
        $batch = new JobBatch(['id' => 'a', 'total_jobs' => 10, 'pending_jobs' => 5, 'failed_jobs' => 0]);
        $this->assertSame('running', $batch->status());
    }

    public function test_status_cancelled_when_cancelled_at_set(): void
    {
        $batch = new JobBatch([
            'id' => 'a', 'total_jobs' => 10, 'pending_jobs' => 5, 'failed_jobs' => 0,
            'cancelled_at' => now(),
        ]);
        $this->assertSame('cancelled', $batch->status());
    }

    public function test_status_finished_with_failures(): void
    {
        $batch = new JobBatch([
            'id' => 'a', 'total_jobs' => 10, 'pending_jobs' => 0, 'failed_jobs' => 2,
            'finished_at' => now(),
        ]);
        $this->assertSame('finished_with_failures', $batch->status());
    }

    public function test_status_clean_finished(): void
    {
        $batch = new JobBatch([
            'id' => 'a', 'total_jobs' => 10, 'pending_jobs' => 0, 'failed_jobs' => 0,
            'finished_at' => now(),
        ]);
        $this->assertSame('finished', $batch->status());
    }
}
