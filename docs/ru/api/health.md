# API: Health

Health-checks dashboard. Реализуется в sister-pack `dskripchenko/laravel-admin-health`. Контракт здесь — чтобы admin core знал, как embed-индикатор в шапке должен общаться с пакетом.

> Полная спецификация — [../sister-packs/health.md](../sister-packs/health.md). Регистрация — [registration.md](registration.md).

URL: `api/admin/health/{action}`.

---

## HealthController (sister-pack)

### Регистрация (в sister-pack'е)

```php
'health' => [
    'controller' => HealthController::class,
    'middleware' => [AdminAuth::class],
    'actions' => [
        'summary' => ['method' => ['get']],
        'checks'  => ['method' => ['get']],
        'run'     => ['method' => ['post']],
        'history' => ['method' => ['get']],
    ],
],
```

---

## Действия

### `health.summary`

```php
/**
 * Агрегированный статус для индикатора в шапке admin.
 *
 * @output object $payload
 * @output string $payload.overall ok|warning|failing.
 * @output object $payload.counts
 * @output integer $payload.counts.ok
 * @output integer $payload.counts.warning
 * @output integer $payload.counts.failing
 * @output string(date-time) $payload.last_run_at
 * @output array  $payload.failing_checks
 * @output string $payload.failing_checks[].id
 * @output string $payload.failing_checks[].name
 * @output string $payload.failing_checks[].message
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {HealthSummaryResponse}
 * @response 403 {ForbiddenErrorResponse} admin.system.health.view.
 */
public function summary(Request $request): JsonResponse;
```

### `health.checks`

```php
/**
 * Полный список чекеров с их статусами.
 *
 * @output object $payload
 * @output array  $payload.checks Список HealthCheckStatus.
 * @output string $payload.checks[].id
 * @output string $payload.checks[].name
 * @output string $payload.checks[].category database|cache|queue|storage|custom.
 * @output string $payload.checks[].status ok|warning|failing.
 * @output string ?$payload.checks[].message
 * @output object $payload.checks[].meta
 * @output string $payload.checks[].frequency 1m|5m|1h.
 * @output string(date-time) $payload.checks[].last_run_at
 * @output integer $payload.checks[].duration_ms
 * @output string(date-time) $payload.last_run_at
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {HealthChecksResponse}
 * @response 403 {ForbiddenErrorResponse} admin.system.health.view.
 */
public function checks(Request $request): JsonResponse;
```

### `health.run`

```php
/**
 * Ручной запуск чекера.
 *
 * @input string $id ID чекера.
 *
 * @output object $payload HealthCheckStatus — свежий результат.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {HealthCheckStatusResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 429 {ThrottledResponse} 5,1 per check id.
 * @response 403 {ForbiddenErrorResponse} admin.system.health.run.
 */
public function run(Request $request): JsonResponse;
```

### `health.history`

```php
/**
 * История запусков одного чекера.
 *
 * @input string $id
 * @input string ?$range 24h|7d|30d (default 24h).
 * @input integer ?$page
 * @input integer ?$per_page
 *
 * @output object $payload
 * @output array  $payload.data
 * @output string(date-time) $payload.data[].ran_at
 * @output string $payload.data[].status ok|warning|failing.
 * @output integer $payload.data[].duration_ms
 * @output string ?$payload.data[].message
 * @output object $payload.meta Пагинация.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {HealthHistoryResponse}
 * @response 403 {ForbiddenErrorResponse}
 */
public function history(Request $request): JsonResponse;
```

---

## Events

Sister-pack эмитит `Dskripchenko\\AdminHealth\\Events\\HealthCheckStatusChanged` при переходе ok ↔ failing/warning. Host-проект слушает для интеграции с Slack/Sentry/PagerDuty.
