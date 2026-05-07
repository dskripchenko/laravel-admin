# Dashboard widgets

Виджеты dashboard'а — два слоя:

- **Backend** (`Widget` PHP-класс): `widgetType()` (имя типа), `data()` (payload), опц. `slug()`, `title()`, `size()`, `permission()`. Сериализуется в `manifest.dashboards[*].widgets[]`.
- **Frontend** (Vue-компонент): получает props `{ title, size, data, type }`, рендерит. Регистрируется в `dashboard registry` под именем `widgetType()`.

## Встроенные типы

| Backend `widgetType()` | Frontend (alias)             | Назначение                         |
|-------------------------|------------------------------|------------------------------------|
| `stats`                 | `stat`, `stats`              | Карточка с числом + тренд          |
| `chart`                 | `chart` (диспетчер)          | Bar / Line / Donut / Pie           |
| `recent_list`           | `recent-table`, `recent-list`| Таблица последних записей          |
| `heatmap`               | `heatmap`                    | Тепловая карта                     |
| `gauge`                 | `gauge`                      | Полукруг с порогами                |
| `markdown`              | `markdown`                   | Markdown-заметка                   |
| `iframe`                | (todo)                       | Embedded URL                       |
| `table`                 | (todo)                       | Произвольная таблица               |

## Регистрация widget'а на DashboardScreen

```php
namespace App\Admin\Screens;

use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Dskripchenko\LaravelAdmin\Widget\ChartWidget;

final class ContentDashboardScreen extends DashboardScreen
{
    public static function slug(): string { return 'content'; }

    public function widgets(): array
    {
        return [
            (new class extends StatsOverviewWidget {
                public static function slug(): string { return 'content.total'; }
            })
                ->title('Всего статей')
                ->size(3)
                ->stat('TOTAL', \App\Models\Article::count())
                ->trend(12.4, 'up'),

            (new class extends ChartWidget {
                public static function slug(): string { return 'content.daily'; }
            })
                ->title('Публикации по дням')
                ->size(8)
                ->chartType('bar')
                ->labels(['1 май', '2 май', '3 май'])
                ->dataset('Опубликовано', [3, 5, 2], '#10b981'),
        ];
    }
}
```

Регистрируем в plugin'е:

```php
// app/Admin/DemoPlugin.php
public function boot(\Dskripchenko\LaravelAdmin\Admin $admin): void
{
    $admin->screen([ContentDashboardScreen::class]);
}
```

> **Зачем `new class extends`?** Widget классы используют `slug()` как уникальный
> идентификатор для сохранения per-user layout (DashboardLayout). Один и тот же
> базовый класс на одном экране даст конфликт slug'ов — анонимный subclass с
> переопределённым `slug()` решает это без создания десятков именованных классов.

## Кастомный widget с нуля

### 1. Backend — Widget class

```php
namespace App\Admin\Widgets;

use Dskripchenko\LaravelAdmin\Widget\Widget;

class WeatherWidget extends Widget
{
    private string $city = 'Moscow';

    public function widgetType(): string
    {
        return 'weather';
    }

    public function city(string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function data(): array
    {
        // Ходим в свой service, кешируем, возвращаем shape для frontend.
        return [
            'city' => $this->city,
            'temperature' => app(WeatherService::class)->current($this->city),
            'condition' => 'sunny',
        ];
    }
}
```

### 2. Frontend — Vue-компонент

```vue
<!-- resources/js/widgets/WeatherWidget.vue -->
<script setup lang="ts">
interface WeatherData {
  city: string
  temperature: number
  condition: string
}
interface Props {
  title?: string
  data?: WeatherData
}
defineProps<Props>()
</script>

<template>
  <UidCard padding="md">
    <h3>{{ title ?? 'Погода' }}</h3>
    <p>{{ data?.city }}: {{ data?.temperature }}°C ({{ data?.condition }})</p>
  </UidCard>
</template>
```

### 3. Регистрация на frontend

В `admin.js` (host):

```js
import { createAdminApp, registerWidget } from '@dskripchenko/laravel-admin'
import WeatherWidget from './widgets/WeatherWidget.vue'

const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__, {
    onAppCreated: () => {
        // ВАЖНО: регистрируем после createAdminApp() — иначе
        // registerBuiltinWidgets() внутри фабрики перезатрёт.
        registerWidget('weather', WeatherWidget)
    },
})
app.mount('#admin-app')
```

### 4. Использование в DashboardScreen

```php
public function widgets(): array
{
    return [
        (new class extends \App\Admin\Widgets\WeatherWidget {
            public static function slug(): string { return 'home.weather'; }
        })
            ->title('Погода в офисе')
            ->size(4)
            ->city('Saint-Petersburg'),
    ];
}
```

## Per-user layout (drag/resize/add)

В edit-mode пользователь:

- **Перетаскивает** widget'ы для пересортировки (HTML5 drag, handle `☰`).
- **Изменяет размер** через `↘`-handle (span 1..12).
- **Удаляет** через `×` (для backend-widget — выставляется `hidden: true` override; для user-added — удаляется полностью).
- **Настраивает** через `⚙` (сейчас только title; per-type editor — TODO).
- **Добавляет** свой widget через `+ Add widget` — выбирает frontend-type, заполняет `title` + `size` + minimal config.

При сохранении — `POST /api/admin/dashboard/save` с body:

```json
{
  "key": "content",
  "widgets": [
    {"slug": "content.total", "size": 6, "position": 0},
    {"slug": "content.daily", "size": 6, "position": 1, "hidden": true},
    {"slug": "custom.markdown.1719312000", "size": 6, "position": 2,
     "type": "markdown", "config": {"title": "Заметка", "content": "..."}}
  ]
}
```

Backend хранит в `admin_dashboard_layouts.widgets` (JSON), per-user через
`owner_type` + `owner_id`. На отдаче frontend накладывает persisted layout
поверх manifest-декларации (см. `DashboardPage.vue` `renderedWidgets`).

## API расширения

- `Widget::permission(string|array)` — скрыть widget от пользователя без прав.
- `Widget::canSee(callable)` — runtime-условие.
- `Widget::refresh(int $seconds)` — auto-refresh (frontend пока не реализован).
- `Widget::size(int $cols)` — span 1..12 (default 12 = full width).
