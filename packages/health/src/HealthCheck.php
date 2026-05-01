<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth;

/**
 * Контракт single-check'а.
 *
 * Каждый check декларирует:
 *   - id() — уникальный slug ('database.default', 'queue.imports')
 *   - name() — human-readable («Соединение с БД»)
 *   - category() — для группировки в UI (database / cache / queue / storage / custom)
 *   - frequency() — '1m' | '5m' | '15m' | '1h' — определяет когда runner
 *     должен снова запустить
 *   - timeout() — секунды (runner abort'ит если check висит)
 *   - run() — собственно проверка, возвращает HealthResult.
 *
 * Сами check'и stateless — никаких полей. State (last_run, status) лежит
 * в admin_health_results-таблице, обновляется runner'ом.
 */
interface HealthCheck
{
    public function id(): string;

    public function name(): string;

    public function category(): string;

    public function frequency(): string;

    public function timeout(): int;

    public function run(): HealthResult;
}
