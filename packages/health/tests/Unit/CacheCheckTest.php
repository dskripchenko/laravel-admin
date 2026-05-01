<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Tests\Unit;

use Dskripchenko\LaravelAdminHealth\Checks\CacheCheck;
use Dskripchenko\LaravelAdminHealth\Tests\TestCase;

final class CacheCheckTest extends TestCase
{
    public function test_array_store_round_trip_ok(): void
    {
        $check = new CacheCheck(['stores' => ['array']]);
        $r = $check->run();
        $this->assertTrue($r->isOk());
    }

    public function test_unknown_store_fails(): void
    {
        $check = new CacheCheck(['stores' => ['nonexistent']]);
        $r = $check->run();
        $this->assertTrue($r->isFailing());
    }
}
