# API: System

Контроллер `system` — bootstrap, manifest, profile-summary, menu, locales, permissions, plugins, notifications, audit.

> Конвенции — [conventions.md](conventions.md). Регистрация — [registration.md](registration.md).

URL: `api/admin/system/{action}`. Все actions требуют `AdminSession` или `AdminBearer` security, кроме случаев явно отмеченных как public.

---

## SystemController

### Регистрация в `getMethods()`

```php
'system' => [
    'controller' => SystemController::class,
    'middleware' => [AdminAuth::class],
    'actions' => [
        'bootstrap'                  => ['method' => ['get']],
        'manifest'                   => ['method' => ['get']],
        'me'                         => ['method' => ['get']],
        'menu'                       => ['method' => ['get']],
        'locales'                    => ['method' => ['get']],
        'permissions'                => ['method' => ['get']],
        'plugins'                    => ['method' => ['get']],
        'notifications'              => ['method' => ['get']],
        'notificationsRead'          => ['method' => ['post']],
        'notificationsMarkAllRead'   => ['method' => ['post']],
        'notificationsDelete'        => ['method' => ['post']],
        'audit'                      => ['method' => ['get']],
    ],
],
```

---

## Действия

### `system.bootstrap`

```php
/**
 * Получить bootstrap-данные SPA.
 *
 * Используется при стратегии `xhr` (см. config admin.bootstrap.strategy).
 * При стратегии `inline` данные приходят inline в <script> shell.blade.php
 * и этот action не вызывается.
 *
 * @output object  $payload Bootstrap-данные.
 * @output string  $payload.csrf CSRF-токен сессии.
 * @output string  $payload.baseUrl Базовый URL admin (например /admin).
 * @output string  $payload.apiUrl URL admin API (например /api/admin).
 * @output string  $payload.locale Текущая локаль (ru/en/...).
 * @output array   $payload.availableLocales Доступные локали.
 * @output string  $payload.theme Тема (light/dark).
 * @output object  $payload.brand Бренд (name, logo, favicon).
 * @output object  ?$payload.user Текущий админ (null = редирект на login).
 * @output array   $payload.permissions Плоский список ключей permissions.
 * @output string  $payload.manifestVersion Хэш текущего manifest для cache-сравнения.
 * @output object  $payload.pluginVersions plugin_id → version.
 * @output object  $payload.config Подмножество публичных опций config/admin.php.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {BootstrapResponse}
 * @response 401 {UnauthenticatedErrorResponse}
 */
public function bootstrap(Request $request): JsonResponse;
```

### `system.manifest`

```php
/**
 * Получить полный JSON-манифест admin: Resource'ы, Screen'ы, Widget'ы,
 * поля, колонки, фильтры, валидация. SPA кэширует по manifestVersion + If-None-Match.
 *
 * Состав фильтруется по permissions текущего пользователя — видны только
 * Resource/Action/Field, к которым есть доступ.
 *
 * @header string ?$If-None-Match Etag предыдущего ответа.
 *
 * @output object  $payload Манифест.
 * @output string  $payload.version Хэш (равен ETag).
 * @output string  $payload.locale Локаль.
 * @output array   $payload.resources Список Resource-схем.
 * @output array   $payload.screens Список Screen-схем.
 * @output array   $payload.settings Список SettingsResource-схем.
 * @output array   $payload.dashboards Список Dashboard-схем.
 * @output array   $payload.plugins Список зарегистрированных AdminPlugin'ов.
 * @output array   $payload.permissions Группы пермишенов для UI.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ManifestResponse}
 * @response 304 {NotModifiedResponse}
 * @response 401 {UnauthenticatedErrorResponse}
 */
public function manifest(Request $request): JsonResponse;
```

Header `ETag` ставится сервером. SPA при следующем запросе отправляет `If-None-Match: "<etag>"`. При совпадении — 304 без тела.

### `system.me`

```php
/**
 * Получить данные текущего администратора.
 *
 * @output object  $payload AdminUserSummary.
 * @output integer $payload.id ID.
 * @output string  $payload.name Имя.
 * @output string(email) $payload.email Email.
 * @output string  ?$payload.avatar URL аватара.
 * @output string  $payload.locale Локаль интерфейса.
 * @output string  $payload.theme Тема.
 * @output boolean $payload.twoFactorEnabled 2FA включена.
 * @output object  ?$payload.impersonator Если работаем под impersonation.
 * @output integer $payload.impersonator.id ID оригинального юзера.
 * @output string  $payload.impersonator.name Имя оригинала.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {AdminUserSummaryResponse}
 * @response 401 {UnauthenticatedErrorResponse}
 */
public function me(Request $request): JsonResponse;
```

### `system.menu`

```php
/**
 * Получить дерево меню сайдбара, отфильтрованное по permissions.
 *
 * @output object  $payload
 * @output array   $payload.items Список MenuItem.
 * @output string  $payload.items[].key Ключ.
 * @output string  $payload.items[].label Метка.
 * @output string  ?$payload.items[].icon Иконка.
 * @output string  ?$payload.items[].url URL (null для группы).
 * @output mixed   ?$payload.items[].badge Число/строка-бейдж (например, кол-во failed jobs).
 * @output array   ?$payload.items[].children Вложенные пункты.
 * @output integer $payload.items[].order Порядок сортировки.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {MenuResponse}
 * @response 401 {UnauthenticatedErrorResponse}
 */
public function menu(Request $request): JsonResponse;
```

### `system.locales`

```php
/**
 * Получить список доступных локалей admin.
 *
 * @output object $payload
 * @output array  $payload.available Доступные коды (ru, en, ...).
 * @output string $payload.current Текущая.
 * @output string $payload.fallback Fallback.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {LocalesResponse}
 */
public function locales(Request $request): JsonResponse;
```

### `system.permissions`

```php
/**
 * Получить плоский список permissions, сгруппированных через ItemPermission::group().
 * Используется в UI редактирования роли (матрица).
 *
 * @output object $payload
 * @output array  $payload.groups Группы.
 * @output string $payload.groups[].name Имя группы.
 * @output array  $payload.groups[].items Permissions в группе.
 * @output string $payload.groups[].items[].key Ключ permission.
 * @output string $payload.groups[].items[].label Локализованная метка.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {PermissionsResponse}
 * @response 403 {ForbiddenErrorResponse} Требуется admin.systems.roles.view.
 */
public function permissions(Request $request): JsonResponse;
```

### `system.plugins`

```php
/**
 * Получить список зарегистрированных AdminPlugin'ов с их версиями.
 * Используется для отладки (Scalar UI и dev-tools).
 *
 * @output object $payload
 * @output array  $payload.plugins Список.
 * @output string $payload.plugins[].id ID плагина.
 * @output string $payload.plugins[].version Версия.
 * @output array  $payload.plugins[].requires Список зависимых plugin-ID.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {PluginsResponse}
 * @response 403 {ForbiddenErrorResponse} Требуется admin.system.api-docs.
 */
public function plugins(Request $request): JsonResponse;
```

---

## Notifications

### `system.notifications`

```php
/**
 * Получить список нотификаций текущего пользователя с пагинацией.
 *
 * @input integer ?$page Номер страницы (default 1).
 * @input integer ?$per_page Размер страницы (default 20, max 100).
 * @input boolean ?$unread Только непрочитанные (default false).
 * @input string(date-time) ?$since Только новее указанной даты.
 *
 * @output object $payload
 * @output array  $payload.data Список AdminNotification.
 * @output string $payload.data[].id UUID.
 * @output string $payload.data[].type FQCN класса нотификации.
 * @output object $payload.data[].data Данные.
 * @output string $payload.data[].data.title Заголовок.
 * @output string $payload.data[].data.message Текст.
 * @output string ?$payload.data[].data.icon Иконка.
 * @output string ?$payload.data[].data.color info|success|warning|danger.
 * @output string ?$payload.data[].data.action_url Ссылка на источник.
 * @output string ?$payload.data[].data.action_label Метка кнопки.
 * @output string(date-time) ?$payload.data[].read_at Когда прочитано.
 * @output string(date-time) $payload.data[].created_at Создано.
 * @output object $payload.meta Пагинация.
 * @output integer $payload.unread_count Кол-во непрочитанных всего.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {NotificationsListResponse}
 */
public function notifications(Request $request): JsonResponse;
```

### `system.notificationsRead`

```php
/**
 * Пометить нотификацию прочитанной.
 *
 * @input string(uuid) $id ID нотификации.
 *
 * @output object $payload AdminNotification.
 * @output string $payload.id UUID.
 * @output string(date-time) $payload.read_at Когда прочитано.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {NotificationItemResponse}
 * @response 404 {NotFoundErrorResponse} Нотификация не принадлежит юзеру.
 */
public function notificationsRead(Request $request): JsonResponse;
```

### `system.notificationsMarkAllRead`

```php
/**
 * Пометить все непрочитанные нотификации текущего юзера как прочитанные.
 *
 * @output object $payload
 * @output integer $payload.affected Сколько нотификаций было обновлено.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {AffectedResponse}
 */
public function notificationsMarkAllRead(Request $request): JsonResponse;
```

### `system.notificationsDelete`

```php
/**
 * Удалить нотификацию (одну или все). Если id не передан, удаляются все
 * нотификации текущего юзера, в зависимости от флага only_read.
 *
 * @input string(uuid) ?$id ID конкретной нотификации.
 * @input boolean ?$only_read Удалить только прочитанные (default true). Игнорируется, если задан id.
 *
 * @output object $payload
 * @output integer $payload.affected Сколько удалено.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {AffectedResponse}
 * @response 404 {NotFoundErrorResponse} Конкретный id не принадлежит юзеру.
 */
public function notificationsDelete(Request $request): JsonResponse;
```

---

## Audit

### `system.audit`

```php
/**
 * Получить глобальный журнал аудита (требует admin.systems.audit.view).
 * Per-resource history доступен через {resource}.audit.
 *
 * @input integer ?$page
 * @input integer ?$per_page
 * @input integer ?$filter_user_id
 * @input string  ?$filter_event created|updated|deleted|restored|...
 * @input string  ?$filter_subject_type Morph-type сущности.
 * @input string(date-time) ?$filter_created_at_from
 * @input string(date-time) ?$filter_created_at_to
 * @input string  ?$q Free-text search (по message/old/new).
 *
 * @output object $payload
 * @output array  $payload.data Список AuditLogEntry.
 * @output integer $payload.data[].id
 * @output object  ?$payload.data[].user
 * @output integer $payload.data[].user.id
 * @output string  $payload.data[].user.name
 * @output string(email) $payload.data[].user.email
 * @output string  $payload.data[].event
 * @output string  ?$payload.data[].subject_type
 * @output mixed   ?$payload.data[].subject_id
 * @output object  ?$payload.data[].attributes
 * @output object  ?$payload.data[].old
 * @output object  ?$payload.data[].new
 * @output string  ?$payload.data[].ip
 * @output string  ?$payload.data[].user_agent
 * @output string(date-time) $payload.data[].created_at
 * @output object  $payload.meta Пагинация.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {AuditListResponse}
 * @response 403 {ForbiddenErrorResponse} Требуется admin.systems.audit.view.
 */
public function audit(Request $request): JsonResponse;
```
