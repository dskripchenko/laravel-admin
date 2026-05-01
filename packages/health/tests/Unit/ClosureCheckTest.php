<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Tests\Unit;

use Dskripchenko\LaravelAdminHealth\Checks\ClosureCheck;
use Dskripchenko\LaravelAdminHealth\HealthResult;
use Dskripchenko\LaravelAdminHealth\Tests\TestCase;
use RuntimeException;

final class ClosureCheckTest extends TestCase
{
    public function test_returns_ok_when_closure_returns_true(): void
    {
        $check = new ClosureCheck('test', 'Test', fn () => true);
        $r = $check->run();
        $this->assertTrue($r->isOk());
    }

    public function test_returns_failing_when_closure_returns_false(): void
    {
        $check = new ClosureCheck('test', 'Test', fn () => false);
        $r = $check->run();
        $this->assertTrue($r->isFailing());
    }

    public function test_passes_through_health_result(): void
    {
        $check = new ClosureCheck('test', 'Test', fn () => HealthResult::warning('low storage'));
        $r = $check->run();
        $this->assertTrue($r->isWarning());
        $this->assertSame('low storage', $r->message);
    }

    public function test_catches_throwable(): void
    {
        $check = new ClosureCheck('test', 'Test', function () {
            throw new RuntimeException('boom');
        });
        $r = $check->run();
        $this->assertTrue($r->isFailing());
        $this->assertStringContainsString('boom', $r->message);
        $this->assertSame(RuntimeException::class, $r->meta['exception']);
    }

    public function test_metadata_methods(): void
    {
        $check = new ClosureCheck(
            'my.id',
            'My Name',
            fn () => true,
            'custom-cat',
            '15m',
            10,
        );
        $this->assertSame('my.id', $check->id());
        $this->assertSame('My Name', $check->name());
        $this->assertSame('custom-cat', $check->category());
        $this->assertSame('15m', $check->frequency());
        $this->assertSame(10, $check->timeout());
    }
}
