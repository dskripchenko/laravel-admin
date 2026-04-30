# dskripchenko/laravel-admin — архитектурный документ (черновик v0.4)

Спецификация с зафиксированными решениями. Все 17 открытых вопросов закрыты, сводка — в разделе 0.

---

## 0. Сводка зафиксированных решений

| # | Решение |
|---|---|
| Backbone | Полностью собственная реализация. Чужие админ-фреймворки в зависимостях не используются |
| Зависимости | `laravel/framework ^12` + `dskripchenko/laravel-api ^4.2` + `laravel-translatable ^2.0` + `laravel-delayed-process ^2.0` + `php-array-helper ^1.1` |
| PHP / Laravel | PHP `^8.5`, Laravel `^12` (без поддержки L11) |
| Namespace | `Dskripchenko\LaravelAdmin\` |
| Auth | Multi-guard в core. Default — собственный guard `admin` + модель `AdminUser` + таблица `admin_users`. Path/domain/guard/provider/middleware конфигурируются. Можно подменить на существующий guard host-проекта |
| Audit-trail | Внутри admin (`src/Audit/`), не отдельный пакет |
| WYSIWYG | Tiptap в core (peerDependency `@tiptap/vue-3`, `@tiptap/starter-kit`). TinyMCE/Quill — опциональные sister-пакеты |
| File-storage | Собственная таблица `admin_attachments` + `Storage::disk()`. Расширения — sister-пакет `laravel-admin-media` |
| Глобальный поиск | Sister-пакет `laravel-admin-search` (Scout-обёртка), не в core |
| Тема | Light/dark в core поверх токенов `@dskripchenko/ui` + переключатель в шапке + persist `localStorage`/cookie. Host-проект оверрайдит CSS-переменные |
| Frontend-state | Pinia. Сторы: `auth`, `manifest`, `permissions`, `menu`, `locale`, `theme`, `alerts` |
| Translatable cache | Soft fallback: при отсутствии tag-aware кэша кэширование переводов отключается, `admin:doctor` показывает рекомендацию |
| Тесты Resource | Без автогенерации фабрик. `ResourceTestCase` фокусируется на admin-специфике |
| Версионирование API | Семвер admin = семвер API. `/admin/api/v1` — внутренний контракт core↔SPA, без обещаний обратной совместимости |
| Manifest-кэш | Etag + version (гибрид). Кэш-ключ `admin:manifest:{version}:{locale}` |
| OpenAPI / docs | Встроенный Swagger UI на `/admin/api/docs` (lazy-loaded), пермишен `admin.system.api-docs` |
| Bootstrap-стратегия | Конфиг `bootstrap.strategy`: `inline` (default, с CSP-nonce) или `xhr` |
| Telemetry | Только Laravel-events (`Admin\Events\*`), без своего observer-API |

---

## 1. Манифест (фиксированные тезисы)

Зафиксированы по входному заданию, далее всё проектирование подчиняется этим инвариантам:

1. **Лёгкая интеграция в любой Laravel-проект.** Подключение в существующий проект — `composer require` + одна artisan-команда `admin:install`. Никаких требований к структуре проекта, namespace, моделям пользователя или гварду. Мы аккуратно встраиваемся, не ломаем existing routes/middleware/auth.
2. **CRUD конфигурируется просто, кастомизация — гибкая.** Описание типового CRUD-раздела укладывается в один `Resource`-класс на 30–80 строк. При этом любой слой (запрос, валидация, форма, layout, экшены, права, фильтры, экспорт) переопределяется точечно — без переписывания всего раздела.
3. **Это конструктор, а не готовая админка.** Не ставим целью запустить «админку под ключ из коробки». Поставляем строительные блоки (Resource, Screen, Layout, Field, Action, Filter, Policy, AuditTrail) и инфраструктуру (роутинг, SPA, права, фоновые задачи). Готовых разделов «Пользователи/Роли/Настройки» в core нет — они идут отдельным **starter-pack** пакетом и подключаются опционально.
4. **RBAC из коробки.** Группированные пермишены (`ItemPermission::group('Системы')->addPermission('admin.systems.users', 'Пользователи')`), привязка пермишена к Screen/Resource/Action, страница ролей и страница пользователей с матрицей чекбоксов — всё это часть core. Под капотом — собственный движок RBAC поверх Eloquent.
5. **Типовые шаблоны страниц для CRUD.** В пакете готовые скелеты `ListScreen`, `EditScreen`, `CreateScreen`, `ViewScreen`, `BulkActionScreen` — наследуются Resource'ом и кастомизируются.
6. **Редактирование связанных данных.** Поддержка `BelongsTo`, `HasMany`, `BelongsToMany`, `MorphMany`, полиморфных композиций. Виджеты `RelationSelect`, `RelationTable` (inline-редактирование вложенных сущностей в таблице на форме родителя), `MorphSwitcher` (выбор подтипа + динамическая форма). Динамические сущности (полиморфные блоки контента) — first-class.
7. **SPA. Без перезагрузок.** Frontend — Vue 3 SPA на `@dskripchenko/ui`. Навигация, формы, фильтры, модалки — всё через JSON-API (`dskripchenko/laravel-api`). Никакого Turbo/Inertia: чистый client-side router + REST-эндпоинты в стандартном конверте `{success, payload}`.
8. **Полный набор виджетов.** Input, Number, Textarea, Password, Select (одиночный/множественный/async), Combobox, Radio, Checkbox, Switch, DatePicker, DateRange, TimePicker, ColorPicker, Slider, Rating, FileUpload, Code, WYSIWYG (адаптерами: Tiptap/Quill/TinyMCE), TagsInput, TreeSelect, Cascader, RelationSelect, RelationTable (вложенные списки), Tabs, Accordion, Group (репитер), Modal, Drawer, Toast — на уровне core.
9. **Минимум внешних зависимостей.** Только `laravel/framework`, собственные `dskripchenko/*` пакеты и неизбежные транзитивные зависимости. Никаких сторонних админ-фреймворков, `spatie/laravel-permission`, `spatie/laravel-medialibrary`, `laravel/scout`, breadcrumbs-пакетов. Всё, чего нет среди наших, либо пишем сами, либо выносим в **опциональный sister-пакет**, не подключаемый по умолчанию.
10. **Security/UX-базлайн в core.** 2FA (TOTP), профиль администратора, user impersonation с audit-следом и баннером, notification center (bell + drawer + database-notifications), first-class soft-delete (фильтры + restore/force-delete), prompt «несохранённых изменений» при уходе со страницы.

---

## 2. Стек и версионные требования

| Слой | Стек |
|---|---|
| PHP | **^8.5** (минимум — диктуется `dskripchenko/laravel-delayed-process` 2.0) |
| Laravel | **^12** (минимум — диктуется delayed-process; translatable требует ^11/^12) |
| БД | PostgreSQL (рекомендуется), MySQL 8+, MariaDB |
| Cache | Redis/Memcached (нужен tag-aware кэш для `laravel-translatable`) |
| Queue | любой Laravel-driver (нужен для `delayed-process`) |
| Frontend runtime | Vue 3.4+, TypeScript, Vite |
| UI-kit | `@dskripchenko/ui` (70+ компонентов, CSS-переменные) |
| Сборка SPA | Vite 5+, опубликованный конфиг можно переиспользовать в host-проекте |

---

## 3. Зависимости

### 3.1. Принцип: только свой код

Кодовая база admin-пакета — полностью собственная. Никаких сторонних админ-фреймворков в дереве зависимостей. Все механики (Screen, Layout, Field, Filter, Action, Permission, Audit, Menu) реализуются внутри пакета.

### 3.2. Стек собственных пакетов (используются)

| Пакет | Версия | Роль в laravel-admin | PHP/Laravel |
|---|---|---|---|
| `dskripchenko/laravel-api` | ^4.2 | Транспортный слой: `BaseApi` / `BaseModule` для версионирования, `CrudController` + `CrudService` + `Meta` под Resource, конверт `{success, payload}`, OpenAPI 3.0 из docblock-тегов `@input`/`@output`, генерация TS-типов для SPA. | PHP 8.1+, Laravel 6–12 |
| `dskripchenko/laravel-translatable` | ^2.0 | i18n: `TranslationTrait` для моделей, `ContentBlockService` для CMS-блоков, DB-loader для `__()`/`trans()`. Бэкенд для виджета `TranslatableInput`. | PHP 8.1+, Laravel 11/12, tag-aware cache |
| `dskripchenko/php-array-helper` | ^1.1 | Утилиты: `array_merge_deep` для слияния конфигов и манифеста, `array_get_signature` для cache-ключей, dot-доступ. | PHP 8.1+, без зависимостей |
| `dskripchenko/laravel-delayed-process` | ^2.0 | Фоновые операции (импорт/экспорт, массовые экшены, отчёты). Серверная часть: `ProcessFactoryInterface`, allowlist в `config/delayed-process.php`. JS-модуль (`applyAxiosInterceptor`, `BatchPoller`) — встраивается прямо в SPA HTTP-клиент. | **PHP ^8.5, Laravel ^12** — задаёт минимум стеку |
| `@dskripchenko/ui` (npm) | latest | Frontend UI-kit. Vue 3 + TypeScript, 70+ компонентов, CSS-переменные. Поверх него — наши SPA-обёртки `LayoutRenderer`, `FieldRenderer`. | Vue ^3.4 |

### 3.3. Внешние зависимости — короткий список

Composer-зависимости admin-пакета (минимально необходимое):

```
"require": {
    "php": "^8.5",
    "laravel/framework": "^12.0",
    "dskripchenko/laravel-api": "^4.2",
    "dskripchenko/laravel-translatable": "^2.0",
    "dskripchenko/laravel-delayed-process": "^2.0",
    "dskripchenko/php-array-helper": "^1.1",
    "ext-json": "*"
}
```

NPM `peerDependencies` SPA-бандла:

```
"peerDependencies": {
    "vue": "^3.4",
    "vue-router": "^4.3",
    "pinia": "^2.1",
    "axios": "^1.6",
    "@dskripchenko/ui": "*",
    "@tiptap/vue-3": "^2.4",
    "@tiptap/starter-kit": "^2.4",
    "marked": "^12.0"
}
```

(Tiptap — единственное исключение из принципа «минимум зависимостей» на фронте. Включён в core ради работающего «из коробки» WYSIWYG.)

Всё остальное — опциональные sister-пакеты:

- `dskripchenko/laravel-admin-starter` — готовые Resource (Users, Roles, AuditLog, Settings, Translations).
- `dskripchenko/laravel-admin-tiptap` / `*-tinymce` / `*-quill` — WYSIWYG-адаптеры (один WYSIWYG в core не выбираем — пользователь ставит нужный).
- `dskripchenko/laravel-loggable` — audit-trail (если вынесем отдельно, см. п.13.1).
- `dskripchenko/laravel-admin-search` — глобальный поиск (Scout-обёртка опционально).

### 3.4. Ключевые архитектурные принципы

- **SPA-first.** Frontend — Vue 3 SPA на `@dskripchenko/ui`. Серверный Blade рендерит только пустую оболочку приложения. Никаких HTML-фрагментов в API-ответах.
- **JSON-only транспорт.** Все обновления — через JSON-API в стандартном конверте `dskripchenko/laravel-api` (`{success, payload}`).
- **Resource как первоклассный примитив.** Один класс описывает list+form+filters+permissions, автоматически генерирует API-эндпоинты и схему UI.
- **Декларативная реактивность на уровне Field** (`->reactive()->reloadFor([...])`) — без отдельного слоя «слушателей».
- **Async-обновления одним пакетом.** Единый JSON-ответ `{layouts: {id: schema}, alerts, commandBar}` обновляет несколько частей UI за один XHR.
- **Composer-friendly RBAC.** Роли и пермишены — собственная Eloquent-таблица, без сторонних пакетов прав.

---

## 4. Слои и поток данных

```
┌────────────────────────────────────────────────────────────────┐
│  Browser SPA  (Vue 3 + @dskripchenko/ui)                       │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Router → Screen renderer → Layout/Field components       │ │
│  │            ↑ JSON state         ↓ actions                 │ │
│  └──────────────────────────────────────────────────────────┘  │
│  HTTP client (axios + applyAxiosInterceptor)                   │
└──────────┬─────────────────────────────────────────────────────┘
           │ JSON: {success, payload}  /  delayed envelope
┌──────────▼─────────────────────────────────────────────────────┐
│  laravel-api: BaseApi / BaseModule / CrudController            │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  AdminApiModule (v1)                                      │ │
│  │   ├── ResourceController  (по одному на Resource)         │ │
│  │   ├── ScreenController    (custom screens)                │ │
│  │   ├── ActionController    (массовые экшены)               │ │
│  │   ├── UploadController    (файлы)                         │ │
│  │   ├── DelayedController   (статус фоновых задач)          │ │
│  │   └── SystemController    (whoami, menu, locales, perms)  │ │
│  └──────────────────────────────────────────────────────────┘  │
│  Middleware: ApiMiddleware, AdminAuth, AdminAccess, Locale     │
└──────────┬─────────────────────────────────────────────────────┘
           │
┌──────────▼─────────────────────────────────────────────────────┐
│  Admin core                                                    │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Resource → ResourceCompiler → {ListScreen, EditScreen}   │ │
│  │  Screen / Layout / Field / Action                         │ │
│  │  Filter / TableColumn                                     │ │
│  │  Permission registry / RoleManager                        │ │
│  │  Menu / Breadcrumbs / Alert / Search                      │ │
│  └──────────────────────────────────────────────────────────┘  │
└──────────┬─────────────────────────────────────────────────────┘
           │
┌──────────▼─────────────────────────────────────────────────────┐
│  Собственные пакеты-зависимости                                │
│  • laravel-api          (BaseApi/Module, Crud, OpenAPI, конверт)│
│  • laravel-translatable (TranslationTrait, ContentBlockService) │
│  • laravel-delayed-process (фоновые экшены, JS-interceptor)     │
│  • php-array-helper     (deep merge / dot-notation / signature) │
│  • [optional] laravel-loggable (audit-trail, см. п.13.1)        │
└────────────────────────────────────────────────────────────────┘
```

> Всё, что в схеме обозначено как Admin core (Screen/Layout/Field/Action/Filter/Permission/Audit/Menu), реализуется внутри пакета `laravel-admin` собственным кодом.

**Ключевая идея:** SPA общается с бэкендом строго через JSON-API в формате `laravel-api`. Никаких HTML-фрагментов в ответах, никакого dual-rendering.

---

## 5. Ключевые концепции

### 5.1. Resource — точка входа в CRUD

Резюме: один класс описывает раздел админки целиком. Это сахар над Screen-ами и единственное, что нужно знать новичку.

```php
namespace App\Admin\Resources;

use Dskripchenko\LaravelAdmin\Resource;
use Dskripchenko\LaravelAdmin\Field;
use Dskripchenko\LaravelAdmin\Action;
use Dskripchenko\LaravelAdmin\Filter;
use Dskripchenko\LaravelAdmin\TableColumn as TC;

final class UserResource extends Resource
{
    public static string $model = \App\Models\User::class;
    public static string $icon  = 'user';
    public static string $group = 'Системы';

    public static function permission(): string
    {
        return 'admin.systems.users';
    }

    public function fields(): array
    {
        return [
            Field\Input::make('name')->title('Имя')->required(),
            Field\Input::make('email')->type('email')->required()->unique(),
            Field\Password::make('password')->onCreate()->required(),
            Field\Select::make('role_id')->fromModel(Role::class, 'name')->title('Роль'),
            Field\Switch::make('is_active')->title('Активен'),
            Field\RelationTable::make('addresses')         // HasMany inline-редактор
                ->fields([
                    Field\Input::make('city'),
                    Field\Input::make('zip'),
                ]),
        ];
    }

    public function columns(): array
    {
        return [
            TC::make('id')->sort(),
            TC::make('name')->sort()->search(),
            TC::make('email')->copyable(),
            TC::make('is_active')->as('badge'),
            TC::make('created_at')->date(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter\Input::for('email'),
            Filter\Switcher::for('is_active'),
            Filter\DateRange::for('created_at'),
        ];
    }

    public function actions(): array
    {
        return [
            Action\Bulk::make('activate')->confirm()->handle(fn($ids) => /* ... */),
            Action\Export::csv(),
        ];
    }
}
```

Регистрация:

```php
// config/admin.php или AppServiceProvider
Admin::resources([
    UserResource::class,
    OrderResource::class,
]);
```

`ResourceCompiler` на старте приложения:

1. Регистрирует пермишены в `PermissionRegistry`.
2. Создаёт три виртуальных Screen-а (`{Resource}\ListScreen`, `EditScreen`, `CreateScreen`) — наследников собственных `BaseListScreen` / `BaseEditScreen` (см. `src/Screen/`).
3. Регистрирует роуты в `AdminApiModule` через `laravel-api` (`/admin/api/v1/resources/{slug}/...`).
4. Добавляет пункт меню (`menu` + `group`).
5. Генерирует JSON-схему layout/fields/columns/filters → отдаёт SPA через `SystemController::manifest()`.

### 5.2. Screen — для нестандартных страниц

Когда Resource не подходит (дашборды, мастера, отчёты), пишется Screen вручную:

```php
final class DashboardScreen extends Screen
{
    public function name(): string       { return 'Дашборд'; }
    public function permission(): array  { return ['admin.dashboard']; }
    public function query(): array       { return ['stats' => $this->stats()]; }
    public function commandBar(): array  { return [Action\Button::make('Обновить')->method('refresh')]; }
    public function layout(): array
    {
        return [
            Layout::metrics(['Всего' => 'stats.total', 'Активных' => 'stats.active']),
            Layout::columns([
                Layout::chart(SalesChart::class),
                Layout::view('admin::widgets.recent', 'stats'),
            ]),
        ];
    }
    public function refresh() { return back(); }
}
```

Регистрация: `Admin::screen('dashboard', DashboardScreen::class)` либо стандартный `Route::screen()`.

### 5.3. Layout

Композитные сериализуемые объекты. SPA знает, как отрисовать каждый тип:

| Layout | Роль | Vue-component |
|---|---|---|
| `Rows` | форма из полей | `<UiForm>` + per-field |
| `Columns` | горизонтальное деление | CSS-grid с пропами |
| `Tabs` | табы | `<UiTabs>` |
| `Accordion` | складные секции | `<UiAccordion>` |
| `Modal` | модалка с собственным state | `<UiModal>` + lazy fetch |
| `Drawer` | боковая панель | `<UiDrawer>` |
| `Block` | карточка с заголовком/CTA | `<UiCard>` + slots |
| `Table` | таблица из `TableColumn` | `<UiTable>` |
| `Metrics` | KPI-карточки | `<UiStat>` сетка |
| `Chart` | график | `<UiSparkline>`/`<UiGauge>` |
| `View` | произвольный Vue-компонент по имени | dynamic `component :is` |
| `Wrapper` | произвольная вёрстка со слотами | scoped slot контракт |

Сериализация: каждый Layout превращается в `{type, props, children}` JSON-узел. SPA-`LayoutRenderer` рекурсивно рисует дерево.

### 5.4. Field

Базовый класс с `__call → attributes[]`. Каждый field несёт:

- `name` (dot-notation, поддержка `addresses.0.city`),
- `value` (резолвится из state по name),
- `validation` (строкой Laravel-rules или массивом, экспортируется в SPA как `{rules, messages}`),
- `visibility` (`canSee`, `onCreate`, `onUpdate`, `onView`),
- `widget` (имя Vue-компонента + props).

Полный список в п.8.

### 5.5. Action

Унифицированная модель действий (используется в `commandBar`, заголовке таблицы, ячейках, bulk):

```php
Action\Button::make('Сохранить')->method('save')->icon('check')->primary();
Action\Link::make('Открыть на сайте')->href(...)->target('_blank');
Action\DropDown::make('Ещё')->items([...]);
Action\Modal::make('Изменить пароль')->modal(ChangePasswordModal::class);
Action\Bulk::make('Удалить')->confirm('Удалить N записей?')->handle(fn($ids) => ...);
Action\Async::make('Импорт')->process(ImportService::class, 'run'); // через delayed-process
```

`Action::method(...)` маппится на метод Screen/Resource. На клиенте кнопка делает POST на `/api/v1/.../action/{name}` со сложенным state формы и параметрами.

### 5.6. Filter

Иерархия фильтров в `src/Filter/`: абстрактный `Filter` + базовые типы (`InputFilter`, `SwitcherFilter`, `DateRangeFilter`, `SelectFromModelFilter`, `SelectFromQueryFilter`, `SelectFromOptionsFilter`).

Сахар-фабрики: `Filter\Input::for('email')`, `Filter\Switcher::for('is_active')`, `Filter\DateRange::for('created_at')`, `Filter\SelectFromModel::for('role_id', Role::class, 'name')`.

URL-driven (`?filter[email]=...&sort=-created_at`) — встраиваемся в схему `CrudController` из `laravel-api` (он уже парсит `filter`/`sort` query-параметры), наша часть — лишь сборка builder-цепочки на основе списка фильтров Resource.

### 5.7. Permission / RoleManager

```php
// config/admin.php → Admin::permissions()
ItemPermission::group('Системы')
    ->addPermission('admin.systems.users', 'Пользователи')
    ->addPermission('admin.systems.roles', 'Роли');
```

В core поставляем:

- модель `Role` (id, name, slug, permissions: JSON);
- pivot `model_has_roles` (morph: можно вешать роли не только на User);
- trait `HasAdminAccess` для модели пользователя (`user()->hasAccess('admin.systems.users')`);
- middleware `admin.access:permission.key`;
- `PermissionRegistry` (singleton) — собирает группы и плоский список ключей для матрицы UI.

Resource автоматически регистрирует свой permission (если задан). Action может требовать собственный sub-permission (`->permission('admin.users.delete')`).

### 5.8. Audit (свой trail)

Реализация — внутренний модуль `src/Audit/`.

- Trait `Loggable` (наш) подключается к моделям — слушатели Eloquent-событий (`created`/`updated`/`deleted`/`restored`) пишут запись в таблицу `admin_audit_logs` (`id, user_id, user_type, subject_type, subject_id, event, attributes (json), old (json), new (json), ip, user_agent, created_at`).
- Морф-карта берётся из стандартной Laravel-конфигурации (`Relation::morphMap`), плюс наши allowlist/blocklist полей в `config/admin-audit.php`.
- Resource по умолчанию добавляет вкладку **«История»** на EditScreen, рендерит `<AuditTimeline>` поверх `<UiTimeline>` из `@dskripchenko/ui`.
- Раздел «Журнал аудита» (фильтры по пользователю/сущности/событию) — отдельный Resource в **starter-pack**, не в core.
- Точки расширения: контракт `AuditableEvent`, фабрика `AuditEntryFactory` (можно подменять формат записей), листенеры `LogAuthEvents` (login/logout/failed) включаются опцией.

### 5.9. Delayed actions (фоновые операции)

`Action\Async::make('export')->process(ExportService::class, 'csv')` под капотом:

1. Вызывает `ProcessFactoryInterface::make(ExportService::class, 'csv', ...$args)`.
2. API возвращает `{success, payload: { delayed: { uuid, status: 'new' } } }`.
3. `applyAxiosInterceptor` в SPA автоматически поллит `/admin/api/v1/system/delayed/status?uuid=...` до `done`/`failed`.
4. Виджет `<UiToast>` показывает прогресс; по завершении — финальный payload (например, ссылка на скачивание).

Allowlist процессов (требование `delayed-process`) — наш ServiceProvider автоматически собирает все `Action\Async`-handlers и пушит их в `config/delayed-process.php` runtime-merge через `array_merge_deep` (php-array-helper).

### 5.10. Локализация (laravel-translatable)

- Модель → `TranslationTrait` + `protected $translatable = ['title', 'body']`. Field автоматически рендерится в `<TranslatableInput>` (вкладки по локалям).
- UI-строки админки идут через DB-loader `__('admin::menu.users')`; редактор переводов — отдельный Resource в starter-pack.
- Content-блоки (`ContentBlockService`) — для CMS-разделов host-проекта, в core не интегрируем, но даём пример Resource.

### 5.11. Профиль администратора, 2FA, impersonation, notifications

**Profile screen** (`Auth\Screens\ProfileScreen`) — доступна всем авторизованным, без отдельного permission. Содержимое: имя, email, пароль, аватар, локаль интерфейса, тема, 2FA-setup, API-tokens (если включён Sanctum). Открывается из user-меню в шапке.

**2FA (TOTP)** — `config/admin.php → auth.two_factor`:

```php
'two_factor' => [
    'enabled'        => true,            // включает поля и UI
    'enforce_for'    => ['admin.*'],     // обязательно для пермишенов из этого списка (wildcard)
    'recovery_codes' => 8,               // сколько одноразовых кодов выдать
    'window'         => 1,               // допуск ±N окон по 30s
],
```

Реализация: модуль `src/Auth/TwoFactor/` (TOTP generator, QR-код, recovery codes), миграция добавляет `two_factor_secret` (encrypted), `two_factor_recovery_codes` (encrypted json), `two_factor_confirmed_at` к `admin_users`. На login flow — middleware `RequireTwoFactor`, отдельная страница ввода кода. Зависимостей не добавляем — TOTP реализуем сами (≈100 строк, RFC 6238).

**Impersonation** — `Action\Impersonate::make()` на `UserResource` (или любой Resource с auth-моделью). Под капотом:

- Middleware `AdminImpersonating` сохраняет `impersonator_id` в сессии, подменяет `auth()->user()`.
- В шапке SPA — баннер `<UiAlert>` «Вы вошли как X. [Вернуться]».
- Audit-лог пишет `impersonation.started` / `impersonation.stopped` с обоими user_id.
- Permission `admin.impersonate`. Запрет на impersonate пользователя с большими правами (configurable).
- Опция `config/admin.php → auth.impersonation.enabled` для полного выключения.

**Notification center**:

- Бэкенд: `Illuminate\Notifications\Notifiable` на `AdminUser`, своя миграция `admin_notifications` (или переиспользование стандартной `notifications` host-проекта — флаг в конфиге).
- API: `/admin/api/v1/system/notifications` (list, mark-read, mark-all-read, delete).
- UI: компонент `<NotificationBell>` в шапке с unread-счётчиком; drawer `<NotificationCenter>` с списком (тип, иконка, текст, время, ссылка `action_url`).
- Эмитим стандартные Laravel-events на отправку — host-проект может слушать.
- Toast-нотификации (транзиентные, через `payload.alerts`) — отдельный механизм, см. п.7.

**API-токены администратора** (вкладка в ProfileScreen, видна при установленном Sanctum):

- Зависимость: `laravel/sanctum` — `composer suggest`. Если не установлен — вкладка не появляется, ошибок нет.
- Бэкенд: `Auth\Controllers\TokenController` на `/admin/api/v1/profile/tokens` (list/create/revoke/regenerate). `AdminUser` подключает `HasApiTokens` trait при наличии Sanctum.
- UI: вкладка «API-токены» в `ProfileScreen`. Создание токена в модалке: name, abilities (subset из admin permissions либо `['*']`), `expires_at`. Секрет показывается **один раз** при создании, копируется в clipboard.
- Список: name, abilities (chips), `last_used_at`, `created_at`, `expires_at`, actions Revoke / Regenerate.
- Audit: события `token.created` / `token.revoked` / `token.first_used` / `token.expired` пишутся в audit-log с привязкой к user_id.
- Опциональный per-token rate-limit (`config/admin.php → auth.api_tokens.rate_limit`).

Use cases: CI/деплой-скрипты, мобильные приложения, интеграции с CRM/маркетплейсами, автоматизация по cron — всё через стандартный admin API с `Authorization: Bearer ...`.

### 5.12. Soft-delete first-class

`Resource::softDeletes(true)` — единственный нужный флаг (если модель использует `SoftDeletes`-trait, флаг может быть авто-true). Включает:

- В фильтр-баре переключатель **`[Активные | Удалённые | Все]`** — применяет соответствующий scope (`whereNull('deleted_at')` / `onlyTrashed()` / `withTrashed()`).
- Bulk + row actions: `Action\Restore`, `Action\ForceDelete`. Force-delete требует отдельный permission `<resource>.force-delete` (по умолчанию только суперюзер).
- ListScreen видит trashed-записи, Edit-форма для trashed открывается в read-only-режиме с CTA «Восстановить».
- Audit фиксирует события `deleted` / `restored` / `force_deleted`.

### 5.13. Unsaved changes prompt

`Resource::warnOnUnsavedChanges()` (default — `true` для edit/create-страниц).

- На SPA-роутере — `beforeRouteLeave` guard, если форма «грязная» (любое поле отличается от исходного state).
- Показывает `<UiModal>` с тремя действиями: «Сохранить и уйти» (если форма валидна), «Уйти без сохранения», «Остаться».
- Также ловит закрытие вкладки через `beforeunload`.
- Можно отключить per-Resource или per-Field (`Field\Hidden` etc. не считаются грязными).

### 5.14. Расширенные возможности таблиц

**Inline-edit ячейки** — `TableColumn::make('is_active')->editable()`. Поддерживается для текстовых ячеек, Switch и Select. Изменение делает PATCH на `/resources/{slug}/{id}` с одним полем. Доступ — стандартный `<resource>.update`. Edit-handle появляется при наведении (карандаш-icon).

**Saved views** — каждый пользователь сохраняет текущую комбинацию `filter + sort + columns` как именованный view. Хранится в `admin_user_views (id, admin_user_id, resource_slug, name, payload json, is_shared, created_at)`. В шапке таблицы — dropdown с personal-view'ами + shared-view'ами других пользователей (если `is_shared = true`). По умолчанию пакет создаёт view «Все» (без фильтров).

**Column visibility / reorder per user** — toggle-меню в шапке (`<UiTable>` уже умеет, наша обёртка persist'ит state). Хранится в `admin_users.preferences` (json) по ключу `tables.{resource_slug}.columns`. Resource задаёт дефолт через `TableColumn::make(...)->defaultHidden()` / порядок в `columns()`.

**Summarizers (footer-агрегаты)** — `TableColumn::make('amount')->summary(['sum', 'avg', 'count', 'range'])`. Сервер считает агрегаты одним SQL-запросом с учётом активных фильтров (НЕ пагинации — агрегат по всему набору). Рендерится как sticky footer-row.

**Группировка** — `Resource::groupable(['status', 'category_id'])` + опциональный dropdown «Группировать по» в шапке. Сервер возвращает данные с `group_key`-маркерами; SPA рисует сворачиваемые группы со счётчиком в заголовке. Сортировка внутри группы — стандартная.

**Polling** — две точки:

- `Resource::poll('30s')` — авто-refresh таблицы (re-fetch list с теми же фильтрами).
- `Field::poll('10s')` — для async-полей (например, `status` у long-running task), single-field GET с заменой значения.

На клиенте: `setInterval` с exponential backoff при ошибках, авто-стоп при `visibilitychange` (вкладка скрыта).

### 5.15. Связанные данные

| Сценарий | Виджет | Бэкенд |
|---|---|---|
| `BelongsTo` (выбор существующей записи) | `Field\Select` с `->fromModel()` или `->async('/search?q=')` | `?q=` поиск через CrudService |
| `HasMany` inline (заказ → позиции) | `Field\RelationTable` | nested validation + sync на save |
| `HasMany` отдельной страницей | `Layout::tabs(['Адреса' => AddressTabLayout::class])` с собственным Resource внутри | child-resource |
| `BelongsToMany` | `Field\TagsInput` либо `Field\TreeSelect` (категории) | sync(...) |
| `MorphMany` (комментарии) | `Field\RelationTable` + `morphRelation()` | автоматический морф |
| Полиморфные блоки (динамические сущности) | `Field\Repeater` + `Field\MorphSwitcher` | type → конкретная схема полей |

«Динамические сущности»: на форме статьи можно собрать массив блоков `[{type:'text', ...}, {type:'image', ...}, {type:'gallery', ...}]`, каждому блоку соответствует свой набор Field — определяется через `MorphSwitcher::types([TextBlock::class, ImageBlock::class, GalleryBlock::class])`.

### 5.16. Infolist / ViewScreen

Третий режим отображения Resource (рядом с list и edit) — read-only показ одной записи. Включается `Resource::view()`.

Своя иерархия `Entry` в `src/Infolist/`: `TextEntry`, `BadgeEntry`, `IconEntry`, `ColorEntry`, `KeyValueEntry`, `RepeatableEntry`, `ImageEntry`, `RelationEntry`, `MapEntry`. API напоминает Field, но рендер read-only.

```php
public function infolist(): array
{
    return [
        Layout::columns([
            Layout::block('Контакт', [
                TextEntry::make('name')->label('Имя'),
                TextEntry::make('email')->copyable(),
                BadgeEntry::make('status')->colors(['active' => 'success', 'banned' => 'danger']),
            ]),
            Layout::block('Адрес', [
                TextEntry::make('addresses.0.city'),
                TextEntry::make('addresses.0.zip'),
            ]),
        ]),
        Layout::tabs([
            'История'  => AuditTrailLayout::class,
            'Заказы'   => RepeatableEntry::make('orders')->fields([...]),
        ]),
    ];
}
```

Поддерживает экспорт в PDF (см. раздел 5.x по экспортам) — рендер ViewScreen в PDF-ready HTML без участия конкретной формы.

### 5.17. Widget + Dashboard

**Widget** — самостоятельный визуальный блок с собственным state и refresh-логикой. Базовый `Widget` + готовые типы:

- `StatsOverviewWidget` — сетка KPI-карточек (`['Всего' => 1234, 'Сегодня' => 12]`).
- `ChartWidget` — линейный/столбчатый/донат график.
- `RecentListWidget` — список последних N записей какого-либо Resource.
- `TableWidget` — мини-таблица.
- `HeatmapWidget`, `GaugeWidget`, `MarkdownWidget`, `IframeWidget`.

Каждый Widget описывает: `query()`, `polling`, `permission()`, `colSpan()`, `view()`. Регистрируется `Admin::widgets([SalesWidget::class, ...])`.

**Dashboard** — page нового типа: статичный (определён в коде) или **user-customizable** (пользователь добавляет/убирает widgets через UI и сохраняет layout в `admin_user_dashboards`). Может быть несколько dashboard'ов; первый — `/` админки.

```php
class SalesDashboard extends Dashboard
{
    public string $path  = 'sales';
    public string $title = 'Продажи';
    public string $permission = 'admin.dashboards.sales';

    public function widgets(): array
    {
        return [
            RevenueWidget::class,
            TopProductsWidget::class,
            RecentOrdersWidget::class,
        ];
    }
}
```

### 5.18. Replicate и Reorder

**Replicate** — `Action\Replicate::make()` на Resource или строке таблицы. Конфигурация на уровне Resource:

```php
public function replicable(): array
{
    return [
        'except' => ['slug', 'sku', 'created_at'],
        'rename' => fn ($model) => $model->name . ' (копия)',
    ];
}
```

Под капотом — Eloquent `replicate($except)` + `save()` + копирование has-many-связей по флагу.

**Reorder** — `Resource::reorderable('position')`. Включает:

- режим «Сортировка» в шапке таблицы (toggle), отключает фильтры/пагинацию, показывает drag-handle;
- batch-PATCH `/resources/{slug}/reorder` с `[{id, position}]`;
- сервер апдейтит в одной транзакции через `CASE WHEN id = ? THEN ?` (одно UPDATE-statement).

### 5.19. Wizard layout

`Layout::wizard([Step::class, ...])` — мастер из нескольких шагов. Каждый Step описывает:

- `title()`, `description()`, `icon()`;
- `fields(): array` (тот же API что у `Rows`);
- `validate(array $state): array` (правила только для своих полей);
- `canEnter(array $state): bool` (можно ли активировать шаг).

UI: `<UiStepper>` сверху, текущий шаг в `<UiCard>`, кнопки Prev/Next/Finish. Финальный submit идёт с aggregated state всех шагов. Используется в onboarding, сложных Create-сценариях, мастере импорта (см. раздел E).

### 5.20. Settings-page

Singleton-Resource нового типа: `SettingsResource extends Resource`. Описывает одну запись (нет list, нет create, нет delete). Открывается на одном маршруте, форма привязана к key/value-источнику.

```php
class GeneralSettings extends SettingsResource
{
    public static string $group = 'Системные';
    public static string $title = 'Общие настройки';

    public static function permission(): string { return 'admin.settings.general'; }

    // источник: либо собственная Eloquent-модель, либо встроенный admin_settings
    public function storage(): SettingsStorage
    {
        return SettingsStorage::eloquent(GeneralSettingsModel::class);
        // либо: SettingsStorage::keyValue(table: 'admin_settings', group: 'general');
    }

    public function fields(): array
    {
        return [
            Layout::tabs([
                'Бренд' => Rows::make([
                    Field\Input::make('site_name'),
                    Field\ImageCropper::make('logo')->aspectRatio(3/1),
                    Field\ColorPicker::make('primary_color'),
                ]),
                'SMTP' => Rows::make([
                    Field\Input::make('mail_host'),
                    Field\Number::make('mail_port'),
                    Field\Password::make('mail_password'),
                ]),
            ]),
        ];
    }
}
```

`SettingsStorage` — два готовых драйвера + контракт для пользовательских:

- `eloquent(Model)` — вся форма мапится на одну Eloquent-модель (одна запись, JSON-колонка опционально);
- `keyValue(table, group)` — каждое поле = строка `(key, value, type)` в `admin_settings`, типизированный cast.

### 5.21. Экспорт и импорт

#### Экспорт

`Action\Export::make(['csv', 'xlsx', 'pdf'])` или per-resource `Resource::exports([...])`. Поддерживаемые форматы:

- **CSV** — встроенно, без зависимостей. Стримится через `php://output`.
- **XLSX** — через `openspout/openspout` (легче `phpoffice/phpspreadsheet`, поддержка стримящего записи). `composer suggest`.
- **PDF** — через контракт `PdfRenderer` с двумя готовыми адаптерами, оба под `composer suggest`:
  - `MpdfRenderer` (default) — лучше для кириллицы, тяжёлых таблиц, headers/footers/page-numbers, watermarks. **Лицензия `mpdf/mpdf` — GPL-2.0-only**, в установочной документации явно предупреждаем.
  - `DompdfRenderer` — LGPL-2.1, лучше для современного CSS, проще лицензионно.
  - `custom` — свой адаптер (например, поверх browsershot/Chrome для идеального fidelity) через тот же контракт.

```php
// config/admin.php
'exports' => [
    'pdf' => [
        'driver'   => env('ADMIN_PDF_DRIVER', 'mpdf'),
        'fallback' => 'dompdf',                // если основной не установлен
        'options'  => [
            'mpdf'   => ['mode' => 'utf-8', 'format' => 'A4'],
            'dompdf' => ['paper' => 'a4'],
        ],
    ],
    'xlsx' => [
        'driver'  => 'openspout',
        'options' => ['memory_limit' => '512M'],
    ],
    'csv' => [
        'delimiter' => ';',                    // RU-friendly default
        'enclosure' => '"',
        'bom'       => true,                   // для совместимости с Excel
    ],
],
```

Runtime-проверка наличия класса с понятной ошибкой `MissingExportDriverException` (со ссылкой на `composer require ...`) если выбран driver, который не установлен.

PDF для ViewScreen/Infolist рендерится через серверный Blade `pdf-shell.blade.php` + сериализация Infolist в HTML без JS (используется на сервере, не SPA).

#### Импорт-мастер

`Resource::importable()` включает 4-шаговый импорт-мастер на базе `Layout::wizard`:

1. **Загрузка файла + auto-detect** (CSV/XLSX). Парсер берётся из тех же `openspout`/встроенного.
2. **Маппинг колонок**: для каждой колонки исходного файла выбираем целевое поле Resource. Auto-suggest по совпадению имён (с нормализацией: lowercase, snake_case, fuzzy-match).
3. **Preview первых N строк** с подсветкой ошибок валидации (rules берутся из `fields()`). Опции: «пропустить ошибочные», «остановиться на первой ошибке», «обновлять существующие по ключу X» (upsert).
4. **Запуск**: dispatch в `delayed-process` через специальный `ImportProcess` (handler в allowlist auto-merge'ится). Прогресс показывается через `<UiProgress>`. По завершении — отчёт: imported / updated / failed + ссылка на скачивание `errors.csv` со строками-ошибками.

### 5.22. Multi-tenancy hooks

Полную модель multi-tenancy admin **не реализует** — нет универсального решения (DB-per-tenant / column-based / schema-based — у каждого проекта свой выбор). Вместо этого даём расширяемые hooks, которые SaaS-проект подключает к своей tenancy-инфраструктуре (например, поверх `stancl/tenancy` или ручной реализации).

**Контракты:**

```php
interface TenantResolver
{
    public function current(): ?Tenant;            // null = single-tenant
    public function switch(Tenant $tenant): void;
    public function availableFor(AdminUser $user): Collection;
}

interface Tenant
{
    public function getKey(): mixed;
    public function getName(): string;
}
```

**Resource-side:**

```php
trait TenantScoped
{
    public function tenantScope(Builder $query, Tenant $tenant): Builder
    {
        return $query->where('tenant_id', $tenant->getKey());
    }
}
```

Resource подключает trait + переопределяет `tenantScope()` если нужна другая стратегия (schema/connection switching). admin автоматически применяет scope к `query()` ListScreen и к `find()` EditScreen, если `TenantResolver::current()` не null.

**UI-side:**

- Если `TenantResolver` зарегистрирован и `availableFor()` возвращает >1 — в шапке появляется `<TenantSwitcher>` (dropdown).
- Текущий tenant пишется в `<ImpersonationBanner>`-style индикатор (если включена опция).
- Permissions могут быть tenant-scoped — `RoleManager::roleFor(AdminUser, Tenant)` (опциональный API).

**Что admin НЕ делает:**

- не создаёт миграцию `tenants`, не диктует структуру.
- не подменяет database-connection (это делает host-проект через middleware tenancy-пакета).
- не предписывает file-storage prefix (host-проект сам конфигурирует disk per tenant).

Рецепты для типовых tenancy-стеков — в `docs/recipes/multi-tenancy-stancl.md`, `multi-tenancy-column.md`, `multi-tenancy-schema.md` (опубликуем по мере набора практики).

### 5.23. Plugin contract

Контракт `AdminPlugin`:

```php
interface AdminPlugin
{
    public function id(): string;
    public function version(): string;
    public function register(Admin $admin): void;   // добавляем Resource/Widget/Field/Action
    public function boot(): void;                   // после полной регистрации
    public function migrations(): array;            // массив путей к миграциям пакета
    public function assets(): array;                // ассеты для admin:link
    public function permissions(): array;           // ItemPermission-группы
    public function menu(): array;                  // пункты меню по умолчанию
}
```

Регистрация: `Admin::plugin(MyPlugin::class)` в `AdminServiceProvider`. Plugin'ы инициализируются в порядке регистрации, могут зависеть друг от друга через `requires(): array`.

Все наши sister-packs реализуются как Plugin'ы:

- `AdminMediaPlugin` — расширенная медиа-библиотека.
- `AdminSearchPlugin` — глобальный поиск через Scout.
- `AdminTinymcePlugin` — альтернативный WYSIWYG-движок.
- `AdminStarterPlugin` — Users/Roles/AuditLog/Translations/Settings Resource'ы.

Plugin'ы видны в `/admin/api/v1/system/plugins` (для отладки) и пишут версии в Swagger UI.

---

## 6. Структура пакета

```
dskripchenko/laravel-admin/
├── composer.json
├── package.json                    # сборка SPA-bundle (Vite)
├── README.md
├── docs/
│   ├── ARCHITECTURE.md             # этот документ
│   ├── getting-started.md
│   ├── resources.md
│   ├── screens.md
│   ├── fields.md
│   ├── actions.md
│   ├── filters.md
│   ├── permissions.md
│   ├── i18n.md
│   ├── audit.md
│   ├── delayed-actions.md
│   ├── customization.md
│   └── recipes/                    # cookbook
│       ├── nested-relations.md
│       ├── polymorphic-blocks.md
│       └── custom-field.md
│
├── config/
│   ├── admin.php                   # prefix, domain, guard, middleware, menu order
│   ├── admin-permissions.php       # стартовый список групп/ключей
│   └── admin-ui.php                # темы, локали, размеры таблиц, упаковка SPA
│
├── routes/
│   └── admin.php                   # SPA-shell route + API-prefix proxy
│
├── database/migrations/
│   ├── 2026_01_01_000001_create_admin_users_table.php       # публикуется только при auth=dedicated
│   ├── 2026_01_01_000002_add_two_factor_columns_to_admin_users.php
│   ├── 2026_01_01_000003_create_admin_password_resets_table.php
│   ├── 2026_01_01_000004_create_admin_roles_table.php
│   ├── 2026_01_01_000005_create_admin_role_assignments_table.php  # morph: модель ↔ роли
│   ├── 2026_01_01_000006_create_admin_audit_logs_table.php
│   ├── 2026_01_01_000007_create_admin_notifications_table.php
│   ├── 2026_01_01_000008_create_admin_attachments_table.php
│   ├── 2026_01_01_000009_create_admin_user_views_table.php   # saved views per user
│   ├── 2026_01_01_000010_create_admin_user_dashboards_table.php  # custom dashboards per user
│   └── 2026_01_01_000011_create_admin_settings_table.php   # key/value для UI-опций
│
├── resources/
│   ├── views/
│   │   ├── shell.blade.php         # пустая HTML-оболочка SPA (#app + bootstrap data)
│   │   └── components/             # Blade-компоненты ТОЛЬКО для server-side (login fallback)
│   ├── lang/
│   │   ├── ru/admin.php
│   │   └── en/admin.php
│   ├── ts/                         # SPA-исходники
│   │   ├── main.ts
│   │   ├── router/
│   │   ├── api/                    # http client + interceptors (delayed-process)
│   │   ├── screens/                # ListScreen, EditScreen, CreateScreen, BulkScreen
│   │   ├── layouts/                # LayoutRenderer + per-type
│   │   ├── fields/                 # FieldRenderer + per-widget wrappers поверх @dskripchenko/ui
│   │   ├── actions/
│   │   ├── filters/
│   │   ├── stores/                 # Pinia: auth, menu, permissions, locale, alerts, theme
│   │   ├── i18n/
│   │   └── theme/
│   │       ├── tokens.css          # admin-уровень переменных поверх @dskripchenko/ui
│   │       ├── light.css
│   │       ├── dark.css
│   │       └── ThemeSwitcher.vue   # переключатель в шапке, persist localStorage+cookie
│   └── stubs/                      # для artisan-генераторов
│       ├── resource.stub
│       ├── screen.stub
│       ├── layout-rows.stub
│       └── field.stub
│
├── public/                         # пусто; собранный SPA уезжает в публикуемые ассеты
│
├── src/
│   ├── AdminServiceProvider.php
│   ├── Facades/Admin.php
│   │
│   ├── Models/
│   │   └── AdminUser.php               # default; публикуется при auth=dedicated
│   │
│   ├── Auth/
│   │   ├── AdminGuardRegistrar.php     # Auth::extend без правки config/auth.php
│   │   ├── Controllers/LoginController.php
│   │   ├── Controllers/PasswordResetController.php
│   │   ├── Controllers/EmailVerificationController.php
│   │   ├── Controllers/TwoFactorController.php       # enable/confirm/disable/recovery
│   │   ├── Controllers/ImpersonationController.php   # start/stop
│   │   ├── Controllers/ProfileController.php
│   │   ├── Controllers/TokenController.php           # API-токены (опц., при Sanctum)
│   │   ├── Middleware/RequireTwoFactor.php
│   │   ├── Middleware/AdminImpersonating.php
│   │   ├── Notifications/ResetPasswordNotification.php
│   │   ├── TwoFactor/
│   │   │   ├── TotpGenerator.php       # RFC 6238, без внешних зависимостей
│   │   │   ├── QrCodeRenderer.php      # SVG inline, без imagick/gd
│   │   │   └── RecoveryCodes.php
│   │   └── Screens/ProfileScreen.php
│   │
│   ├── Console/                    # artisan
│   │   ├── InstallCommand.php          # admin:install
│   │   ├── PublishCommand.php          # admin:publish (views/config/assets)
│   │   ├── MakeResourceCommand.php     # admin:make-resource
│   │   ├── MakeScreenCommand.php       # admin:make-screen
│   │   ├── MakeLayoutCommand.php       # admin:make-layout
│   │   ├── MakeFieldCommand.php        # admin:make-field
│   │   ├── MakeFilterCommand.php       # admin:make-filter
│   │   ├── MakeAdminCommand.php        # admin:user (создать суперюзера)
│   │   └── LinkCommand.php             # admin:link (симлинк ассетов)
│   │
│   ├── Resource/
│   │   ├── Resource.php                # абстракт
│   │   ├── SettingsResource.php        # singleton-Resource (одна запись)
│   │   ├── ResourceCompiler.php
│   │   ├── ResourceRegistry.php
│   │   ├── ResourceManifest.php        # сериализатор для SPA
│   │   ├── SettingsStorage.php         # eloquent / keyValue / custom
│   │   └── Screens/
│   │       ├── GeneratedListScreen.php
│   │       ├── GeneratedEditScreen.php
│   │       ├── GeneratedCreateScreen.php
│   │       ├── GeneratedViewScreen.php # infolist режим
│   │       └── GeneratedSettingsScreen.php
│   │
│   ├── Infolist/
│   │   ├── Entry.php                   # абстракт (аналог Field, read-only)
│   │   ├── TextEntry.php
│   │   ├── BadgeEntry.php
│   │   ├── IconEntry.php
│   │   ├── ColorEntry.php
│   │   ├── KeyValueEntry.php
│   │   ├── RepeatableEntry.php
│   │   ├── ImageEntry.php
│   │   ├── RelationEntry.php
│   │   └── MapEntry.php
│   │
│   ├── Widget/
│   │   ├── Widget.php                  # абстракт
│   │   ├── StatsOverviewWidget.php
│   │   ├── ChartWidget.php
│   │   ├── RecentListWidget.php
│   │   ├── TableWidget.php
│   │   ├── HeatmapWidget.php
│   │   ├── GaugeWidget.php
│   │   ├── MarkdownWidget.php
│   │   ├── IframeWidget.php
│   │   └── WidgetRegistry.php
│   │
│   ├── Dashboard/
│   │   ├── Dashboard.php               # абстракт page-типа
│   │   ├── DashboardCompiler.php
│   │   ├── Models/AdminUserDashboard.php  # custom-layout per user
│   │   └── DashboardRegistry.php
│   │
│   ├── Plugin/
│   │   ├── AdminPlugin.php             # контракт
│   │   ├── PluginRegistry.php
│   │   └── PluginManifest.php          # /system/plugins
│   │
│   ├── Tenancy/
│   │   ├── TenantResolver.php          # контракт
│   │   ├── Tenant.php                  # контракт
│   │   ├── TenantScoped.php            # trait для Resource
│   │   ├── NullTenantResolver.php      # default = single-tenant
│   │   └── Middleware/ResolveTenant.php
│   │
│   ├── Export/
│   │   ├── Exporter.php                # абстракт
│   │   ├── CsvExporter.php             # без зависимостей, стримится
│   │   ├── XlsxExporter.php            # openspout, composer suggest
│   │   ├── PdfExporter.php             # делегирует в PdfRenderer
│   │   ├── PdfRenderer.php             # контракт
│   │   ├── Renderers/MpdfRenderer.php  # composer suggest, GPL-2.0
│   │   ├── Renderers/DompdfRenderer.php# composer suggest, LGPL-2.1
│   │   ├── ExportRegistry.php
│   │   └── Exceptions/MissingExportDriverException.php
│   │
│   ├── Import/
│   │   ├── ImportProcess.php           # AbstractDelayedProcess
│   │   ├── Importer.php                # абстракт
│   │   ├── CsvImporter.php
│   │   ├── XlsxImporter.php
│   │   ├── ColumnMapper.php            # fuzzy-match имён
│   │   ├── ImportPreviewService.php    # валидация первых N строк
│   │   └── ImportReport.php            # imported/updated/failed + errors.csv
│   │
│   ├── Screen/
│   │   ├── Screen.php                  # абстрактный класс (lifecycle: query/layout/actions)
│   │   ├── ScreenRouter.php            # макрос Route::adminScreen() + резолвер action-методов
│   │   ├── Repository.php              # state экрана с dot-notation доступом
│   │   ├── BaseListScreen.php          # шаблон list (query+filters+columns+commandBar)
│   │   ├── BaseEditScreen.php          # шаблон edit (query+rows+save/remove)
│   │   ├── BaseCreateScreen.php
│   │   └── BulkActionScreen.php
│   │
│   ├── Layout/
│   │   ├── Layout.php                  # абстракт + JSON-сериализация
│   │   ├── Rows.php
│   │   ├── Columns.php
│   │   ├── Tabs.php
│   │   ├── Accordion.php
│   │   ├── Modal.php
│   │   ├── Drawer.php
│   │   ├── Block.php
│   │   ├── Table.php
│   │   ├── Metrics.php
│   │   ├── Chart.php
│   │   ├── Wizard.php                  # многошаговая форма
│   │   ├── Step.php                    # шаг wizard
│   │   ├── Infolist.php                # composite Entries
│   │   ├── View.php                    # обёртка для произвольного Vue-компонента
│   │   ├── Wrapper.php
│   │   └── LayoutFactory.php           # Layout::rows(...), Layout::tabs(...), Layout::wizard(...)
│   │
│   ├── Field/
│   │   ├── Field.php                   # абстракт
│   │   ├── Input.php
│   │   ├── Number.php
│   │   ├── Password.php
│   │   ├── Textarea.php
│   │   ├── Select.php
│   │   ├── Combobox.php
│   │   ├── Radio.php
│   │   ├── Checkbox.php
│   │   ├── Switch.php
│   │   ├── DatePicker.php
│   │   ├── DateRange.php
│   │   ├── TimePicker.php
│   │   ├── ColorPicker.php
│   │   ├── Slider.php
│   │   ├── Rating.php
│   │   ├── FileUpload.php
│   │   ├── Code.php
│   │   ├── Wysiwyg.php                 # tiptap по умолчанию (+sister-packs для tinymce/quill)
│   │   ├── Markdown.php                # лёгкий редактор без tiptap, split-preview
│   │   ├── KeyValue.php                # JSON {key: value} редактор
│   │   ├── Builder.php                 # page-builder из Block-классов
│   │   ├── Slug.php                    # auto-generate из source-поля + transliteration
│   │   ├── ImageCropper.php            # canvas-based, без внешних зависимостей
│   │   ├── TagsInput.php
│   │   ├── TreeSelect.php
│   │   ├── Cascader.php
│   │   ├── RelationSelect.php
│   │   ├── RelationTable.php
│   │   ├── Repeater.php
│   │   ├── MorphSwitcher.php
│   │   ├── TranslatableInput.php
│   │   ├── Hidden.php
│   │   ├── Label.php
│   │   └── Group.php                   # горизонтальная композиция полей
│   │
│   ├── Action/
│   │   ├── Action.php
│   │   ├── Button.php
│   │   ├── Link.php
│   │   ├── DropDown.php
│   │   ├── Modal.php
│   │   ├── Bulk.php
│   │   ├── Export.php                  # CSV/Excel/PDF (см. п.3.5 sister-packs)
│   │   ├── Async.php                   # обёртка над delayed-process
│   │   ├── Restore.php                 # для soft-delete
│   │   ├── ForceDelete.php             # для soft-delete
│   │   ├── Replicate.php
│   │   └── Impersonate.php
│   │
│   ├── Filter/
│   │   ├── Filter.php                  # свой абстракт (run(Builder) + display(): Field[])
│   │   ├── Filterable.php              # trait для моделей: scope filters() + sort
│   │   ├── HttpFilterParser.php        # парсинг ?filter[...]=...&sort=... в спецификацию
│   │   ├── Input.php
│   │   ├── Switcher.php
│   │   ├── DateRange.php
│   │   ├── SelectFromModel.php
│   │   ├── SelectFromQuery.php
│   │   └── SelectFromOptions.php
│   │
│   ├── Table/
│   │   ├── TableColumn.php             # TC::make()->sort()->search()->as('badge')->editable()->summary([...])
│   │   ├── ColumnPreset.php            # date, money, badge, copyable, image, link
│   │   ├── ColumnRenderer.php          # сериализация для SPA
│   │   ├── Summarizer.php              # sum/avg/count/range — сервер-side агрегаты
│   │   ├── Grouper.php                 # group-by сервер-side
│   │   ├── Models/AdminUserView.php    # saved views
│   │   ├── UserViewService.php         # CRUD над saved views + share
│   │   └── UserPreferencesService.php  # column visibility/order persist
│   │
│   ├── Permission/
│   │   ├── ItemPermission.php
│   │   ├── PermissionRegistry.php
│   │   ├── Models/Role.php
│   │   ├── Concerns/HasAdminAccess.php
│   │   ├── Middleware/AdminAuth.php
│   │   ├── Middleware/AdminAccess.php
│   │   └── Policies/RolePolicy.php
│   │
│   ├── Menu/
│   │   ├── Menu.php
│   │   ├── MenuItem.php
│   │   └── MenuRegistry.php
│   │
│   ├── Http/
│   │   ├── AdminApiModule.php          # реализация BaseModule из laravel-api
│   │   ├── AdminApi.php                # реализация BaseApi (v1)
│   │   └── Controllers/
│   │       ├── ResourceController.php
│   │       ├── ScreenController.php
│   │       ├── ActionController.php
│   │       ├── UploadController.php
│   │       ├── DelayedController.php
│   │       └── SystemController.php    # /manifest, /me, /menu, /locales
│   │
│   ├── Audit/
│   │   ├── Concerns/Loggable.php       # trait для моделей
│   │   ├── Models/AuditLog.php
│   │   ├── Listeners/LogModelChanges.php
│   │   ├── Listeners/LogAuthEvents.php           # login/logout/failed/2fa/impersonation
│   │   ├── Contracts/AuditableEvent.php
│   │   ├── AuditEntryFactory.php
│   │   ├── AuditTrailLayout.php        # вкладка «История» на EditScreen
│   │   └── AuditTimelineProjector.php  # формат для UiTimeline
│   │
│   ├── Notifications/
│   │   ├── NotificationCenterController.php  # /system/notifications
│   │   ├── Models/AdminNotification.php
│   │   ├── Concerns/HasAdminNotifications.php
│   │   └── Channels/AdminDatabaseChannel.php  # пишет в admin_notifications
│   │
│   ├── I18n/
│   │   ├── LocaleResolver.php
│   │   └── TranslatableFieldBridge.php # связка Field <-> TranslationTrait
│   │
│   ├── Delayed/
│   │   ├── AsyncActionRunner.php
│   │   └── AllowlistRegistrar.php      # авто-merge в config/delayed-process.php
│   │
│   ├── Support/
│   │   ├── ConfigMerger.php            # обёртка над php-array-helper для конфигов
│   │   ├── DotState.php                # репозиторий state с dot-notation
│   │   ├── Manifest.php                # сборка JSON-манифеста для SPA
│   │   └── ValidationRulesExporter.php # PHP rules → JSON-схема для клиента
│   │
│   └── Testing/
│       ├── ActsAsAdmin.php
│       ├── ResourceTestCase.php
│       └── ScreenTestCase.php
│
└── tests/
    ├── Unit/
    ├── Feature/
    │   ├── ResourceCrudTest.php
    │   ├── PermissionsTest.php
    │   ├── DelayedActionTest.php
    │   └── TranslatableFieldTest.php
    └── Pest.php
```

**Sister-пакеты v1.0 (опциональные, не подключаются по умолчанию).**

Каждый — отдельный composer-пакет под `dskripchenko/`, MIT, реализован как `AdminPlugin` (см. п.5.23). Никаких сторонних админ-фреймворков и spatie/* пакетов в зависимостях.

| Пакет | Назначение | Внешние deps | Миграции |
|---|---|---|---|
| `laravel-admin-starter` | Готовые Resource: `UserResource`, `RoleResource`, `AuditLogResource`, `SettingsResource`, `TranslationResource`, `ContentBlockResource`, `AdminUserSessionResource` (опц.). Регистрирует группу `admin.systems.*` permissions. Точка входа в готовую админку | нет | нет |
| `laravel-admin-tinymce` | Альтернативный WYSIWYG-движок поверх TinyMCE. ⚠️ TinyMCE — GPL/коммерческая лицензия | npm: `@tinymce/tinymce-vue`, `tinymce` | нет |
| `laravel-admin-quill` | Альтернативный WYSIWYG поверх Quill. Поддержка output `html` и `delta` (для co-editing) | npm: `@vueup/vue-quill`, `quill` | нет |
| `laravel-admin-search` | Глобальный поиск (cmd+K). Driver `eloquent` (default, без deps) или `scout` (через `composer suggest`). Vue-компонент `<GlobalSearchBar>`. Поиск только в Resource'ах с `<resource>.view` | `laravel/scout` (suggest) | нет |
| `laravel-admin-media` | Расширенная медиа-библиотека: коллекции, теги, focal-point, responsive-варианты, EXIF-стрипинг. Свои таблицы `admin_media` + `admin_media_variants` + pivot. Field `MediaPicker` заменяет базовый `FileUpload`. Без `spatie/laravel-medialibrary` | `intervention/image` (suggest, для сложного processing) | да |
| `laravel-admin-health` | Health-checks dashboard. Контракт `HealthCheck` + готовые чекеры (DB/Cache/Queue/Redis/Disk/Log/Schedule heartbeat). Artisan `admin:health:run` для cron, индикатор в шапке, widget на dashboard. Без `spatie/laravel-health` | нет | да |
| `laravel-admin-pulse` | Лёгкая телеметрия: request volume, slow-routes, slow-queries, exceptions, jobs throughput. Сэмплер через middleware (rate=1/N), TTL-rotation. Без `laravel/pulse` | нет | да |
| `laravel-admin-jobs` | Viewer для `failed_jobs`, batches, queue depth. Resource'ы `FailedJobResource`, `JobBatchResource`, `QueuedJobResource` (для DB-driver). Widget `QueueDepthWidget`. Опц. notification при появлении failed-job | нет | нет |

Подробные спецификации — в `docs/sister-packs/` (по одному файлу на пакет).

**План развития (v1.x+).**

Опциональные пакеты, которые могут появиться по мере набора практики и запросов. Не закладываются в роадмап v1.0 и вообще не блокируют ничего, но фиксируем намерение, чтобы не дублировать концепции.

| Пакет | Назначение | Когда нужен |
|---|---|---|
| `laravel-admin-sso` | SSO/OIDC: вход через Google / Microsoft / Keycloak / SAML 2.0. Контракт `SsoProvider` + готовые провайдеры. UI-кнопки на login-странице, маппинг внешних claim'ов на роли | enterprise/корпоративные клиенты |
| `laravel-admin-webauthn` | Security-keys (FIDO2/WebAuthn) как альтернатива TOTP. Регистрация/проверка ключа в `ProfileScreen`, опционально замена/дополнение `*-2fa` | проекты с повышенными требованиями к безопасности |
| `laravel-admin-mail-preview` | UI для предпросмотра mailable'ов (тех, что отправляет приложение): список, render с подстановкой test-данных, отправка тестового письма на свой email. Полезно при разработке шаблонов | команды с большим количеством транзакционных писем |
| `laravel-admin-cron` | UI для `schedule:list` и истории запусков задач из консоли. Resource `ScheduledTaskResource`, лог запусков (старт/конец/exit-code/output). Алерты при пропусках | проекты с сложными scheduler-задачами |
| `laravel-admin-translate-cli` | Массовый автоперевод translatable-моделей через DeepL / OpenAI / Yandex.Translate API. Bulk-action на TranslationResource: «перевести все пропуски на язык X» с предпросмотром и стоимостью | мультиязычные CMS-проекты |

Каждый из них реализуем тем же `AdminPlugin`-контрактом. Список не закрыт — экосистема может расширяться по мере появления потребностей.

---

## 7. SPA: как работает «без перезагрузок»

1. **Bootstrap.** `routes/admin.php` отдаёт ровно один Blade `shell.blade.php` на любой URL с префиксом `/admin/*`. В `<head>` инжектится `window.__ADMIN_BOOTSTRAP__ = { csrf, baseUrl, locale, user, manifest }`.
2. **Vue-router** на стороне клиента; роуты тривиально мапятся на манифест: `/admin/resources/{slug}` → `<ListScreen>`, `/admin/resources/{slug}/{id}` → `<EditScreen>`, `/admin/screens/{name}` → `<CustomScreen>`.
3. **Манифест** (`/admin/api/v1/system/manifest`) — это JSON со всеми Resource/Screen/Field/Layout/Action/Permission. SPA получает его при логине и кэширует. Inval по `etag`.
4. **Запросы.** Любая операция (получить запись, сохранить форму, применить bulk-action, открыть модалку) — это вызов `laravel-api`-эндпоинта. Конверт всегда одинаковый: `{success, payload}` для успеха, `{success: false, payload: { errorKey, message|messages }}` для ошибки.
5. **Reactive layouts.** `Field` может декларативно зависеть от других полей: `Field\Select::make('region_id')->reactive()->reloadFor(['country_id'])`. SPA шлёт XHR на `/admin/api/v1/resources/{slug}/field/region_id?country_id=...` и подставляет новый список опций / новую схему.
6. **Долгие операции.** Любой response в формате `{payload: {delayed: {uuid}}}` автоматически перехватывается `applyAxiosInterceptor` из `laravel-delayed-process` — тостер показывает прогресс, по завершении промис возвращает финальный payload.
7. **Алерты.** Сервер прикладывает `payload.alerts: [{type, message}]` — SPA рендерит через `<UiToast>`.
8. **Без full reload.** Login/logout тоже идут через JSON-API; единственный full-reload — на `401` (повторная инициализация bootstrap).

---

## 8. Виджеты (mapping Field ↔ @dskripchenko/ui)

| Field (PHP) | UI-component | Особенности |
|---|---|---|
| `Input` / `Number` / `Password` | `<UiInput>` / `<UiNumberInput>` | type, mask, prefix/suffix |
| `Textarea` | `<UiTextarea>` | autosize |
| `Select` | `<UiSelect>` | options/fromModel/async |
| `Combobox` | `<UiCombobox>` | свободный ввод + автокомплит |
| `Radio` / `Checkbox` / `Switch` | `<UiRadio>` / `<UiCheckbox>` / `<UiSwitch>` | inline-вариант |
| `DatePicker` / `DateRange` / `TimePicker` | `<UiDatePicker>` / `<UiDateRangePicker>` / `<UiTimePicker>` | timezone-aware |
| `ColorPicker` | `<UiColorPicker>` | hex/rgba |
| `Slider` / `Rating` | `<UiSlider>` / `<UiRating>` | step, marks |
| `FileUpload` | `<UiFileUpload>` | через UploadController, single/multi, image-preview |
| `Code` | `<UiCode>` | подсветка |
| `Wysiwyg` | Tiptap (в core) | конфиг `extensions: ['heading','bold','italic','link','image','table',...]`; альтернативные движки (TinyMCE/Quill) — sister-пакеты |
| `Markdown` | `<UiTextarea>` + `marked` preview | lightweight, без Tiptap; `->preview('split'\|'tab'\|'off')`, `->toolbar([...])` |
| `KeyValue` | `<UiStack>` пар `<UiInput>` | JSON-объект; `->keyLabel`, `->valueLabel`, `->reorderable`, `->keyValidation(...)` |
| `Builder` | inline-палитра + список Block-карточек | page-builder; `->blocks([HeroBlock::class, TextBlock::class, ...])`, drag-reorder |
| `Slug` | `<UiInput>` со spy на source | `->from('title')->unique()->editable()`, авто-translit RU/EN, lock-режим |
| `ImageCropper` | canvas-overlay над `<UiFileUpload>` | `->aspectRatio(...)`, output JPEG/WebP, без imagick/gd на клиенте |
| `TagsInput` / `TreeSelect` / `Cascader` | соответственно | для иерархий и many-to-many |
| `RelationSelect` | `<UiSelect>` + async | BelongsTo |
| `RelationTable` | `<UiTable>` редактируемая | HasMany inline, добавление/удаление строк |
| `Repeater` | `<UiAccordion>` или сетка карточек | произвольная схема в каждом элементе |
| `MorphSwitcher` | `<UiTabs>` или `<UiSelect>` + динамическая форма | смена type подменяет fields |
| `TranslatableInput` | `<UiTabs>` (вкладки по локалям) + любой child-Field | tight-coupling с TranslationTrait |
| `Hidden` / `Label` | без рендера / `<UiLabel>` |  |
| `Group` | `<UiStack direction="row">` | горизонтальная композиция нескольких Field |

Системные UI-компоненты (вне Field/Layout):

| Компонент | Назначение |
|---|---|
| `<NotificationBell>` + `<NotificationCenter>` | колокольчик с счётчиком + drawer со списком уведомлений |
| `<ImpersonationBanner>` | предупреждение в шапке при работе под другим пользователем |
| `<TwoFactorChallenge>` | страница ввода 6-значного кода + recovery |
| `<UnsavedChangesGuard>` | глобальный хук + модалка при попытке покинуть грязную форму |
| `<ProfileMenu>` | dropdown в шапке (имя, аватар, профиль, выйти) |

Layout-уровень:

| Layout (PHP) | UI-component |
|---|---|
| `Rows` | `<UiForm>` (внутри FormField обёртки) |
| `Columns` | `<UiGrid>` |
| `Tabs` / `Accordion` | `<UiTabs>` / `<UiAccordion>` |
| `Modal` / `Drawer` | `<UiModal>` / `<UiDrawer>` |
| `Block` | `<UiCard>` |
| `Table` | `<UiTable>` |
| `Metrics` | `<UiStat>` сетка |
| `Chart` | `<UiSparkline>` / `<UiGauge>` / `<UiHeatmap>` |
| `View` | `<component :is="...">` через регистрацию пользовательских компонентов |

---

## 9. Конфигурирование и кастомизация

**Несколько уровней «гибкости без боли»:**

1. **Декларативный (90% случаев):** Resource-класс. Только перечисление полей/колонок/фильтров.
2. **Override методов Resource:** `query()`, `validationRules()`, `beforeSave()`, `afterSave()`, `searchable()`, `with()`, `policy()`.
3. **Свой Layout вместо дефолтного:** `public function editLayout(): array { return [Layout::tabs([...])]; }` — Resource перестаёт собирать Rows автоматически.
4. **Подмена Screen целиком:** `protected static array $screens = ['edit' => CustomEditScreen::class];`.
5. **Свой Field:** наследуем `Field`, прописываем `protected string $widget = 'my-field'`, регистрируем Vue-компонент `app.component('my-field', MyField)`.
6. **Свой Layout-type:** наследуем `Layout`, переопределяем `serialize()`, регистрируем Vue-компонент.
7. **Тема:** оверрайд CSS-переменных `--ui-color-primary`, `--ui-radius-sm` и т.д. в `resources/ts/theme/overrides.css`.
8. **Меню/брендинг:** `Admin::menu([...])`, `Admin::brand('Acme', '/logo.svg')`.

Слияние конфигов панели идёт через `ConfigMerger` (обёртка над `array_merge_deep` из `php-array-helper`): дефолт пакета → опубликованный config → runtime overrides из `AppServiceProvider::boot`.

---

## 10. Установка в существующий Laravel-проект

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install      # публикует config, миграции, vite-конфиг, base ServiceProvider
php artisan migrate
php artisan admin:user "Admin" admin@example.com secret
npm install
npm run admin:build            # либо admin:dev для HMR
```

`admin:install` спрашивает (interactive):

- путь панели (default `admin` → `/admin`) и домен (опционально, для поддомена);
- стратегия auth:
  - `dedicated` (default) — свой guard `admin`, своя таблица `admin_users`;
  - `shared` — использовать существующий guard host-проекта (тогда дополнительно: имя guard'а, FQCN модели, добавить ли trait `HasAdminAccess` автоматом);
- набор middleware для shell/api/public (предлагаются дефолты, можно подтвердить или изменить);
- запустить ли миграции и `admin:user` сразу.

После этого admin доступен по `/{path}` (или на указанном домене) без вмешательства в существующие routes/middleware/views host-проекта.

---

## 11. Безопасность и интеграция с auth (multi-guard)

### 11.1. Принцип

Админка живёт в собственном изолированном auth-контуре по умолчанию: свой guard, своя модель пользователя, своя таблица, свой login. Это не мешает host-проекту — `web`-guard остаётся нетронутым. При желании весь контур можно подменить на существующий.

### 11.2. Конфигурация

Всё критичное настраивается в `config/admin.php`:

```php
return [
    // 11.2.1. Адрес панели
    'path'      => env('ADMIN_PATH', 'admin'),       // /admin
    'domain'    => env('ADMIN_DOMAIN'),              // null = тот же домен; иначе субдомен
    'api_path'  => 'api/v1',                         // итог: /admin/api/v1/...

    // 11.2.2. Auth
    'auth' => [
        'guard'    => env('ADMIN_GUARD', 'admin'),   // имя guard'а
        'provider' => env('ADMIN_PROVIDER', 'admin_users'),
        'model'    => \Dskripchenko\LaravelAdmin\Models\AdminUser::class,
        'table'    => 'admin_users',
        'password_broker' => 'admin_users',          // для reset password
        'login_throttle'  => '5,1',                  // 5 попыток в минуту
    ],

    // 11.2.3. Middleware stack
    'middleware' => [
        'shell' => [                                 // на SPA-оболочку (HTML)
            'web',
            \Dskripchenko\LaravelAdmin\Http\Middleware\AdminLocale::class,
        ],
        'api' => [                                   // на JSON API
            'api',
            \Dskripchenko\LaravelAdmin\Http\Middleware\AdminAuth::class,
            \Dskripchenko\LaravelAdmin\Http\Middleware\AdminLocale::class,
            \Dskripchenko\LaravelApi\Http\Middleware\ApiMiddleware::class,
        ],
        'public' => [                                // login/logout/forgot — без AdminAuth
            'web',
        ],
    ],

    // 11.2.4. Сессия (опционально — отдельная от web)
    'session' => [
        'cookie' => env('ADMIN_SESSION_COOKIE', null),  // null = используем web-сессию
        'driver' => null,                               // null = тот же driver
    ],
];
```

### 11.3. Что регистрируется автоматически

`AdminServiceProvider` в `boot()`:

1. Регистрирует guard `admin` (если ещё не существует) и provider `admin_users` через `Auth::extend()` без правки `config/auth.php` host-проекта.
2. Привязывает `AdminUser` к provider'у.
3. Регистрирует roуты с настроенным `path`/`domain`/`middleware`.
4. Включает password-broker для `admin_users`.

Если разработчик в `config/admin.php` указал свой guard (`'guard' => 'web'`) — авто-регистрация отдельного guard'а пропускается, используется существующий, ожидается trait `HasAdminAccess` на существующей модели User.

### 11.4. Модель `AdminUser` (default)

```php
// dskripchenko/laravel-admin/src/Models/AdminUser.php
class AdminUser extends Authenticatable
{
    use Notifiable, HasAdminAccess, HasApiTokens, Loggable;

    protected $table = 'admin_users';
    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];
    protected $casts  = ['email_verified_at' => 'datetime'];
}
```

Миграция `create_admin_users_table` (стандартный набор: `id, name, email, password, remember_token, email_verified_at, locale, last_login_at, timestamps`).

### 11.5. Login flow

- Login-страница на Vue (часть SPA-shell, доступна без AdminAuth) — POST на `/admin/api/v1/auth/login` (контроллер из core).
- Поддержка remember-me, throttling, password reset, email verification (вкл/выкл флагами в конфиге).
- 2FA (TOTP) — в core, см. п.5.11. Включается флагом `auth.two_factor.enabled` в `config/admin.php`.
- API-токены: на той же модели через Sanctum-trait (опциональная зависимость) — для headless-сценариев и интеграций.

### 11.6. Сценарии установки

| Кейс | Конфиг |
|---|---|
| Чистая админка с нуля | дефолт: свой guard `admin`, своя таблица `admin_users` |
| Уже есть `User` и хотим один кабинет | `auth.guard = 'web'`, `auth.model = App\Models\User`, `auth.provider = 'users'`, добавить trait `HasAdminAccess` |
| Поддомен | `domain = 'admin.example.com'`, `path = ''` |
| Своя auth-логика (SSO, LDAP) | переопределяем `LoginController` через DI-контейнер; guard остаётся свой |
| Mobile + Web admin | оставляем session-guard для веба + Sanctum для API, оба указывают на `admin_users` |

### 11.7. CSRF

- SPA-shell под `web`-middleware → стандартный CSRF.
- API-запросы — axios автоматически шлёт `XSRF-TOKEN` из cookie. Для headless через Sanctum — Bearer-токен.

---

## 12. Этапы разработки

Roadmap пересчитан под полный объём (gap-анализ + sister-packs). Срок указан в неделях работы одного разработчика full-time. При параллельной работе двух разработчиков core-фазы могут идти ~1.5x быстрее, sister-packs идут полностью параллельно после Phase 11.

### 12.1. Core (v1.0)

| Фаза | Скоуп | Срок |
|---|---|---|
| **P0. Скаффолд** | composer/package skeleton, AdminServiceProvider, базовый config/admin.php, shell.blade.php, Vite-конфиг, CI (PHPUnit/Pest, Vitest, ESLint, Pint) | 1 нед |
| **P1. Backbone** | Screen/Layout/Field/Action/Filter абстракты, Repository (dot-state), JSON-сериализация манифеста, ScreenRouter (`Route::adminScreen()`), LayoutRenderer/FieldRenderer на SPA, минимальная интеграция с `laravel-api` (BaseApi/AdminApiModule), smoke-test «Hello, Resource» | 2.5 нед |
| **P2. Auth & RBAC** | Multi-guard registrar, AdminUser, миграции admin_users + admin_roles + admin_role_assignments, ItemPermission, PermissionRegistry, AdminAuth/AdminAccess middleware, login/forgot/email-verify, **2FA TOTP** (свой генератор + recovery codes), **ProfileScreen**, **impersonation** + банер, **password reset notifications** | 4 нед |
| **P3. Resource v1** | ResourceCompiler, ResourceRegistry, GeneratedListScreen/EditScreen/CreateScreen, ResourceController (CRUD), ResourceManifest с **etag + version** кэшем, реактивный manifest endpoint | 2 нед |
| **P4. Базовые Field** | Input/Number/Password/Textarea/Select/Combobox/Radio/Checkbox/Switch/DatePicker/DateRange/TimePicker/ColorPicker/Slider/Rating/FileUpload/Code/Hidden/Label + ValidationRulesExporter | 2 нед |
| **P5. Сложные Field** | RelationSelect/RelationTable/Repeater/MorphSwitcher/TranslatableInput, **Markdown**, **KeyValue**, **Builder** (page-builder), **Slug**, **ImageCropper**, TagsInput/TreeSelect/Cascader, Group | 3 нед |
| **P6. Tables advanced** | TableColumn (+as/sort/search/copyable presets), Filterable trait, HttpFilterParser, базовые фильтры (Input/Switcher/DateRange/SelectFromModel/Query/Options), **inline-edit**, **saved views**, **column visibility/reorder per user**, **summarizers**, **group-by**, **polling**, CSV-экспорт встроенный | 2.5 нед |
| **P7. Layouts/primitives** | Tabs/Accordion/Modal/Drawer/Block/Wrapper/View, **Wizard + Step**, **Infolist + Entries** (TextEntry/BadgeEntry/...), GeneratedViewScreen | 1.5 нед |
| **P8. Widgets + Dashboard** | Widget абстракт, StatsOverview/Chart/RecentList/Table/Heatmap/Gauge/Markdown/Iframe, Dashboard-page, AdminUserDashboard (custom layouts per user) | 1.5 нед |
| **P9. Resource extras** | Soft-delete first-class (Restore/ForceDelete + фильтр), **Replicate**, **Reorder**, **Unsaved changes guard** | 1 нед |
| **P10. Audit** | Loggable trait, AuditLog model, listeners (model + auth-events), AuditTrailLayout, AuditTimelineProjector | 0.5 нед |
| **P11. Settings + Plugin + Tenancy** | SettingsResource + SettingsStorage (eloquent/keyValue), AdminPlugin контракт + PluginRegistry, TenantResolver/Tenant контракты + TenantScoped trait | 1 нед |
| **P12. Actions advanced** | Bulk/Async/Modal/DropDown/Link/Restore/ForceDelete/Impersonate/Replicate/Export, интеграция с `delayed-process` + AllowlistRegistrar | 1 нед |
| **P13. Export/Import** | XLSX (openspout suggest), PDF (PdfRenderer контракт + Mpdf/Dompdf адаптеры), 4-шаговый Import wizard, ImportProcess, ColumnMapper, ImportPreviewService | 2 нед |
| **P14. WYSIWYG (Tiptap в core)** | Field\Wysiwyg + tiptap-обёртка, конфиг extensions, image-upload через UploadController | 1 нед |
| **P15. Notifications + API tokens** | Notification center + bell + drawer + database channel + API endpoints, **API-tokens UI** в Profile (опц. через Sanctum) | 1 нед |
| **P16. Theming + i18n** | Light/dark theme в core, ThemeSwitcher, persist (localStorage+cookie), CSS-vars overrides, LocaleResolver, TranslatableFieldBridge, soft-fallback по tag-aware кэшу | 1 нед |
| **P17. Bootstrap + Swagger** | Стратегии bootstrap (inline+nonce / xhr), AdminCspNonce middleware, встроенный Swagger UI на /admin/api/docs (lazy) | 0.5 нед |
| **P18. Тесты + helpers** | ResourceTestCase, ScreenTestCase, ActsAsAdmin, smoke-coverage по всем Field/Layout/Action | 1.5 нед |
| **P19. Документация + примеры** | docs/getting-started, docs/recipes, docs/sister-packs, demo-app | 2 нед |
| **P20. Бета** | реальный проект-pilot, фикс багов, перф (manifest-кэш, polling, lazy-imports), CSP-проверка, security-аудит | 4 нед |
| **Итого core v1.0** |  | **~36 недель ≈ 8.5 мес** (одиночная разработка) |

### 12.2. Sister-packs v1.0 (параллельно с поздними фазами core)

| Пакет | Срок | Когда стартует |
|---|---|---|
| `laravel-admin-starter` | 2 нед | после P11 (нужны Settings+Plugin контракты) |
| `laravel-admin-search` | 1.5 нед | после P3 (нужен Resource v1) |
| `laravel-admin-media` | 4 нед | после P5 (нужен ImageCropper и FileUpload) |
| `laravel-admin-health` | 1.5 нед | после P11 (нужны Plugin+Settings) |
| `laravel-admin-pulse` | 2.5 нед | после P11 |
| `laravel-admin-jobs` | 1.5 нед | после P3 |
| `laravel-admin-tinymce` | 1 нед | после P14 |
| `laravel-admin-quill` | 1 нед | после P14 |
| **Итого sister-packs** | **~15 нед** | реально 6–8 нед при параллельной разработке двух человек |

### 12.3. Sister-packs v1.x+ (план развития)

`laravel-admin-sso`, `laravel-admin-webauthn`, `laravel-admin-mail-preview`, `laravel-admin-cron`, `laravel-admin-translate-cli` — оценки делаем по запросу, ориентир ~2 нед каждый.

### 12.4. Итог

- **Core v1.0:** ~8.5 месяцев одиночной разработки или ~6 мес при двух разработчиках full-time.
- **Полный экосистемный v1.0** (core + 8 sister-packs): ~10–11 месяцев одиночной разработки или ~7 мес параллельной.
- **Минимальный публичный релиз** (P0–P11 + P15 + P19 + быстрый бета): можно ужать до ~6 месяцев, выпуская оставшиеся фазы инкрементально в 1.x.

---

## 13. Открытые вопросы для вычитки

Сюда стоит вернуться вместе и зафиксировать решения. Решённые помечены ✅.

0. ✅ **Backbone.** Полностью собственная кодовая база, без сторонних админ-фреймворков в зависимостях.

1. ✅ **Audit-trail внутри admin** (модуль `src/Audit/`). Решено: не выносим в отдельный пакет, чтобы не плодить репозитории. Если когда-нибудь понадобится audit вне админки — рефакторим в отдельный пакет позже.

2. ✅ **Multi-guard в core.** Пакет поставляет собственный `admin`-guard (отдельная модель `AdminUser`, отдельная таблица `admin_users`), но при установке всё конфигурируется: префикс URL/домен, имя guard'а, провайдер, модель пользователя, набор middleware. Если у проекта уже есть свой guard — указываем его в конфиге, пакет переиспользует. Подробности — раздел 11.

3. ✅ **Tiptap в core.** `Field\Wysiwyg::make(...)` работает «из коробки» поверх Tiptap (Vue 3-native, MIT, активная поддержка). Расширение базы Tiptap-узлов (таблицы, code-block, mentions) — через конфиг и опциональные sister-пакеты. TinyMCE/Quill — только если кому-то нужно заменить — выносятся в опциональные `dskripchenko/laravel-admin-tinymce` / `*-quill`.

4. ✅ **File-storage.** Никакой `spatie/laravel-medialibrary` в core. Своя минимальная таблица `admin_attachments` + `Storage::disk()`. Расширенный медиа-функционал — в опциональном `dskripchenko/laravel-admin-media`.

5. ✅ **Search (глобальный).** Не в core. Опциональный `dskripchenko/laravel-admin-search` (тонкая Scout-обёртка).

6. ✅ **Default light/dark тема в core.** Поверх токенов `@dskripchenko/ui` поставляем admin-overrides (`--admin-sidebar-bg`, `--admin-topbar-height`, `--admin-table-row-density` и т.д.) и переключатель в шапке с persist в `localStorage` + cookie. Host-проект подменяет любую переменную через свой CSS-файл, подключаемый последним.

7. ✅ **Namespace `Dskripchenko\LaravelAdmin\`** (буквальное соответствие имени composer-пакета). Все классы в `src/` живут под этим namespace.

8. ✅ **L12+ only.** Минимум — PHP `^8.5`, Laravel `^12`. Никаких совместимостных веток для L11 — каждая будущая мажорная версия Laravel поддерживается по мере выхода. Это согласуется с требованиями `delayed-process` 2.0.

9. ✅ **Pinia.** Уже в `peerDependencies`. Сторы: `auth`, `manifest`, `permissions`, `menu`, `locale`, `theme`, `alerts`. DevTools-интеграция работает «из коробки».

10. ✅ **Soft fallback по tag-aware кэшу.** Если cache-driver не поддерживает теги — кэширование переводов внутри admin отключается, в лог пишется warning, `admin:doctor` показывает рекомендацию переключиться на Redis/Memcached. Админка запускается на любом cache-driver, перформанс просядет на горячих запросах с переводами, но это не блокирует работу.

11. ✅ **Тестирование Resource без автогенерации фабрик.** Пользователь пишет штатные Laravel-фабрики сам. Наш `ResourceTestCase` сосредоточен на admin-специфике: `actingAsAdmin($admin, $permissions)`, `assertResourceList($resource, $expected)`, `assertResourceCreated`, `assertResourceUpdated`, `assertResourceDeleted`, `submitResourceForm($resource, $payload)`, `bulkAction($resource, $name, $ids)`.

12. ✅ **Семвер admin-пакета = семвер API.** `/admin/api/v1` — внутренний контракт между core и SPA, breaking-changes допустимы в мажорных релизах admin (с миграционными нотами в CHANGELOG). Никаких параллельных v1/v2 ради обратной совместимости. Внешние потребители, которым нужен стабильный API, поднимают свой публичный слой поверх admin (через `laravel-api` это легко).

13. ✅ **Manifest-кэш: etag + version (гибрид).** `/admin/api/v1/system/manifest` отдаёт `ETag: hash(resources, locale, user.permissions)` и поддерживает `If-None-Match` → 304. Параллельно `bootstrap` инжектит `manifest_version` (тот же хэш) — SPA сначала сверяет с `localStorage`, при совпадении вообще не дёргает эндпоинт. Хэш собирается из сериализованного `ResourceCompiler`-вывода + версии admin-пакета + локали + permissions пользователя. Кэш-ключ в `localStorage`: `admin:manifest:{version}:{locale}`.

14. ✅ **Встроенный Swagger UI.** `/admin/api/docs` под admin-аутентификацией, lazy-loaded `swagger-ui-dist` (не входит в основной SPA-бандл). Спецификация на `/admin/api/openapi.json` через генератор `laravel-api`. Виден только пользователям с пермишеном `admin.system.api-docs` (по умолчанию суперюзер).

15. ✅ **Конфигурируемая стратегия bootstrap.** `config/admin.php → bootstrap.strategy`: `inline` (default, inline `<script>` с поддержкой CSP-nonce через `Vite::useCspNonce()`) или `xhr` (пустая shell + первый запрос на `/admin/api/v1/system/bootstrap`). Никакого `'unsafe-inline'` по умолчанию: при `inline`-стратегии генерируем `nonce` и кладём в CSP-header через middleware `AdminCspNonce`.

16. ✅ **Только Laravel-events.** Никакого собственного observer-DSL. Эмитим стандартные события: `Admin\Events\ActionDispatched`, `ActionCompleted`, `ActionFailed`, `ResourceQueried`, `ResourceSaved`, `ResourceDeleted`, `ValidationFailed`, `BulkActionStarted`, `DelayedActionDispatched`, `LoginSucceeded`, `LoginFailed`. Host-проект слушает их штатным `Event::listen()` или через сервис-провайдер APM-интеграции (Sentry/Telescope/OpenTelemetry — у всех есть listener'ы для Laravel-events). Resource-специфичную логику (per-resource hooks) пользователь выражает через `beforeSave`/`afterSave`/`beforeDelete` на самом Resource.

---

## 14. TL;DR

Мы строим **конструктор админок**:

- кодовая база **полностью своя**, без сторонних админ-фреймворков в зависимостях;
- современный Vue 3 SPA-фронт на `@dskripchenko/ui`;
- единая JSON-API-транспортная ось на `dskripchenko/laravel-api`;
- минимум внешних зависимостей: только `laravel/framework` + наши `dskripchenko/*` пакеты;
- Resource-первый подход → быстрый CRUD за минуты, Screen/Layout/Field — когда нужна свобода;
- Permissions, Audit, i18n, Delayed actions, RelationTable — всё из коробки;
- ноль завязок на структуру host-проекта, ноль перезагрузок страницы.

Следующий шаг: пройтись по разделу 13 «Открытые вопросы», зафиксировать решения, после чего я уточню документ и подготовлю детализацию по первой фазе (скаффолд).
