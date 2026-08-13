<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The URL and the domain
    |--------------------------------------------------------------------------
    */

    // The SPA shell lives under `path`, /admin/* for instance.
    'path' => env('ADMIN_PATH', 'admin'),
    'domain' => env('ADMIN_DOMAIN'),
    // The API lives SEPARATELY from the SPA, at /api/admin/*; it does not nest under path.
    'api_path' => env('ADMIN_API_PATH', 'api/admin'),

    'api' => [
        // The admin API's global per-user rate limit: 'requests,minutes'.
        'throttle' => env('ADMIN_API_THROTTLE', '240,1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auth — multi-guard
    |--------------------------------------------------------------------------
    | strategy: 'dedicated' — an 'admin' guard of our own plus the AdminUser
    |                        model
    |           'shared'    — we reuse the host project's existing guard
    */

    'auth' => [
        'strategy' => env('ADMIN_AUTH_STRATEGY', 'dedicated'),

        'guard' => env('ADMIN_GUARD', 'admin'),
        'provider' => env('ADMIN_PROVIDER', 'admin_users'),
        'model' => Dskripchenko\LaravelAdmin\Models\AdminUser::class,
        'table' => 'admin_users',
        'password_broker' => 'admin_users',

        'login_throttle' => env('ADMIN_LOGIN_THROTTLE', '5,1'),

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
    | The session
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
            // The admin API is session-based rather than stateless, so it
            // uses the `web` middleware group for StartSession,
            // EncryptCookies and CSRF. Headless bearer tokens through Sanctum
            // are optional and come later.
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
    | The SPA bootstrap strategy
    |--------------------------------------------------------------------------
    */

    'bootstrap' => [
        'strategy' => env('ADMIN_BOOTSTRAP_STRATEGY', 'inline'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding and the UI
    |--------------------------------------------------------------------------
    */

    'brand' => [
        'name' => env('ADMIN_BRAND_NAME', 'Admin'),
        // The logo image's URL, used by the sidebar and the login, forgot and reset pages.
        'logo' => env('ADMIN_BRAND_LOGO'),
        // A short textual mark of one or two characters, used when there is no logo.
        'mark' => env('ADMIN_BRAND_MARK'),
        'favicon' => env('ADMIN_BRAND_FAVICON'),
        // The copyright line in the panel's footer; null leaves the footer empty.
        'copyright' => env('ADMIN_BRAND_COPYRIGHT'),
        'footer' => null,
    ],

    /*
    | The installation banner: a short announcement above the panel.
    |
    | It is drawn by the shell rather than by the application, and that is
    | deliberate. An announcement of this kind is a property of the
    | INSTALLATION, not of a screen: "this is a demo stand", "the data is
    | wiped", "a migration is under way". It must be visible even when the SPA
    | has failed to come up — those are exactly the moments when a person most
    | needs to understand where they have landed.
    |
    | When `text` is empty there is no banner at all and no markup is printed.
    |
    | `countdown_to` is the moment in time (ISO-8601) the countdown runs to. The
    | value is dynamic, so it cannot be put into the config directly: a cached
    | config would freeze it forever. The host application sets it in the middle
    | of a request — through a middleware, for instance.
    */
    'notice' => [
        'text' => env('ADMIN_NOTICE'),
        'href' => env('ADMIN_NOTICE_HREF'),
        'countdown_to' => null,
        // The caption next to the countdown: "until the reset", "until it ends" and the like.
        'countdown_label' => env('ADMIN_NOTICE_COUNTDOWN_LABEL'),
    ],

    'ui' => [
        'default_theme' => 'light',
        'default_locale' => 'ru',
        'available_locales' => ['ru', 'en'],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI / Scalar
    |--------------------------------------------------------------------------
    | `scalar_script` is the URL of the Scalar bundle. An external CDN
    | (jsdelivr) by default; a host project may set a local self-hosted path for
    | environments with no access to a CDN
    | (`/vendor/scalar/api-reference.js`).
    */
    'openapi' => [
        'ui' => env('ADMIN_OPENAPI_UI', 'scalar'),
        'scalar_theme' => 'default',
        'scalar_script' => env('ADMIN_SCALAR_SCRIPT', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference'),
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
        // Attributes whose changes are stripped from the diff snapshot.
        // Default: credentials/secrets + bookkeeping timestamps that fire on
        // every save and would clutter the timeline ("Changed: updated_at
        // 12:00:01 → 12:00:02"). Hosts can override this list via env or
        // per-model by overriding getAuditExcluded(): array.
        'excluded_attributes' => [
            // Secrets / tokens.
            'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
            // Auto-managed timestamps.
            'created_at', 'updated_at', 'deleted_at',
            // Auth bookkeeping written by login / impersonation flows.
            'last_login_at', 'last_seen_at', 'current_team_id',
        ],
        // When an update event survives `excluded_attributes` with nothing
        // left to record, skip writing the audit row entirely instead of
        // leaving an empty "Changed" entry in the timeline.
        'skip_empty_updates' => true,
        'retention_days' => 365,
        'user_agent_max_length' => 1024,
        'url_max_length' => 2048,
        // Human-readable labels for actor_type and subject_type: FQCN → label.
        // An FQCN means nothing to an operator, so the host writes
        // "Administrator" and the like here. A class outside the map falls back
        // to the reverse morph-map alias, and then to class_basename.
        //   App\Models\ClientUser::class => 'Client user',
        'type_labels' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles resource (system-roles)
    |--------------------------------------------------------------------------
    */

    'roles' => [
        // The slug prefixes of the roles that are NOT shown and NOT editable
        // in the service list of system roles — neither in the list nor
        // through a direct edit URL. Useful so that roles of another domain
        // (the client-side `client-*` ones, say) do not mix with the admin
        // roles. Nothing is hidden by default.
        'hidden_slug_prefixes' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination and uploads (the defaults)
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
        // The whitelist of disks the admin may stream through
        // /api/admin/uploads/serve. It solves previewing files on private
        // disks, where there is no storage:link. A host adds its own disks
        // here explicitly.
        'servable_disks' => [env('ADMIN_UPLOADS_DISK', 'local'), 'public'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Export
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
    | The manifest cache
    |--------------------------------------------------------------------------
    */

    'manifest' => [
        'cache_store' => null,
        'etag' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | SPA frontend assets
    |--------------------------------------------------------------------------
    |
    | The host project builds the SPA bundle with Vite and states its paths in
    | one of two ways:
    |
    | 1. An explicit list (the minimum):
    |     'assets' => [
    |         'css' => ['/build/admin.css'],
    |         'js'  => ['/build/admin.js'],
    |     ]
    |
    | 2. A Vite manifest (resolved automatically through `public/build/manifest.json`):
    |     'assets' => [
    |         'vite_manifest' => public_path('build/manifest.json'),
    |         'vite_entry'    => 'resources/js/admin.js',
    |         'vite_base_url' => '/build/',
    |     ]
    |
    | ShellController picks the mode itself, by the presence of the `vite_manifest` key.
    */

    'assets' => [
        'css' => [],
        'js' => [],
        'vite_manifest' => null,
        'vite_entry' => null,
        'vite_base_url' => '/build/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Panels (v1.8) — the additional panels
    |--------------------------------------------------------------------------
    | Every panel is an independent surface: its own mount path ('' meaning
    | the site's root), its own guard and provider, its own laravel-api version
    | (/api/{id}/...), its own middleware stacks and its own set of plugins. The
    | top-level keys of this config form the implicit default panel `admin`.
    |
    | 'panels' => [
    |     'client' => [
    |         'path' => '',                       // the mount prefix ('' = the root)
    |         'exclude_prefixes' => ['api', 'admin'], // do not swallow other paths
    |         'auth' => [
    |             'strategy' => 'dedicated',
    |             'guard' => 'client',
    |             'provider' => 'client_users',
    |             'model' => App\Models\ClientUser::class,
    |             'table' => 'client_users',
    |             'password_broker' => 'client_users',
    |         ],
    |         'api' => App\Admin\ClientApi::class, // extends Panel\PanelApi
    |         'middleware' => [
    |             'shell' => ['web', AdminLocale::class, AdminCspNonce::class],
    |             // api — ADDITIONS to the shared base stack
    |             // admin.middleware.api (which is panel-aware: the guard is
    |             // resolved from the request's panel)
    |             'api' => [SomePanelMiddleware::class],
    |         ],
    |         'plugins' => [App\Admin\ClientPanelPlugin::class],
    |     ],
    | ],
    */
    'panels' => [],

    'plugins' => [
        // \Dskripchenko\LaravelAdminStarter\AdminStarterPlugin::class,
        // \Dskripchenko\LaravelAdminMedia\AdminMediaPlugin::class,
    ],

];
