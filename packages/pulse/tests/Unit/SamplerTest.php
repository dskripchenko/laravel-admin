<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests\Unit;

use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Dskripchenko\LaravelAdminPulse\Services\Sampler;
use Dskripchenko\LaravelAdminPulse\Tests\TestCase;
use RuntimeException;

final class SamplerTest extends TestCase
{
    public function test_should_sample_with_rate_one_always_true(): void
    {
        config(['admin-pulse.sample_rate.request' => 1.0]);
        $sampler = new Sampler;
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($sampler->shouldSample('request'));
        }
    }

    public function test_should_sample_with_rate_zero_always_false(): void
    {
        config(['admin-pulse.sample_rate.request' => 0.0]);
        $sampler = new Sampler;
        for ($i = 0; $i < 50; $i++) {
            $this->assertFalse($sampler->shouldSample('request'));
        }
    }

    public function test_record_writes_to_db(): void
    {
        $sampler = new Sampler;
        $sampler->record('request', 'GET /api/test', 42, 'GET', 200, ['ip' => '1.2.3.4']);

        $this->assertSame(1, PulseSample::query()->count());
        $row = PulseSample::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('request', $row->kind);
        $this->assertSame('GET /api/test', $row->key);
        $this->assertSame(42, $row->duration_ms);
        $this->assertSame(200, $row->status_code);
        $this->assertSame(['ip' => '1.2.3.4'], $row->meta);
    }

    public function test_fingerprint_sql_strips_literals(): void
    {
        $sampler = new Sampler;
        $fp = $sampler->fingerprintSql("SELECT * FROM users WHERE id = 42 AND name = 'Alice'");
        $this->assertSame("SELECT * FROM users WHERE id = ? AND name = '?'", $fp);
    }

    public function test_fingerprint_sql_normalizes_whitespace(): void
    {
        $sampler = new Sampler;
        $fp = $sampler->fingerprintSql("SELECT  *  FROM\tusers\nWHERE id = 1");
        $this->assertSame('SELECT * FROM users WHERE id = ?', $fp);
    }

    public function test_fingerprint_exception_stable_for_same_class_message_location(): void
    {
        $sampler = new Sampler;
        $a = new RuntimeException('boom');
        $b = new RuntimeException('boom');
        // Same line/file → same fingerprint
        $this->assertSame(16, strlen($sampler->fingerprintException($a)));
    }
}
