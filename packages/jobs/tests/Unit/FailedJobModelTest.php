<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs\Tests\Unit;

use Dskripchenko\LaravelAdminJobs\Models\FailedJob;
use Dskripchenko\LaravelAdminJobs\Tests\TestCase;

final class FailedJobModelTest extends TestCase
{
    public function test_extracts_exception_class_from_full_text(): void
    {
        $job = new FailedJob([
            'uuid' => 'a',
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => "RuntimeException: connection failed\n#0 /app/Foo.php(42)",
        ]);
        $this->assertSame('RuntimeException', $job->exception_class);
    }

    public function test_extracts_exception_message(): void
    {
        $job = new FailedJob([
            'uuid' => 'a', 'connection' => 'r', 'queue' => 'q', 'payload' => '{}',
            'exception' => 'RuntimeException: connection failed at api.example.com',
        ]);
        $this->assertSame('connection failed at api.example.com', $job->exception_message);
    }

    public function test_returns_empty_message_for_malformed_exception(): void
    {
        $job = new FailedJob([
            'uuid' => 'a', 'connection' => 'r', 'queue' => 'q', 'payload' => '{}',
            'exception' => 'malformed without colon',
        ]);
        $this->assertSame('', $job->exception_message);
    }

    public function test_produces_stable_fingerprint_for_same_exception_head(): void
    {
        $exception = "RuntimeException: foo\n#0 /app/A.php\n#1 /app/B.php\n#2 /app/C.php\n#3 /app/D.php";
        $a = new FailedJob(['uuid' => '1', 'connection' => 'r', 'queue' => 'q', 'payload' => '{}', 'exception' => $exception]);
        $b = new FailedJob(['uuid' => '2', 'connection' => 'r', 'queue' => 'q', 'payload' => '{}', 'exception' => $exception]);
        $this->assertSame($a->exception_fingerprint, $b->exception_fingerprint);
        $this->assertSame(16, strlen($a->exception_fingerprint));
    }

    public function test_decodes_payload(): void
    {
        $payload = json_encode(['displayName' => 'App\\Jobs\\SendEmailJob', 'data' => ['user_id' => 7]]);
        $job = new FailedJob([
            'uuid' => 'a', 'connection' => 'r', 'queue' => 'q',
            'payload' => $payload, 'exception' => 'X',
        ]);
        $this->assertSame('App\\Jobs\\SendEmailJob', $job->jobName());
        $this->assertSame(['user_id' => 7], $job->decodedPayload()['data']);
    }

    public function test_returns_unknown_for_missing_displayname(): void
    {
        $job = new FailedJob([
            'uuid' => 'a', 'connection' => 'r', 'queue' => 'q',
            'payload' => '{}', 'exception' => 'X',
        ]);
        $this->assertSame('Unknown', $job->jobName());
    }
}
