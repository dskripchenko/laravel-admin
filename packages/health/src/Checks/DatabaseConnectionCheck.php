<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Checks;

use Dskripchenko\LaravelAdminHealth\HealthCheck;
use Dskripchenko\LaravelAdminHealth\HealthResult;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Проверяет каждое DB-подключение из config['connections'] через попытку
 * получить PDO-инстанс. Failing если хотя бы одно недоступно.
 */
final class DatabaseConnectionCheck implements HealthCheck
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(public readonly array $config = []) {}

    public function id(): string
    {
        return 'database.connections';
    }

    public function name(): string
    {
        return 'Соединения с БД';
    }

    public function category(): string
    {
        return 'database';
    }

    public function frequency(): string
    {
        return (string) ($this->config['frequency'] ?? '1m');
    }

    public function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 5);
    }

    public function run(): HealthResult
    {
        /** @var array<int, mixed> $connections */
        $connections = (array) ($this->config['connections'] ?? [config('database.default')]);
        $failures = [];
        $checked = [];

        foreach ($connections as $connection) {
            if (! is_string($connection) || $connection === '') {
                continue;
            }
            $checked[] = $connection;
            try {
                DB::connection($connection)->getPdo();
            } catch (Throwable $e) {
                $failures[$connection] = $e->getMessage();
            }
        }

        if ($failures !== []) {
            return HealthResult::failing(
                'Недоступны connection(s): '.implode(', ', array_keys($failures)),
                ['failures' => $failures, 'checked' => $checked],
            );
        }

        return HealthResult::ok(
            'Все '.count($checked).' соединение(й) активны',
            ['checked' => $checked],
        );
    }
}
