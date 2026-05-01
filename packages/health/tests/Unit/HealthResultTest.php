<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Tests\Unit;

use Dskripchenko\LaravelAdminHealth\HealthResult;
use Dskripchenko\LaravelAdminHealth\Tests\TestCase;

final class HealthResultTest extends TestCase
{
    public function test_ok_factory(): void
    {
        $r = HealthResult::ok('all good', ['x' => 1]);
        $this->assertSame('ok', $r->status);
        $this->assertSame('all good', $r->message);
        $this->assertSame(['x' => 1], $r->meta);
        $this->assertTrue($r->isOk());
        $this->assertFalse($r->isWarning());
        $this->assertFalse($r->isFailing());
    }

    public function test_warning_factory(): void
    {
        $r = HealthResult::warning('high latency');
        $this->assertSame('warning', $r->status);
        $this->assertTrue($r->isWarning());
    }

    public function test_failing_factory(): void
    {
        $r = HealthResult::failing('down');
        $this->assertSame('failing', $r->status);
        $this->assertTrue($r->isFailing());
    }

    public function test_default_message_for_ok(): void
    {
        $this->assertSame('OK', HealthResult::ok()->message);
    }
}
