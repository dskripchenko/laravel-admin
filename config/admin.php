<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | URL и домен
    |--------------------------------------------------------------------------
    */

    // SPA-shell живёт под `path` (например, /admin/*).
    'path' => env('ADMIN_PATH', 'admin'),
    'domain' => env('ADMIN_DOMAIN'),
    // API живёт ОТДЕЛЬНО от SPA — на /api/admin/* (не нестится под path).
    'api_path' => env('ADMIN_API_PATH', 'api/admin'),

    /*
    |--------------------------------------------------------------------------
    | Auth — multi-guard
    |--------------------------------------------------------------------------
    | strategy: 'dedicated' — собственный guard 'admin' + модель AdminUser
    |           'shared'    — переиспользуем существующий guard host-проекта
    */

    'auth' => [
        'strategy' => env('ADMIN_AUTH_STRATEGY', 'dedicated'),

        'guard' => env('ADMIN_GUARD', 'admin'),
        'provider' => env('ADMIN_PROVIDER', 'admin_users'),
        'model' => Dskripchenko\LaravelAdmin\Models\AdminUser::class,
        'table' => 'admin_users',
        'password_broker' => 'admin_users',

        'login_throttle' => '5,1',

        'two_factor' => [
            'enabled' => true,
            'enforce_for' => [],
            'recovery_codes' => 8,
            'window' => 1,
        ],

        'impersonation' => [
            'enabled' => true,
            'permission' => 'admin.impersonate',
            'block_higher_powered' => true,
        ],

        'api_tokens' => [
            'enabled' => true,
            'rate_limit' => '60,1',
            'default_expiry' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Сессия
    |--------------------------------------------------------------------------
    */

    'session' => [
        'cookie' => env('ADMIN_SESSION_COOKIE'),
        'driver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'shell' => [
            'web',
            Dskripchenko\LaravelAdmin\Http\Middleware\AdminLocale::class,
            Dskripchenko\LaravelAdmin\Http\Middleware\AdminCspNonce::class,
        ],
        'api' => [
            // admin-API сессионный (не stateless) — используем `web` middleware
            // group для StartSession/EncryptCookies/CSRF. Headless Bearer-tokens
            // через Sanctum — фаза P15 (опционально).
            'web',
            Dskripchenko\LaravelAdmin\Http\Middleware\CaptureApiRequest::class,
            Dskripchenko\LaravelAdmin\Http\Middleware\AdminAuth::class,
            Dskripchenko\LaravelAdmin\Http\Middleware\RunActionMiddleware::class,
            Dskripchenko\LaravelAdmin\Http\Middleware\AdminLocale::class,
        ],
        'public' => [
            'web',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bootstrap-стратегия SPA
    |--------------------------------------------------------------------------
    */

    'bootstrap' => [
        'strategy' => env('ADMIN_BOOTSTRAP_STRATEGY', 'inline'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding и UI
    |--------------------------------------------------------------------------
    */

    'brand' => [
        'name' => env('ADMIN_BRAND_NAME', 'Admin'),
        'logo' => null,
        'favicon' => null,
    ],

    'ui' => [
        'default_theme' => 'light',
        'default_locale' => 'ru',
        'available_locales' => ['ru', 'en'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'table' => 'admin_notifications',
        'use_host_table' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

    'audit' => [
        'enabled' => true,
        'table' => 'admin_audit_logs',
        'log_auth_events' => true,
        'excluded_attributes' => ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'],
        'retention_days' => 365,
        'user_agent_max_length' => 1024,
        'url_max_length' => 2048,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination и uploads (defaults)
    |--------------------------------------------------------------------------
    */

    'pagination' => [
        'default_per_page' => 25,
        'max_per_page' => 100,
        'notifications_per_page' => 20,
    ],

    'uploads' => [
        'disk' => env('ADMIN_UPLOADS_DISK', 'local'),
        'directory' => 'uploads',
        'max_kilobytes' => 51200,
        'max_kilobytes_image' => 10240,
    ],

    /*
    |--------------------------------------------------------------------------
    | Экспорт
    |--------------------------------------------------------------------------
    */

    'exports' => [
        'pdf' => [
            'driver' => env('ADMIN_PDF_DRIVER', 'mpdf'),
            'fallback' => 'dompdf',
            'options' => [
                'mpdf' => ['mode' => 'utf-8', 'format' => 'A4'],
                'dompdf' => ['paper' => 'a4', 'orientation' => 'portrait'],
            ],
        ],
        'xlsx' => [
            'driver' => 'openspout',
            'options' => ['memory_limit' => '512M'],
        ],
        'csv' => [
            'delimiter' => ';',
            'enclosure' => '"',
            'bom' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Manifest-кэш
    |--------------------------------------------------------------------------
    */

    'manifest' => [
        'cache_store' => null,
        'etag' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins
    |--------------------------------------------------------------------------
    */

    'plugins' => [
        // \Dskripchenko\LaravelAdminStarter\AdminStarterPlugin::class,
        // \Dskripchenko\LaravelAdminMedia\AdminMediaPlugin::class,
    ],

];
