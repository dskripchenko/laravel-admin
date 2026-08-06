# dskripchenko/laravel-admin-health

## 1. Назначение

Health-checks dashboard: периодические проверки состояния системы (БД, cache, queue, disk, redis, scheduler heartbeat) с UI-просмотром, историей и алертами через стандартные Laravel-events. Своя реализация **без** `spatie/laravel-health`.

**Use case:** командам нужен централизованный «зелёный кружок / красная лампа», чтобы быстро понимать здоровье системы; иметь страницу для on-call дежурных.

## 2. Состав

### Контракт

```php
interface HealthCheck
{
    public function id(): string;            // 'database.default'
    public function name(): string;          // 'Соединение с БД'
    public function category(): string;      // 'database' | 'cache' | 'queue' | 'storage' | 'custom'
    public function frequency(): string;     // '1m' | '5m' | '1h'
    public function timeout(): int;          // seconds
    public function run(): HealthResult;     // status (ok|warning|failing) + message + meta
}
```

### Готовые чекеры (в пакете)

- **`DatabaseConnectionCheck`** — `DB::connection($name)->getPdo()` per-connection.
- **`CacheCheck`** — `Cache::store($store)->set/get` round-trip per-store.
- **`QueueCheck`** — длина каждой очереди (warning при превышении threshold), счётчик failed_jobs.
- **`RedisCheck`** — `PING`, info про память и подключения.
- **`DiskSpaceCheck`** — `disk_free_space` per-disk, warning при <10%, failing при <5%.
- **`LogChannelCheck`** — пишет тестовое сообщение в каждый log-канал, проверяет что не падает.
- **`ScheduleHeartbeatCheck`** — последний `heartbeat`-таймстамп от cron, failing если >2× от ожидаемой частоты.
- **`HttpEndpointCheck`** — пингует список внешних URL'ов (для health-check'а зависимых сервисов).
- **`CustomClosureCheck`** — `HealthCheck::closure(fn () => /* ... */)` для произвольных one-off.

### Background runner

Artisan-команда `admin:health:run`:

- читает список из `Admin::healthChecks()`,
- запускает только те, у которых `frequency` указывает на необходимость re-run,
- пишет результаты в `admin_health_results` (`id, check_id, status, message, meta json, duration_ms, ran_at`),
- эмитит Laravel-event `HealthCheckStatusChanged` при переходе ok → failing/warning или обратно.

В scheduler: `$schedule->command('admin:health:run')->everyMinute()->withoutOverlapping()`.

### UI

- **`HealthCheckWidget`** — для dashboard. Компактная сетка `<UiBadge>` (по чекеру), цвет = status. Tooltip с last_message и ran_at.
- **`HealthCheckResource`** — полная страница (view-only). Таблица всех чекеров с last status, история last 24h, кнопка «Прогнать сейчас» (требует `admin.system.health.run` permission).
- **Topbar-индикатор** `<HealthIndicator>` — мини-кружок в шапке (зелёный / оранжевый / красный) с popover на список failing/warning. Виден только пользователям с `admin.system.health.view`.

## 3. Зависимости

**Composer:** `dskripchenko/laravel-admin: ^1.0`. Никаких сторонних.

NPM: нет.

## 4. Миграции

- `create_admin_health_results_table`

## 5. Permissions

```php
ItemPermission::group('Системные')
    ->addPermission('admin.system.health.view', 'Health-check: просмотр')
    ->addPermission('admin.system.health.run',  'Health-check: ручной запуск');
```

## 6. Конфиг

`config/admin-health.php`:

```php
return [
    'checks' => [
        \Dskripchenko\AdminHealth\Checks\DatabaseConnectionCheck::class => [
            'connections' => ['mysql', 'pgsql_reports'],
            'frequency'   => '1m',
        ],
        \Dskripchenko\AdminHealth\Checks\QueueCheck::class => [
            'queues'           => ['default', 'imports', 'notifications'],
            'depth_warning'    => 100,
            'depth_failing'    => 1000,
            'failed_jobs_warn' => 10,
        ],
        \Dskripchenko\AdminHealth\Checks\DiskSpaceCheck::class => [
            'disks'           => ['local', 'public'],
            'warn_below_pct'  => 15,
            'fail_below_pct'  => 5,
        ],
        // ...
    ],

    'history_days' => 7,                              // TTL для admin_health_results
    'topbar_indicator' => true,
];
```

## 7. Подключение

```bash
composer require dskripchenko/laravel-admin-health
php artisan admin:plugin:install health
php artisan migrate

# В app/Console/Kernel.php (или routes/console.php в L11+):
$schedule->command('admin:health:run')->everyMinute()->withoutOverlapping();
$schedule->command('admin:health:cleanup')->daily();
```

## 8. Алерты

Эмитим стандартный Laravel-event `Dskripchenko\AdminHealth\Events\HealthCheckStatusChanged`. Host-проект слушает его через `EventServiceProvider` и шлёт куда нужно (Slack, Telegram, Sentry, PagerDuty).

Пример:

```php
Event::listen(HealthCheckStatusChanged::class, function ($event) {
    if ($event->isFailing()) {
        SlackNotification::send("🚨 {$event->check->name()}: {$event->result->message}");
    }
});
```

## 9. Зачем sister, а не core

- Не каждый проект хочет полноценный health-monitoring — некоторым достаточно `php artisan about`.
- Это +scheduler-задача, которая может перегрузить лёгкий single-server сетап.
- Health-стек (что мониторить, какие пороги) сильно проектно-специфичен.
