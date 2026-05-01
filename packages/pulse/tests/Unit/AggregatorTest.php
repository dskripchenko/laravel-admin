<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests\Unit;

use Dskripchenko\LaravelAdminPulse\Models\PulseAggregate;
use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Dskripchenko\LaravelAdminPulse\Services\Aggregator;
use Dskripchenko\LaravelAdminPulse\Tests\TestCase;
use Illuminate\Support\Carbon;

final class AggregatorTest extends TestCase
{
    public function test_percentile_correctness(): void
    {
        $aggregator = new Aggregator;
        $sorted = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $this->assertSame(1, $aggregator->percentile($sorted, 0.0));
        $this->assertSame(10, $aggregator->percentile($sorted, 1.0));
        $this->assertSame(6, $aggregator->percentile($sorted, 0.50));
        $this->assertSame(10, $aggregator->percentile($sorted, 0.95));
    }

    public function test_percentile_empty_returns_zero(): void
    {
        $aggregator = new Aggregator;
        $this->assertSame(0, $aggregator->percentile([], 0.95));
    }

    public function test_aggregate_creates_route_percentile_rows(): void
    {
        $now = Carbon::now()->floor('5 minutes');
        $from = $now->copy()->subMinutes(5);
        $to = $now;

        // 10 samples per route, two routes
        for ($i = 1; $i <= 10; $i++) {
            PulseSample::query()->create([
                'kind' => 'request',
                'key' => 'GET /a',
                'label' => 'GET',
                'duration_ms' => $i * 10,
                'status_code' => 200,
                'meta' => null,
                'sampled_at' => $from->copy()->addSeconds($i),
            ]);
            PulseSample::query()->create([
                'kind' => 'request',
                'key' => 'GET /b',
                'label' => 'GET',
                'duration_ms' => $i * 100,
                'status_code' => 200,
                'meta' => null,
                'sampled_at' => $from->copy()->addSeconds($i),
            ]);
        }

        $aggregator = new Aggregator;
        $written = $aggregator->aggregate($from, $to);
        $this->assertSame(2, $written);

        $a = PulseAggregate::query()->where('key', 'GET /a')->first();
        $this->assertNotNull($a);
        $this->assertSame(10, $a->metrics['count']);
        $this->assertSame(100, $a->metrics['max']);
        $this->assertSame(10, $a->metrics['min']);
        $this->assertGreaterThan(0, $a->metrics['p95']);
        $this->assertSame('route.percentiles', $a->bucket);
    }

    public function test_aggregate_returns_zero_when_no_samples(): void
    {
        $aggregator = new Aggregator;
        $written = $aggregator->aggregate(Carbon::now()->subMinutes(5), Carbon::now());
        $this->assertSame(0, $written);
    }
}
