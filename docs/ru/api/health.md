# API: Health

Контроллера `health` в API нет — ни в ядре, ни в пакете `dskripchenko/laravel-admin-health`. Состояние системы попадает в панель двумя путями:

1. **Ядро** — общий механизм индикаторов верхней панели: action `system/status` и контракт `Dskripchenko\LaravelAdmin\Status\StatusIndicator`.
2. **Пакет `dskripchenko/laravel-admin-health`** — health-проверки, которые используют этот механизм, плюс ресурс с историей результатов и виджет дашборда. Своих HTTP-маршрутов пакет не добавляет.

> Конвенции — [conventions.md](conventions.md). Описание пакета — [../sister-packs/health.md](../sister-packs/health.md).

---

## Ядро: `system.status`

`GET /api/admin/system/status` — требует аутентификации (`AdminAuth`), отдельного права нет. Полное описание — [system.md](system.md#systemstatus).

Индикатор — класс, реализующий `StatusIndicator`:

```php
use Dskripchenko\LaravelAdmin\Status\StatusIndicator;

final class QueueIndicator implements StatusIndicator
{
    public function key(): string
    {
        return 'app.queue';
    }

    /** @return array{status: 'ok'|'warning'|'error'|'unknown', label: string, detail?: string, url?: string} */
    public function state(): array
    {
        return ['status' => 'warning', 'label' => 'Очередь стоит', 'detail' => 'Нет обработанных задач 10 минут'];
    }
}
```

Регистрация — в `boot()` плагина или хоста: `$admin->statusIndicators([QueueIndicator::class])`. Индикаторы привязаны к панели, в которой их зарегистрировали; `system/status` отдаёт индикаторы текущей панели.

Ответ:

```json
{
  "success": true,
  "payload": {
    "indicators": [
      { "key": "admin.health", "status": "error", "label": "...", "detail": "...", "url": "/r/system-health-results" }
    ]
  }
}
```

Статус вне `ok|warning|error|unknown` превращается в `unknown`; индикатор, бросивший исключение, пропускается. SPA опрашивает action раз в минуту и показывает только индикаторы со статусом не `ok`.

---

## Пакет `dskripchenko/laravel-admin-health`

Плагин `AdminHealthPlugin` при загрузке регистрирует:

| Что | Описание |
|---|---|
| `HealthStatusIndicator` | индикатор с ключом `admin.health` для `system/status` (если `admin-health.topbar_indicator` не выключен). Сводный статус проверок переводится в словарь ядра: `failing` → `error`, `warning` → `warning`, `ok` → `ok`, прочее → `unknown`; `url` ведёт на `/r/system-health-results` |
| `HealthResultResource` | ресурс результатов проверок, slug `system-health-results`, базовое право `admin.system.health`. Доступен через обычные эндпоинты ресурса `/api/admin/system-health-results/{action}` — см. [resources.md](resources.md) |
| `HealthOverviewWidget` | виджет дашборда со сводкой, право `admin.system.health.view` |
| права | группа «Системные»: `admin.system.health.view`, `admin.system.health.run` |

Проверки запускаются не через API, а artisan-командой `admin:health:run` (по расписанию); старые результаты чистит `admin:health:cleanup`.

Шаблоны `HealthSummaryResponse`, `HealthChecksResponse`, `HealthCheckStatusResponse`, `HealthHistoryResponse` в `AdminApiSisterPackSchemas` объявлены только для OpenAPI-документа: ни один action ядра или пакета их не возвращает.
