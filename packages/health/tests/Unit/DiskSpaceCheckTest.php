<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Tests\Unit;

use Dskripchenko\LaravelAdminHealth\Checks\DiskSpaceCheck;
use Dskripchenko\LaravelAdminHealth\Tests\TestCase;

final class DiskSpaceCheckTest extends TestCase
{
    public function test_existing_path_returns_ok(): void
    {
        // /tmp всегда существует, и место там обычно > 5%
        $check = new DiskSpaceCheck(['paths' => ['/tmp']]);
        $r = $check->run();
        // Не failing/warning при разумном fail/warn threshold
        $this->assertContains($r->status, ['ok', 'warning']);
        $this->assertArrayHasKey('paths', $r->meta);
    }

    public function test_nonexistent_path_skipped(): void
    {
        $check = new DiskSpaceCheck(['paths' => ['/nonexistent_path_xyzzy']]);
        $r = $check->run();
        $this->assertTrue($r->isOk());
    }
}
