<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Очереди для мониторинга
    |--------------------------------------------------------------------------
    | Список имён очередей для QueueDepthWidget. Должны существовать в queue
    | driver host-проекта. Пустой = автоопределение через `Queue::getNames()`
    | (если поддерживается driver'ом).
    */

    'queues_to_monitor' => ['default'],

    /*
    |--------------------------------------------------------------------------
    | Показывать резерв очереди (queued jobs)
    |--------------------------------------------------------------------------
    | true только для DB queue-driver (`jobs`-таблица). Для Redis/SQS — не
    | работает (driver не предоставляет per-job listing). Для DB-driver
    | даёт `QueuedJobResource` со списком pending-задач.
    */

    'show_queued' => env('ADMIN_JOBS_SHOW_QUEUED', false),

    /*
    |--------------------------------------------------------------------------
    | Notification on failed job
    |--------------------------------------------------------------------------
    | Listener на стандартный Laravel-event JobFailed (см. AdminJobsPlugin).
    | Шлёт notification в admin notification-center при появлении failed-job.
    */

    'notification' => [
        'on_failed' => env('ADMIN_JOBS_NOTIFY_ON_FAILED', true),
        'rate_limit_per_minute' => 1,
        'group_by_fingerprint' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Truncate payload в collapsed-view
    |--------------------------------------------------------------------------
    | Длина в символах для preview-rendering payload/exception в таблице.
    | Full payload viewer'ом доступен на view-странице.
    */

    'payload_truncate' => 5000,
];
