<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdminHealth\Checks\CacheCheck;
use Dskripchenko\LaravelAdminHealth\Checks\DatabaseConnectionCheck;
use Dskripchenko\LaravelAdminHealth\Checks\DiskSpaceCheck;
use Dskripchenko\LaravelAdminHealth\Checks\QueueCheck;

return [
    /*
    |--------------------------------------------------------------------------
    | Зарегистрированные check'и
    |--------------------------------------------------------------------------
    | Каждый ключ — class-string<HealthCheck>. Значение — config-array,
    | который будет передан в конструктор check'а как `$config`-параметр.
    | Для добавления собственного check'а: реализуйте HealthCheck contract,
    | добавьте class в массив + config'и параметрами.
    */

    'checks' => [
        DatabaseConnectionCheck::class => [
            'connections' => [env('DB_CONNECTION', 'mysql')],
            'frequency' => '1m',
            'timeout' => 5,
        ],

        CacheCheck::class => [
            'stores' => [env('CACHE_STORE', 'redis')],
            'frequency' => '5m',
            'timeout' => 5,
        ],

        QueueCheck::class => [
            'queues' => ['default'],
            'depth_warning' => 100,
            'depth_failing' => 1000,
            'failed_jobs_warn' => 10,
            'frequency' => '1m',
            'timeout' => 5,
        ],

        DiskSpaceCheck::class => [
            'paths' => [storage_path()],
            'warn_below_pct' => 15,
            'fail_below_pct' => 5,
            'frequency' => '5m',
            'timeout' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | TTL истории результатов (дней)
    |--------------------------------------------------------------------------
    | Cleanup-команда удалит записи старше этого порога. Используется
    | `admin:health:cleanup` (запускайте раз в сутки в scheduler).
    */

    'history_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Topbar-индикатор
    |--------------------------------------------------------------------------
    | Если true — UI добавляет в topbar мини-кружок со сводным статусом
    | (на frontend'е реализуется отдельным компонентом).
    */

    'topbar_indicator' => true,
];
