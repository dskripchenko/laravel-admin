# Roadmap — статус фаз

Статус выполнения плана из [ARCHITECTURE.md §12](ARCHITECTURE.md) (там —
скоуп и оценки). Обновляется при закрытии фаз и major-вехах.

Актуально на 2026-08-18 (composer v1.30.0 / npm 1.30.0).

## Core

| Фаза | Статус | Примечание |
|---|---|---|
| P0. Скаффолд | ✅ | |
| P1. Backbone | ✅ | |
| P2. Auth & RBAC | ✅ | 2FA TOTP, impersonation, profile |
| P3. Resource v1 | ✅ | manifest etag/version |
| P4. Базовые Field | ✅ | |
| P5. Сложные Field | ✅ | Builder — базовый; см. Backlog |
| P6. Tables advanced | ✅ | inline-edit, saved views, summarizers, group-by |
| P7. Layouts/primitives | ✅ | Wizard, Infolist + Entries |
| P8. Widgets + Dashboard | ✅ | |
| P9. Resource extras | ✅ | soft-delete, replicate, reorder |
| P10. Audit | ✅ | + auth-события (single-dispatch с 1.8.5) |
| P11. Settings + Plugin + Tenancy | ✅ | |
| P12. Actions advanced | ✅ | async через delayed-process |
| P13. Export/Import | ✅ | XLSX/PDF/CSV, import-wizard |
| P14. WYSIWYG | ✅ | default — @dskripchenko/wysiwyg; tinymce/quill sister-packs |
| P15. Notifications + API tokens | ✅ | |
| P16. Theming + i18n | ✅ | |
| P17. Bootstrap + Scalar UI | ✅ | |
| P18. Тесты + helpers | ✅ | 857 backend / 325 frontend на 1.9.0 |
| P19. Документация + примеры | ✅ | en/ru/de/zh + demo |
| **P20. Бета (пилот)** | ✅ | **Закрыта серией 1.7.x–1.8.9 на пилоте printable**: staging-стенд, мультитенантность (schemify-слои), две панели, E2E-прогон сценариев обеих панелей (28/28). Найдено и исправлено на пилоте: guest-manifest 500, panel-aware auth, throttle-дубли и общие бакеты, event-дубли Login/Logout, snake_case field-регистрация, RelationSelect options, mode-visibility полей, unique auto-ignore, DB-422 messages, SPA-permissions hasAccess-only моделей, префилл Field::default() |
| P21. Canon-матрица версий | ✅ | PHP 8.2–8.5 × Laravel 11/12/13 |
| M1. Panels | ✅ | v1.8.0 — независимые поверхности (Filament-parity), guard/provider/broker per panel |

**v1.9.0 — стабильный срез: core v1.0-скоуп выполнен полностью.**

## Frontend (npm @dskripchenko/laravel-admin)

| Веха | Статус | Примечание |
|---|---|---|
| F1–F9 + F-refactor.0 | ✅ | на @dskripchenko/ui |
| P22 ScreenPage, M1 AdminSidebarNode | ✅ | n-уровневое меню |
| F10. Dashboard | ✅ | npm 1.8.0+1.8.1 / composer 1.9.1 (2026-07-22, E2E 8/8 на стенде): DashboardPage — 12-col grid, edit-mode (drag-reorder / resize-span / hide / remove), AddWidgetDialog из реестра, per-user персистенция (/dashboard/get|save|reset, DashboardLayout); полный набор widget-компонентов под backend-типы: stats/chart(+bar/donut)/recent_list/heatmap/gauge/markdown/**table** (resource-колонки + formatCell)/**iframe** (sandbox); 1.8.1: сидирование draft при входе в edit (первый save слал widgets:[] → 422), кнопка «Сбросить» (reset был без UI), i18n тулбара |

## Sister-packs

| Пакет | Статус |
|---|---|
| starter, search, media, health, pulse, jobs, tinymce, quill | ✅ v1.0 |

## Backlog — закрыт 2026-07-22 (core v1.9.2 + npm 1.9.0)

- ✅ SPA-компоненты сложных полей: `key_value`, `repeater`, `builder`,
  `relation_table` (npm 1.9.0; вложенные под-формы через NestedFieldsGroup).
- ✅ `admin:user --super` — назначает системную роль Super Admin (v1.9.2).
- ✅ Session invalidation: смена пароля гасит остальные сессии
  (AuthenticateSession-семантика в AdminAuth, 401 session_expired);
  выключенная учётка теряет доступ на следующем запросе (v1.9.2).
- ✅ Object-rules доезжают до валидатора (ValidationRulesExporter, v1.9.2) —
  включило composite-unique per-field 422 до DB в printable (ScopedUniqueRule).
- ✅ printable: CSRF-исключение сужено до stateless-поверхностей (§3.1);
  rate-limit внешнего API ключуется credential-token'ом, internal — ip+client.

## Открыто

### OpenAPI-спека ссылается на то, чего нет — ЗАКРЫТО (1.31.0, 1.31.1)

> Было 1204 ошибки и 63 предупреждения, стало ноль; проверка закреплена
> строкой `api:lint --strict` в CI printable, и проверено, что она ловит
> регресс.

Найдено 18.08.2026 первым же прогоном `api:lint` (laravel-api 5.7.0) на
printable. Спека панели, которую отдаёт `GET /api/doc`, ссылается на схемы
безопасности и шаблоны ответов, не определённые нигде.

Корень маленький, множитель большой. В докблоках контроллеров ядра стоят
четыре схемы — `AdminSession` (612 упоминаний), `AdminBearer` (216),
`Public` (18), `AdminAuth` (2), — а `getOpenApiSecurityDefinitions()` в
`AdminApi` не переопределён вовсе, то есть определений ноль. И 26 шаблонов
ответа, которых нет среди 208 объявленных:

```
AuditTimelineResponse, DashboardLayoutResponse, DashboardLayoutSavedResponse,
DashboardWidgetsResponse, DelayedProcessRunResponse, DelayedProcessStatusResponse,
ImportStartResponse, ImportStatusResponse, LocaleUpdatedResponse,
NotificationListResponse, NotificationMarkAllResponse, NotificationMarkResponse,
NotificationUnreadResponse, ResourceCreateScreenResponse, ResourceEditScreenResponse,
ResourceExportResponse, ResourceForceDeletedResponse, ResourceInlineUpdatedResponse,
ResourceListScreenResponse, ResourceReorderedResponse, ResourceSummaryResponse,
ResourceViewScreenResponse, StatusResponse, ThemeStateResponse, ThemeUpdatedResponse,
UploadResponse
```

`ResourceListScreenResponse` и соседи по `Resource*` умножаются на каждый
ресурс приложения, `AdminSession` — на каждое действие, и всё это ещё раз на
каждую панель. Отсюда 1204 ошибки на printable при паре десятков настоящих
причин. `StatusResponse` — свежий, приехал вместе с `system.status` в 1.30.0.

Почему это не заметили: `@response 200 {Нечто}` с неопределённым шаблоном даёт
`$ref: '#/components/schemas/Нечто'` в спеку, которая **остаётся валидной по
схеме OpenAPI** — ломается только у того, кто по ней генерирует клиент или
открывает её в Swagger UI. Панель работает, тесты зелёные.

Порядок работ:

1. Объявить схемы в `AdminApi::getOpenApiSecurityDefinitions()`. `AdminSession`
   — cookie-сессия, `AdminBearer` — токен в заголовке; `Public` и `AdminAuth`
   разобрать отдельно: `Public` по смыслу означает «схема не нужна», и
   правильнее либо убрать тег, либо завести пустой `security: []`, а
   `AdminAuth` (2 места) похож на опечатку в имени.
2. Дописать 26 шаблонов в `getOpenApiTemplates()`. Половина — `Resource*`,
   у них общая форма, так что это не 26 независимых описаний.
3. Заодно: 33 тега `@output file` без переменной (генератор молча выбрасывает —
   у экспорта в итоге нет схемы ответа) и 61 union-тип вроде `object|null`,
   которые этот DSL не понимает и превращает в `string`.
4. Поставить `php artisan api:lint --strict` в CI printable, чтобы регресс не
   вернулся. Проверка не требует ничего, кроме загруженного приложения.

Воспроизведение: `php artisan api:lint --json` в printable.

### Разметка API расходится с валидацией

Найдено 19.08.2026 сверкой правил `$request->validate([...])` с тегами `@input`.
Это **не** то, что чинил `api:lint` в 1.31.x: там ссылки вели в никуда, здесь
разметка валидна и неверна — описывает не тот набор полей, который эндпоинт
принимает. Ни одна проверка такого не видит: и правила, и докблок по отдельности
безупречны, расходятся они только друг с другом.

#### `ResourceController::action` — три ошибки в одном докблоке

Массовое действие над записями, то есть та ручка, которой панель удаляет и
меняет пачками.

```php
'ids'     => ['required', 'array', 'min:1'],
'ids.*'   => ['required'],
'key'     => ['required', 'string'],
'payload' => ['nullable', 'array'],
```

А в докблоке:

```
@input array $items
@response 200 {ResourceReorderedResponse}
```

1. **Имя параметра неверное.** Задокументирован `items`, принимается `ids`.
   Клиент, написанный по спеке, получит 422 на обязательном поле, которого он
   не отправлял.
2. **`key` и `payload` не задокументированы вовсе** — то есть какое именно
   действие выполнить и с какими данными, снаружи узнать нельзя.
3. **Шаблон ответа чужой.** Метод возвращает `{affected, message}`, а объявлен
   `ResourceReorderedResponse`, то есть `{count, message}` от соседнего
   `reorder()`. Заимствован у соседа — ровно тот случай, ради которого в плагине
   записано правило «`@response` не копировать никогда».

   Нужный шаблон в ядре уже есть: `AffectedResponse` / `AffectedPayload`.

#### Остальное

- `DashboardController::save` — не задокументированы шесть полей
  `widgets.*.slug|type|position|size|hidden|config`, то есть вся форма элемента
  раскладки.
- `ProfileController::tokenCreate` — не задокументирован `abilities.*`.

#### Чего в этой уборке делать не надо

Обратное направление («задокументировано, а правила нет») — не дефект сам по
себе. Проверено на этих же контроллерах: `password_confirmation` в
`changePassword` появляется из правила `confirmed`, а `value` в `inlineUpdate`
валидируется динамически по типу колонки. Оба тега верны.

#### Как ловить впредь

`api:lint` читает докблоки и не заглядывает в тело метода. Заглянуть в
`validate([...])` он мог бы — и тогда сверка работала бы в CI, а не только
глазами. Это же ложится и в 0.6.0 плагина (`laravel-api-idea/docs/roadmap.md`),
но там она увидит расхождение лишь у того, кто открыл файл.

### Панельные `create`/`update` не описывают ни одного поля

Найдено 20.08.2026 замером спеки printable. Третья запись про одно и то же
требование, и самая крупная: в 1.31.x чинились ссылки в никуда, 19.08 —
разметка валидная и неверная, здесь разметки **нет вовсе**.

Замер по 685 операциям четырёх версий: 216 не объявляют входа, из них 175
вход и правда не принимают (`system/bootstrap`, `system/me`,
`notifications/unread`), а **41 подразумевают его именем действия** —
`create`, `update`, `search` панели. POST на создание клиента, креденшла,
тарифа: в спеке тело без единого поля.

Не про лень: `ResourceController::create` честно пишет в докблоке прозой
«Which `@input` fields there are is decided by `Resource::fields()`», и
это правда — метод валидирует `$resource->validationRules('create')`.
Набор полей известен, просто в рантайме.

**Механизм в laravel-api для этого есть и здесь не срабатывает.**
`@input [methodName]` подтягивает поля из метода контроллера, но
вызывается как `app()->call("{$class}@{$method}")` — без единого слова о
том, для какого ключа контроллера идёт генерация. А один
`ResourceController` обслуживает все ресурсы разом: спросить его «поля для
`clients`» нечем.

Поэтому работа парная и порядок в ней жёсткий:

1. **laravel-api** передаёт в callable контекст — версию, ключ
   контроллера, действие. Заведено в его бэклоге; изменение обратно
   совместимо, `app()->call()` подставляет только объявленные параметры.
2. **Ядро** реализует провайдер поверх `Resource::fields()` и
   `validationRules()`, принимая этот контекст. Формы у ресурсов общие, так
   что это одна реализация на все, а не 41 описание.

Без первого шага второй невозможен: спрашивать нечем.

Требование заказчика по printable — спека **обязана** быть полной, — так
что это не уборка по возможности. Записи в printable: BL-45 и BL-46 в
`docs/internal/plan/backlog.md`.

### Витрина компонентов — публикация

Storybook подключён и собирается на CI (`npm run storybook` — порт 6007,
`npm run storybook:build` — тот же артефакт статикой). Публикации нет:
Pages в репозитории не включён, workflow нет.

Сделано так осознанно. У `@dskripchenko/ui` витрина опубликована и живёт
на `https://dskripchenko.github.io/ui/`, но там 80 историй — по ней можно
ориентироваться. Здесь пока **одна** (форма входа): она заведена как
образец и как проверка, что оснастка работает. Публичная страница с одной
историей создаёт впечатление документации, которой нет.

Порядок работ:

1. Дописать истории на экраны, которые ломаются чаще прочих и которые
   неудобно проверять руками: профиль (все четыре раздела), редактор
   шаблонов с вкладками, дашборд. Пять-шесть историй уже дают витрине
   смысл. Размеры экранов в переключателе совпадают с тем, что проверяет
   CI printable (402 / 768 / 1280) — дефект, найденный в витрине,
   воспроизводится тестом и наоборот.
2. Workflow — копия `deploy-storybook.yml` из `ui`: сборка с
   `STORYBOOK_BASE_PATH`, `upload-pages-artifact`, `deploy-pages`. Pages
   переключить на источник «GitHub Actions» (workflow это умеет сам, если
   у токена есть права, — проверить глазами).

### Доступность: вложенный интерактив в UidMenuTrigger

`axe-core` на экране входа находит `nested-interactive`: внутри
`.uid-menu-trigger` лежит фокусируемый элемент. Дефект примитива
`@dskripchenko/ui`, чинить в источнике. Пока висит в долге
`printable/tests/e2e/specs/a11y-baseline.json` — храповик там краснеет
только на новых нарушениях, это учтено как известное.
