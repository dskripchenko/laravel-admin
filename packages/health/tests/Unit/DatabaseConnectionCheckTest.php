<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Tests\Unit;

use Dskripchenko\LaravelAdminHealth\Checks\DatabaseConnectionCheck;
use Dskripchenko\LaravelAdminHealth\Tests\TestCase;

final class DatabaseConnectionCheckTest extends TestCase
{
    public function test_default_connection_works_in_testing(): void
    {
        $check = new DatabaseConnectionCheck(['connections' => ['testing']]);
        $r = $check->run();
        $this->assertTrue($r->isOk());
        $this->assertSame(['testing'], $r->meta['checked']);
    }

    public function test_failing_connection_reports_failing(): void
    {
        $check = new DatabaseConnectionCheck(['connections' => ['no_such_connection']]);
        $r = $check->run();
        $this->assertTrue($r->isFailing());
        $this->assertArrayHasKey('failures', $r->meta);
    }
}
