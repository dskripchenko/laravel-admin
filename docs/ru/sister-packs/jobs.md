# dskripchenko/laravel-admin-jobs

## 1. Назначение

UI для управления Laravel queue: просмотр failed jobs, batches, queue depth. Retry, forget, cancel-batch.

**Use case:** команда не использует Horizon (или Horizon — это для основного приложения, а в админке нужен view только над failed-jobs); нужно быстро поправить и retry-нуть упавший job без SSH.

## 2. Состав

### Resource'ы

- **`FailedJobResource`** — список из стандартной Laravel-таблицы `failed_jobs`.
  - Колонки: `id, connection, queue, payload (collapsed JSON), exception (collapsed), failed_at`.
  - View-страница: full payload, full exception trace, quick-retry, copy-payload.
  - Bulk: retry, forget.
  - Single: retry, forget.
  - Фильтры: connection, queue, exception_class (fingerprint), failed_at_range, search (в payload или exception).
  - Permission: `admin.system.jobs.failed.view|retry|forget`.

- **`JobBatchResource`** — для `Bus::batch([...])`.
  - Колонки: `id, name, total, pending, processed, failed, progress (bar), created_at, finished_at, cancelled_at`.
  - View-страница: статус, список failed-jobs из batch, кнопки retry-failed, cancel.
  - Permission: `admin.system.jobs.batches.view|manage`.

- **`QueuedJobResource`** (опционально, флаг в config) — текущие jobs в `jobs`-таблице (только для DB queue-driver).
  - Колонки: `id, queue, payload (collapsed), available_at, reserved_at, attempts`.
  - Action: `forget` (delete row) — для совсем застрявших.
  - Для Redis-driver — упрощённый view: только counts через `Queue::size()`.

### Widget

- **`QueueDepthWidget`** — для dashboard. Сетка карточек: каждая очередь с её current depth (pending), reserved, failed counts. Sparkline-история за 1ч (если pulse-pack установлен — берём агрегаты оттуда; иначе — простой polling).

### Notifications (опционально)

Listener на стандартный Laravel-event `JobFailed`:

- шлёт notification в admin notification-center при появлении failed-job;
- rate-limit 1/min на user (чтобы не задолбать при шторме);
- группировка по `exception_fingerprint`.

## 3. Зависимости

**Composer:** `dskripchenko/laravel-admin: ^1.0`. Только Laravel queue API (стандарт).

NPM: нет.

## 4. Миграции

Нет — использует стандартные `failed_jobs` и `job_batches` Laravel.

## 5. Permissions

```php
ItemPermission::group('Системные')
    ->addPermission('admin.system.jobs.failed.view',     'Failed jobs: просмотр')
    ->addPermission('admin.system.jobs.failed.retry',    'Failed jobs: retry')
    ->addPermission('admin.system.jobs.failed.forget',   'Failed jobs: forget')
    ->addPermission('admin.system.jobs.batches.view',    'Batches: просмотр')
    ->addPermission('admin.system.jobs.batches.manage',  'Batches: cancel/retry')
    ->addPermission('admin.system.jobs.queued.view',     'Текущая очередь: просмотр');
```

## 6. Конфиг

`config/admin-jobs.php`:

```php
return [
    'queues_to_monitor' => ['default', 'high', 'low', 'imports'],

    'show_queued' => env('ADMIN_JOBS_SHOW_QUEUED', false),     // true только для DB-driver

    'notification' => [
        'on_failed' => true,
        'rate_limit_per_minute' => 1,
        'group_by_fingerprint'  => true,
    ],

    'payload_truncate' => 5000,                                  // символов в collapsed view
];
```

## 7. Подключение

```bash
composer require dskripchenko/laravel-admin-jobs
php artisan admin:plugin:install jobs
```

## 8. Зачем sister, а не core

- Универсального решения нет — сильно зависит от queue-driver (DB / Redis / SQS / Beanstalk).
- Многие проекты используют Horizon — им сторонний UI не нужен.
- Хочется иметь возможность держать core админки без зависимостей на queue-схему.
