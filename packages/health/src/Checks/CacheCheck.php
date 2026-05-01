<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Checks;

use Dskripchenko\LaravelAdminHealth\HealthCheck;
use Dskripchenko\LaravelAdminHealth\HealthResult;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Round-trip set/get/forget per-store. Detects cache backend availability.
 */
final class CacheCheck implements HealthCheck
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(public readonly array $config = []) {}

    public function id(): string
    {
        return 'cache.stores';
    }

    public function name(): string
    {
        return 'Cache-хранилища';
    }

    public function category(): string
    {
        return 'cache';
    }

    public function frequency(): string
    {
        return (string) ($this->config['frequency'] ?? '5m');
    }

    public function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 5);
    }

    public function run(): HealthResult
    {
        /** @var array<int, mixed> $stores */
        $stores = (array) ($this->config['stores'] ?? [config('cache.default')]);
        $failures = [];
        $checked = [];

        foreach ($stores as $store) {
            if (! is_string($store) || $store === '') {
                continue;
            }
            $checked[] = $store;
            $key = '_admin_health_'.bin2hex(random_bytes(4));
            $value = (string) microtime(true);

            try {
                $cache = Cache::store($store);
                $cache->put($key, $value, 60);
                $read = $cache->get($key);
                $cache->forget($key);

                if ($read !== $value) {
                    $failures[$store] = 'round-trip mismatch';
                }
            } catch (Throwable $e) {
                $failures[$store] = $e->getMessage();
            }
        }

        if ($failures !== []) {
            return HealthResult::failing(
                'Cache недоступен: '.implode(', ', array_keys($failures)),
                ['failures' => $failures, 'checked' => $checked],
            );
        }

        return HealthResult::ok(
            'Все '.count($checked).' store(s) round-trip OK',
            ['checked' => $checked],
        );
    }
}
