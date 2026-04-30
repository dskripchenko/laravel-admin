# dskripchenko/laravel-admin-pulse

## 1. Назначение

Лёгкая телеметрия для админ-сценариев: response-times по роутам, top-slowest, slow-queries, dispatched jobs, top-exceptions, cache hit-rate. **Своя минимальная реализация без** `laravel/pulse`.

**Use case:** небольшие команды без выделенного APM (Sentry/NewRelic/DataDog), которым нужна базовая видимость по перформансу из админки.

## 2. Состав

### Сэмплер

`PulseMiddleware` — глобально подключается через `config/admin-pulse.php → middleware.web` и `middleware.api`:

- меряет каждый N-й request (rate=1/N, default 1/10) — отбор `mt_rand()`-based;
- собирает метрики через listeners `DB::listen()`, `Queue::before()/after()`, `ExceptionHandler::reportable()`, `CacheHit/CacheMissed`;
- пишет batch'ом в `admin_pulse_samples` через Laravel-Job (НЕ синхронно — не блокирует request);
- работает в `terminate()` (после ответа клиенту).

### Метрики

- **`Request`** — `route, method, status, duration, memory_peak, user_id, sampled_at`.
- **`Query`** — `connection, sql_fingerprint (template), count_in_request, total_time, sampled_at` (один Query-rec = одна группа однотипных запросов в request).
- **`Job`** — `class, queue, status (ok|failed|released), duration, attempts, dispatched_at, finished_at`.
- **`Exception`** — `class, message_fingerprint, file_line, count, last_seen_at` (с накоплением по fingerprint).
- **`Cache`** — `store, hit_count, miss_count, sampled_at`.

### Агрегация

`AggregatorJob` запускается через `admin:pulse:aggregate` (cron каждые 5 минут):

- читает `admin_pulse_samples` за окно,
- считает p50/p95/p99 по route, top-slowest queries, top exceptions,
- пишет в `admin_pulse_aggregates` (`bucket, period_start, period_end, dimensions json, metrics json`).

### TTL-rotation

- `admin_pulse_samples` — TTL 24 часа (configurable).
- `admin_pulse_aggregates` — TTL 7 дней.
- `admin:pulse:rotate` чистит, ставится в scheduler ежедневно.

### UI

- **Widget'ы** для dashboard:
  - `RequestVolumeWidget` — sparkline количества запросов в минуту за 1ч/24ч.
  - `SlowRoutesWidget` — топ-10 медленных роутов по p95.
  - `SlowQueriesWidget` — топ-10 SQL-запросов по среднему времени.
  - `ExceptionsWidget` — топ-10 исключений по count за 24ч.
  - `JobThroughputWidget` — jobs/min, success rate.
- **`PulseResource`** (view-only) — общий обзор с переключателем интервала (1ч / 24ч / 7д). Каждая метрика — отдельный tab с таблицей и chart'ом.

## 3. Зависимости

**Composer:** `dskripchenko/laravel-admin: ^1.0`. Никаких сторонних.

NPM: нет.

## 4. Миграции

- `create_admin_pulse_samples_table`
- `create_admin_pulse_aggregates_table`

Индексы на `(route, sampled_at)`, `(sql_fingerprint, sampled_at)` и т.п. — для быстрых запросов из UI.

## 5. Permissions

```php
ItemPermission::group('Системные')
    ->addPermission('admin.system.pulse.view', 'Телеметрия: просмотр');
```

## 6. Конфиг

`config/admin-pulse.php`:

```php
return [
    'enabled' => env('ADMIN_PULSE_ENABLED', true),

    'sample_rate' => [
        'request'   => 0.1,                       // 10% запросов
        'query'     => 0.1,
        'job'       => 1.0,                       // jobs дешёвые — пишем все
        'exception' => 1.0,                       // exceptions всегда
        'cache'     => 0.05,                      // cache дорого — 5%
    ],

    'middleware' => [
        'web' => true,
        'api' => true,
    ],

    'ignore_routes' => [
        '/admin/api/v1/pulse/*',                  // не сэмплировать самих себя
        '/health',
        '/_debugbar/*',
    ],

    'retention' => [
        'samples_hours'    => 24,
        'aggregates_days'  => 7,
    ],

    'fingerprint' => [
        'sql_strip_values' => true,               // SELECT * FROM users WHERE id = ? (не 42)
    ],
];
```

## 7. Подключение

```bash
composer require dskripchenko/laravel-admin-pulse
php artisan admin:plugin:install pulse
php artisan migrate

# В scheduler:
$schedule->command('admin:pulse:aggregate')->everyFiveMinutes();
$schedule->command('admin:pulse:rotate')->daily();
```

## 8. Перформанс-соображения

- Сэмплер пропускает `/admin/api/v1/pulse/*` (избегаем рекурсии).
- Запись samples — через job, не блокирует request.
- При высокой нагрузке снизить `sample_rate.request` до 0.01 (1%).
- Можно отключить per-environment через `ADMIN_PULSE_ENABLED=false` в `.env`.

## 9. Зачем sister, а не core

- +2 таблицы, +middleware на все запросы, +фоновые задачи.
- Многие проекты используют Sentry/NewRelic/DataDog/Tempo — pulse им не нужен.
- Сэмплер потенциально критичен для перформанса — должен быть осознанным выбором.
