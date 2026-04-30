# Фазы P0–P1: детализация

Первые 3.5 недели работы. Задача — довести проект до состояния, когда пользователь может написать минимальный Resource-класс и получить рабочий list/edit-экран в SPA через JSON-API.

> Roadmap всех фаз — в [../ARCHITECTURE.md](../ARCHITECTURE.md) раздел 12. Архитектура слоёв — раздел 5. Структура файлов — раздел 6.

---

## P0. Скаффолд (1 неделя)

### Статус

Большая часть сделана в commit'е `Initial scaffold`. Нужно дописать:

- artisan-команды (`admin:install`, `admin:user`, `admin:link`).
- AdminGuardRegistrar (стаб + регистрация в ServiceProvider).
- `.env.example` для Testbench.
- Обновить ServiceProvider, чтобы подключать команды.

### P0.1. Что уже есть

| Файл | Статус |
|---|---|
| `composer.json` | ✅ полный |
| `package.json` | ✅ полный |
| `LICENSE`, `README.md`, `CHANGELOG.md` | ✅ |
| `.gitignore`, `.gitattributes`, `.editorconfig` | ✅ |
| `phpunit.xml.dist`, `pint.json`, `phpstan.neon` | ✅ |
| `.github/workflows/ci.yml` | ✅ |
| `config/admin.php` | ✅ полный (с зашитыми решениями) |
| `routes/admin.php` | ✅ shell-route |
| `resources/views/shell.blade.php` | ✅ |
| `resources/ts/index.ts` | ✅ заглушка `createAdmin()` |
| `vite.config.ts`, `tsconfig.json` | ✅ |
| `src/AdminServiceProvider.php` | ✅ register/boot/publishes/route group |
| `src/Admin.php` | ✅ manager с resources/widgets/plugins |
| `src/Facades/Admin.php` | ✅ |
| `src/Http/Controllers/ShellController.php` | ✅ |
| `src/Http/Middleware/{AdminAuth,AdminLocale,AdminCspNonce}.php` | ✅ скелеты |
| `tests/{Pest.php, TestCase.php, Unit/SmokeTest.php, Feature/ShellRouteTest.php}` | ✅ |
| `packages/{starter,tinymce,quill,search,media,health,pulse,jobs}/` | ✅ stubs |

### P0.2. Что нужно дописать (≈3 рабочих дня)

#### Day 1: artisan-команды

**`src/Console/InstallCommand.php`** (новый):

```php
final class InstallCommand extends Command
{
    protected $signature   = 'admin:install {--force}';
    protected $description = 'Установить laravel-admin: публикация конфига, миграций, ассетов; интерактивные вопросы по auth-стратегии';

    public function handle(): int { /* ... */ }
}
```

Behaviour:
- `vendor:publish --tag=admin-config --tag=admin-migrations`.
- Interactive вопросы (через Laravel Prompts):
  - `path` (default `admin`).
  - `domain` (опционально).
  - `auth.strategy` (`dedicated` / `shared`).
  - При `shared` — `auth.guard`, `auth.model` (FQCN существующего User).
  - «Запустить миграции?» (y/n).
  - «Создать суперюзера сейчас?» (y/n → вызов `admin:user` interactive).
- Запись выбранных значений в `config/admin.php` (или `.env` через AdminEnvWriter helper).

**`src/Console/MakeAdminCommand.php`** (новый):

```php
final class MakeAdminCommand extends Command
{
    protected $signature   = 'admin:user {name?} {email?} {password?} {--super}';
    protected $description = 'Создать администратора';

    public function handle(): int { /* ... */ }
}
```

Behaviour:
- Если args не переданы — Laravel Prompts (text/email/password).
- При `--super` (или интерактивный y/n) — назначить системную роль `Super Admin` (или просто пометить `is_super=true` если роли ещё нет).
- На фазе P0 ролей ещё нет → команда работает только с базовыми полями (name, email, password) + флаг.

**`src/Console/LinkCommand.php`** (новый):

```php
final class LinkCommand extends Command
{
    protected $signature   = 'admin:link';
    protected $description = 'Симлинк скомпилированных ассетов admin → public/vendor/admin';

    public function handle(): int { /* ... */ }
}
```

Behaviour:
- Создаёт симлинк `public/vendor/admin` → `vendor/dskripchenko/laravel-admin/dist` (или `node_modules/@dskripchenko/laravel-admin/dist`).
- На локальном dev (когда пакет ставится через path-repo) — сразу указывает на `dist/` рабочей копии.

#### Day 2: AdminGuardRegistrar + bootstrap-update

**`src/Auth/AdminGuardRegistrar.php`** (новый, stub):

```php
final class AdminGuardRegistrar
{
    public function register(): void
    {
        if ((string) config('admin.auth.strategy') !== 'dedicated') {
            return;
        }

        $guard    = (string) config('admin.auth.guard', 'admin');
        $provider = (string) config('admin.auth.provider', 'admin_users');
        $model    = config('admin.auth.model');
        $table    = (string) config('admin.auth.table', 'admin_users');

        // Auth::extend без правки config/auth.php host-проекта
        config([
            "auth.guards.{$guard}" => [
                'driver'   => 'session',
                'provider' => $provider,
            ],
            "auth.providers.{$provider}" => [
                'driver' => 'eloquent',
                'model'  => $model,
                'table'  => $table,
            ],
            "auth.passwords.{$provider}" => [
                'provider' => $provider,
                'table'    => 'admin_password_resets',
                'expire'   => 60,
                'throttle' => 60,
            ],
        ]);
    }
}
```

Подключить в `AdminServiceProvider::boot()` после `mergeConfigFrom`.

Обновить `commands()` в Provider для регистрации трёх новых команд.

**Базовая модель:**

**`src/Models/AdminUser.php`** (новый, скелет):

```php
final class AdminUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'admin_users';
    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];
    protected $casts  = ['email_verified_at' => 'datetime'];
}
```

**Миграции:**

- `database/migrations/2026_01_01_000001_create_admin_users_table.php` (только базовый набор без 2FA-колонок — те добавит P2 отдельной миграцией).

#### Day 3: smoke-тесты + полировка

**`tests/Feature/InstallCommandTest.php`** (новый):

```php
it('publishes config on admin:install', function () {
    $this->artisan('admin:install --force')
        ->expectsConfirmation('Запустить миграции?', 'no')
        ->expectsConfirmation('Создать суперюзера сейчас?', 'no')
        ->assertSuccessful();

    expect(file_exists(config_path('admin.php')))->toBeTrue();
});
```

**`tests/Feature/MakeAdminCommandTest.php`** (новый):

```php
it('creates admin user', function () {
    $this->artisan('admin:user "Test Admin" admin@test.com secret')
        ->assertSuccessful();

    $user = AdminUser::where('email', 'admin@test.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Test Admin');
});
```

Расширить существующий `tests/Unit/SmokeTest.php`:

```php
it('registers AdminGuardRegistrar in dedicated mode', function () {
    config(['admin.auth.strategy' => 'dedicated']);
    expect(config('auth.guards.admin'))->toBeArray();
    expect(config('auth.providers.admin_users.model'))
        ->toBe(\Dskripchenko\LaravelAdmin\Models\AdminUser::class);
});
```

### P0.3. Acceptance criteria

- [x] `composer install` без ошибок.
- [x] `npm install && npm run build` без ошибок (production-bundle dist/index.mjs существует).
- [x] `vendor/bin/pest` проходит (smoke + ShellRoute).
- [ ] `php artisan admin:install` отрабатывает, публикует config, спрашивает auth-стратегию, по выбору запускает migrate.
- [ ] `php artisan admin:user "Name" "email@..." "secret"` создаёт запись в `admin_users`.
- [ ] `php artisan admin:link` создаёт симлинк `public/vendor/admin`.
- [ ] `GET /admin` отвечает 200 с пустым SPA-shell'ом, в `<head>` присутствует `window.__ADMIN_BOOTSTRAP__`.
- [ ] CI green: pint, phpstan, pest, vitest, vue-tsc.

---

## P1. Backbone (2.5 недели ≈ 12 рабочих дней)

Цель — рабочий минимальный Resource-класс с list+edit-страницами, отдающий схему в SPA через JSON-манифест и общающийся с фронтом через JSON-API в формате `dskripchenko/laravel-api`.

«Smoke-test «Hello, Resource»»: пользователь пишет `UserResource extends Resource`, регистрирует через `Admin::resources([...])`, открывает `/admin/resources/users` — видит таблицу, открывает edit — видит форму, сохраняет — данные пишутся.

### P1.1. Layout abstracts (день 1–2)

| Файл | Задача |
|---|---|
| `src/Layout/Layout.php` | Абстрактный базовый класс. Method'ы: `id()`, `props(): array`, `children(): array`, `toJson(): array`, `canSee(callable\|bool): self`, `__call` для extension'ов |
| `src/Layout/LayoutFactory.php` | Статические фабрики: `Layout::rows`, `columns`, `tabs`, `accordion`, `block`, `view`, `wrapper`, `modal`, `drawer`, `wizard`, `metrics`, `chart` |
| `src/Layout/Rows.php` | Принимает массив Field'ов, рендерится как `<UiForm>` |
| `src/Layout/Columns.php` | Принимает массив layout'ов, props: ratios |
| `src/Layout/Tabs.php` | Map `label => layout`, props: defaultTab |
| `src/Layout/Accordion.php` | То же, что Tabs, но с collapse-state |
| `src/Layout/Block.php` | Карточка с title/description/commandBar |
| `src/Layout/View.php` | Произвольный Vue-компонент с props |
| `src/Layout/Wrapper.php` | Кастомный Blade или Vue с slot'ами |
| `src/Layout/Modal.php` | (skeleton — полная реализация в P7) |
| `src/Layout/Drawer.php` | (skeleton — P7) |
| `src/Layout/Wizard.php`, `Step.php` | (skeleton — P7) |
| `src/Layout/Metrics.php`, `Chart.php` | (skeleton — P8) |
| `src/Layout/Table.php` | (skeleton — P6) |

**Acceptance:**

- [ ] `Layout::rows([Input::make('name')])->toJson()` возвращает корректный JSON со схемой.
- [ ] Композиция работает: `Layout::tabs(['Tab1' => Layout::rows([...])])`.
- [ ] `canSee(false)` исключает layout из serialization.
- [ ] Unit-тесты на каждый базовый Layout.

### P1.2. Field abstracts (день 3–4)

| Файл | Задача |
|---|---|
| `src/Field/Field.php` | Абстрактный базовый. `__call` → `$attributes[$method] = $args[0]`. Method'ы: `make($name)`, `name()`, `value($value)`, `default($value)`, `required($cond)`, `rules($rules)`, `canSee()`, `onCreate()`, `onUpdate()`, `onView()`, `placeholder`, `help`, `title`, `disabled`, `reactive`, `reloadFor`, `toJson()` |
| `src/Field/Input.php` | type=`text` (default). Опции: `type('email\|url\|tel\|...')`, `mask`, `prefix`, `suffix` |
| `src/Field/Hidden.php` | Невидимое поле для transport `id`/_token-like |
| `src/Field/Label.php` | Read-only display |

> Остальные Field-типы (Number, Password, Textarea, Select, ...) — фаза P4 и P5. На P1 нужны только три выше для smoke.

**Acceptance:**

- [ ] `Input::make('email')->type('email')->required()->toJson()` корректный.
- [ ] Fluent-API через __call работает.
- [ ] Unit-тесты на JSON-сериализацию Field.

### P1.3. Action и Filter abstracts (день 5)

| Файл | Задача |
|---|---|
| `src/Action/Action.php` | Абстрактный, статика `make($label)`. Methods: `label`, `icon`, `color`, `confirm`, `permission`, `method`, `primary`, `destructive`, `position`, `parameters([...])`, `toJson()` |
| `src/Action/Button.php` | Простой POST на screen-method |
| `src/Action/Link.php` | Внешняя ссылка (target, href) |
| `src/Filter/Filter.php` | Абстрактный, methods: `for($field)`, `default($value)`, `display(): array<Field>`, `apply(Builder, value): Builder`, `toJson()` |
| `src/Filter/InputFilter.php` | LIKE-поиск по полю |
| `src/Filter/SwitcherFilter.php` | Boolean фильтр |

> Остальные Filter'ы (DateRange/SelectFromModel/Query/Options) и Action'ы (DropDown/Modal/Bulk/Async/...) — P6 и P12.

**Acceptance:**

- [ ] `Button::make('Сохранить')->method('save')->toJson()` корректный.
- [ ] `InputFilter::for('email')` применяется к Builder через `apply()`.

### P1.4. Repository (dot-state) и State serialization (день 6)

**`src/Support/Repository.php`** (новый):

```php
final class Repository
{
    public function __construct(private array $data = []) {}

    public function get(string $key, mixed $default = null): mixed { /* dot-notation */ }
    public function set(string $key, mixed $value): self { /* */ }
    public function has(string $key): bool { /* */ }
    public function merge(array|Repository $other): self { /* */ }
    public function only(array $keys): self { /* */ }
    public function except(array $keys): self { /* */ }
    public function toArray(): array { /* */ }
    public function toJson(int $options = 0): string { /* */ }
}
```

Использует `Dskripchenko\PhpArrayHelper\ArrayHelper::get/set` под капотом.

**`src/Support/DotState.php`** — alias к Repository для обратной совместимости (можно убрать).

**Acceptance:**

- [ ] Unit-тесты на dot-notation get/set/has/merge.
- [ ] Тест: `Repository::set('user.address.city', 'Moscow')->get('user.address.city') === 'Moscow'`.

### P1.5. Screen abstract + ScreenRouter (день 7–8)

**`src/Screen/Screen.php`** (новый, абстрактный):

```php
abstract class Screen
{
    public function name(): string                  { return static::class; }
    public function description(): ?string          { return null; }
    public function permission(): array|string|null { return null; }

    abstract public function query(...$params): array|Repository;

    public function commandBar(): iterable          { return []; }

    abstract public function layout(): iterable;

    final public function compile(...$params): array
    {
        // запускает query → собирает state → собирает layout → возвращает payload для SystemController
    }
}
```

**`src/Screen/ScreenRouter.php`** (новый):

```php
final class ScreenRouter
{
    public static function register(): void
    {
        Route::macro('adminScreen', function (string $uri, string $screenClass): RouteAction {
            return Route::any($uri, fn (Request $r) => /* resolve screen, execute method */);
        });
    }
}
```

В `AdminServiceProvider::boot()` зовём `ScreenRouter::register()`.

**`src/Screen/BaseListScreen.php`** (новый, базовый класс для list-режима Resource):

```php
abstract class BaseListScreen extends Screen
{
    abstract public function resource(): string; // FQCN Resource

    public function query(): Repository
    {
        $resource = app($this->resource());
        $records  = $resource->indexQuery()->paginate(/* per_page */);
        return new Repository(['records' => $records]);
    }

    public function layout(): iterable
    {
        return [Layout::table(/* columns from resource */)];
    }
}
```

**`src/Screen/BaseEditScreen.php`** (новый, аналогично):

```php
abstract class BaseEditScreen extends Screen
{
    abstract public function resource(): string;

    public function query(string|int $id): Repository
    {
        $resource = app($this->resource());
        $record   = $resource->modelQuery()->findOrFail($id);
        return new Repository(['record' => $record->toArray()]);
    }

    public function layout(): iterable
    {
        return [Layout::rows(/* fields from resource */)];
    }

    public function save(Request $request, string|int $id) { /* ... */ }
}
```

**Acceptance:**

- [ ] Unit-тест: Screen с query/layout компилируется в JSON через SystemController.
- [ ] Unit-тест: `Route::adminScreen('foo', FooScreen::class)` регистрирует роут.

### P1.6. Resource abstract + ResourceCompiler + ResourceRegistry (день 9–10)

**`src/Resource/Resource.php`** (новый, абстрактный):

```php
abstract class Resource
{
    public static string $model;
    public static string $icon  = 'cube';
    public static ?string $group = null;

    public static function slug(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    public static function permission(): ?string { return null; }

    public function fields(): array       { return []; }
    public function columns(): array      { return []; }
    public function filters(): array      { return []; }
    public function actions(): array      { return []; }
    public function rules(string $context = 'create'): array { /* собирается из fields */ }

    public function indexQuery(): Builder { return static::$model::query(); }
    public function modelQuery(): Builder { return static::$model::query(); }

    public function searchableFields(): array { return []; }
    public function with(): array { return []; }
}
```

На P1 нужен только минимальный набор. Расширения (`softDeletes()`, `replicable()`, `reorderable()`, `importable()`, `exports()`, `infolist()`) — поздние фазы.

**`src/Resource/ResourceCompiler.php`** (новый):

```php
final class ResourceCompiler
{
    public function compile(string $resourceClass): CompiledResource
    {
        // создаёт три virtual screen-класса (List/Edit/Create) на лету через runtime-bind
        // регистрирует роуты через Route::adminScreen()
        // собирает manifest-payload
    }
}
```

На P1 — только манифест и роуты. Виртуальные screen'ы могут быть простыми anonymous-классами через factory.

**`src/Resource/ResourceRegistry.php`** (новый):

```php
final class ResourceRegistry
{
    /** @var array<string, CompiledResource> */
    private array $compiled = [];

    public function add(string $resourceClass): void { /* compile + index by slug */ }
    public function all(): array { return $this->compiled; }
    public function get(string $slug): ?CompiledResource { /* */ }
    public function manifest(?AdminUser $user = null): array { /* permissions-фильтрация */ }
}
```

Обновить `Admin` manager: `resources([...])` → `ResourceRegistry::add(...)`.

**Acceptance:**

- [ ] Resource'ы регистрируются и видны в `ResourceRegistry::all()`.
- [ ] Каждый Resource добавляет роуты `/api/admin/resources/{slug}` (list/show/create/update/delete).
- [ ] Manifest содержит все зарегистрированные Resource'ы с permission-фильтрацией.

### P1.7. Manifest builder (день 11)

**`src/Support/Manifest.php`** (новый):

```php
final class Manifest
{
    public function __construct(
        private ResourceRegistry $resources,
        private ScreenRegistry $screens,
        private MenuRegistry $menu,
        private PermissionRegistry $permissions,
        private PluginRegistry $plugins,
    ) {}

    public function build(?AdminUser $user, string $locale): array
    {
        return [
            'version'    => $this->version($user, $locale),
            'locale'     => $locale,
            'resources'  => $this->resources->manifest($user),
            'screens'    => $this->screens->manifest($user),
            'plugins'    => $this->plugins->manifest(),
            'permissions' => $this->permissions->manifest(),
        ];
    }

    public function version(?AdminUser $user, string $locale): string
    {
        // hash от сериализованного payload + admin version + user permissions
        return hash('sha256', /* ... */);
    }
}
```

**Acceptance:**

- [ ] Manifest сериализуется в JSON, размер контролируем.
- [ ] Version-hash меняется при добавлении Resource или изменении locale/permissions.

### P1.8. AdminApi + AdminApiModule + SystemController (день 12)

**`src/Http/AdminApiModule.php`** (новый, реализует `Dskripchenko\LaravelApi\BaseModule`):

```php
final class AdminApiModule extends BaseModule
{
    public function getApiVersionList(): array
    {
        return ['admin' => AdminApi::class];
    }
}
```

**`src/Http/AdminApi.php`** (новый, реализует `BaseApi`):

```php
final class AdminApi extends BaseApi
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'system' => [
                    'controller' => SystemController::class,
                    'actions'    => ['bootstrap', 'manifest', 'me', 'menu', 'locales', 'permissions', 'plugins'],
                ],
                // resources/screens/profile/auth/uploads/delayed добавляются в поздние фазы
            ],
        ];
    }
}
```

**`src/Http/Controllers/SystemController.php`** (новый, расширяет `Dskripchenko\LaravelApi\ApiController`):

```php
final class SystemController extends ApiController
{
    public function bootstrap(Request $request): JsonResponse { /* возвращает данные для SPA */ }
    public function manifest(Request $request, Manifest $manifest): JsonResponse { /* etag-aware */ }
    public function me(Request $request): JsonResponse { /* AdminUserSummary */ }
    public function menu(Request $request, MenuRegistry $menu): JsonResponse { /* */ }
    public function locales(): JsonResponse { /* */ }
    public function permissions(PermissionRegistry $registry): JsonResponse { /* */ }
    public function plugins(PluginRegistry $registry): JsonResponse { /* */ }
}
```

Регистрация роутов через AdminApiModule в `AdminServiceProvider::boot()`:

```php
Route::group([
    'prefix'     => config('admin.path') . '/' . config('admin.api_path'),
    'middleware' => config('admin.middleware.api'),
], function () {
    AdminApiModule::register('admin', new AdminApi());
});
```

**Acceptance:**

- [ ] `GET /api/admin/system/manifest` отвечает 200 с manifest-payload (envelope `{success, payload}`).
- [ ] `GET /api/admin/system/me` отвечает корректным `AdminUserSummary` либо 401.
- [ ] `If-None-Match` корректно отдаёт 304.

### P1.9. SPA-каркас: client, router, renderers (день 13–14)

**`resources/ts/api/client.ts`** (новый):

```typescript
import axios, { type AxiosInstance } from 'axios'

export function createApiClient(baseURL: string, csrfToken: string): AxiosInstance {
  const client = axios.create({ baseURL, withCredentials: true })
  client.defaults.headers.common['X-XSRF-TOKEN'] = csrfToken
  client.defaults.headers.common['Accept'] = 'application/json'

  // Envelope unwrap
  client.interceptors.response.use(
    (response) => {
      if (response.data?.success === true) {
        response.data = response.data.payload
        return response
      }
      return Promise.reject(new ApiError(response.data?.payload))
    },
    (error) => Promise.reject(new ApiError(error.response?.data?.payload, error)),
  )

  return client
}
```

**`resources/ts/api/interceptors.ts`** (новый):

```typescript
import { applyAxiosInterceptor } from '@dskripchenko/laravel-delayed-process/web'

export function setupDelayedInterceptor(client: AxiosInstance, baseURL: string) {
  applyAxiosInterceptor(client, {
    statusUrl: `${baseURL}/system/delayed/status`,
    pollingInterval: 2000,
  })
}
```

**`resources/ts/api/manifest.ts`** (новый):

```typescript
export class ManifestStore {
  async load(force = false): Promise<Manifest> {
    const cached = this.readCache()
    if (cached && !force) return cached

    const response = await client.get('/system/manifest', {
      headers: cached ? { 'If-None-Match': `"${cached.version}"` } : {},
    })
    if (response.status === 304) return cached!

    this.writeCache(response.data)
    return response.data
  }

  private readCache(): Manifest | null { /* localStorage */ }
  private writeCache(manifest: Manifest): void { /* localStorage */ }
}
```

**`resources/ts/router/index.ts`** (новый):

```typescript
export function createAdminRouter(baseUrl: string): Router {
  return createRouter({
    history: createWebHistory(baseUrl),
    routes: [
      { path: '/login', component: () => import('../screens/LoginScreen.vue') },
      { path: '/resources/:slug', component: () => import('../screens/ListScreen.vue') },
      { path: '/resources/:slug/create', component: () => import('../screens/CreateScreen.vue') },
      { path: '/resources/:slug/:id', component: () => import('../screens/EditScreen.vue') },
      { path: '/screens/:name', component: () => import('../screens/CustomScreen.vue') },
      { path: '/:catchAll(.*)', redirect: '/' },
    ],
  })
}
```

**`resources/ts/stores/auth.ts`, `manifest.ts`, `menu.ts`** (новые):

Pinia-сторы. Минимум: `useAuthStore`, `useManifestStore`. Менеджмент-сторы (notifications, theme, alerts) — поздние фазы.

**`resources/ts/layouts/LayoutRenderer.vue`** (новый):

```vue
<script setup lang="ts">
import { computed } from 'vue'
import RowsLayout from './RowsLayout.vue'
import ColumnsLayout from './ColumnsLayout.vue'
import TabsLayout from './TabsLayout.vue'
// ...

const props = defineProps<{ schema: LayoutSchema; state: Record<string, unknown> }>()

const Component = computed(() => {
  switch (props.schema.type) {
    case 'rows': return RowsLayout
    case 'columns': return ColumnsLayout
    case 'tabs': return TabsLayout
    case 'block': return BlockLayout
    case 'view': return ViewLayout
    default: return UnknownLayout
  }
})
</script>

<template>
  <component :is="Component" :schema="schema" :state="state" />
</template>
```

**`resources/ts/fields/FieldRenderer.vue`** (новый):

```vue
<script setup lang="ts">
import InputField from './InputField.vue'
// ...

const props = defineProps<{ schema: FieldSchema; modelValue: unknown }>()
const emit = defineEmits<{ 'update:modelValue': [unknown] }>()

const Component = computed(() => {
  switch (props.schema.type) {
    case 'input': return InputField
    case 'hidden': return HiddenField
    // ... other field types arrive in P4/P5
    default: return UnknownField
  }
})
</script>
```

**`resources/ts/fields/InputField.vue`** (новый):

```vue
<script setup lang="ts">
import { UiInput } from '@dskripchenko/ui'

const props = defineProps<{ schema: InputFieldSchema; modelValue: string | null }>()
const emit = defineEmits<{ 'update:modelValue': [string | null] }>()
</script>

<template>
  <UiInput
    :type="schema.options?.type ?? 'text'"
    :model-value="modelValue"
    @update:model-value="(v) => emit('update:modelValue', v)"
    :placeholder="schema.placeholder"
    :required="schema.required"
  />
</template>
```

**`resources/ts/screens/ListScreen.vue`, `EditScreen.vue`, `CreateScreen.vue`** (новые, минимальные).

ListScreen рендерит `<UiTable>` поверх данных, полученных из `/resources/{slug}`. EditScreen — `<LayoutRenderer>` поверх данных из `/resources/{slug}/{id}`.

**Acceptance:**

- [ ] `npm run build` производит `dist/` без ошибок.
- [ ] При открытии `/admin/resources/users` SPA фетчит manifest и list, рендерит таблицу.
- [ ] Vitest-тесты на ManifestStore (cache hit/miss/304-response).

### P1.10. End-to-end smoke (день 12)

Создать demo-Resource в `tests/Fixtures/`:

```php
// tests/Fixtures/Resources/UserResource.php
final class UserResource extends Resource
{
    public static string $model = TestUserModel::class;

    public function fields(): array
    {
        return [
            Field\Input::make('name')->required(),
            Field\Input::make('email')->type('email')->required(),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort(),
            TableColumn::make('name')->sort()->search(),
            TableColumn::make('email')->copyable(),
        ];
    }
}
```

**`tests/Feature/HelloResourceTest.php`** (новый):

```php
beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../Fixtures/migrations');
    Admin::resources([UserResource::class]);
    $this->actingAs(AdminUser::factory()->withSuperAccess()->create(), 'admin');
});

it('returns Resource in manifest', function () {
    $response = $this->getJson('/api/admin/system/manifest');

    $response->assertSuccessful();
    expect($response->json('payload.resources'))->toHaveCount(1);
    expect($response->json('payload.resources.0.slug'))->toBe('user-resource');
    expect($response->json('payload.resources.0.fields'))->toHaveCount(2);
    expect($response->json('payload.resources.0.columns'))->toHaveCount(3);
});

it('returns Resource list', function () {
    TestUserModel::factory()->count(3)->create();

    $response = $this->getJson('/api/admin/resources/user-resource');

    $response->assertSuccessful();
    expect($response->json('payload.data'))->toHaveCount(3);
});

it('shows a single record', function () {
    $user = TestUserModel::factory()->create(['name' => 'John']);

    $response = $this->getJson("/api/admin/resources/user-resource/{$user->id}");

    $response->assertSuccessful();
    expect($response->json('payload.record.name'))->toBe('John');
});

it('creates a record', function () {
    $response = $this->postJson('/api/admin/resources/user-resource', [
        'name'  => 'New User',
        'email' => 'new@example.com',
    ]);

    $response->assertSuccessful();
    expect(TestUserModel::where('email', 'new@example.com')->exists())->toBeTrue();
});

it('updates a record', function () {
    $user = TestUserModel::factory()->create();

    $response = $this->patchJson("/api/admin/resources/user-resource/{$user->id}", [
        'name' => 'Updated',
    ]);

    $response->assertSuccessful();
    expect($user->fresh()->name)->toBe('Updated');
});

it('deletes a record', function () {
    $user = TestUserModel::factory()->create();

    $response = $this->deleteJson("/api/admin/resources/user-resource/{$user->id}");

    $response->assertSuccessful();
    expect(TestUserModel::find($user->id))->toBeNull();
});
```

Это «smoke-test «Hello, Resource»» из roadmap.

### P1.11. Acceptance criteria всей фазы

- [ ] Все unit-тесты на Layout/Field/Action/Filter/Repository проходят.
- [ ] `Admin::resources([UserResource::class])` регистрирует Resource.
- [ ] `GET /api/admin/system/manifest` возвращает Resource в payload.
- [ ] `GET /api/admin/resources/{slug}` возвращает list через `paginate()`.
- [ ] `GET /api/admin/resources/{slug}/{id}` возвращает одну запись.
- [ ] `POST /api/admin/resources/{slug}` создаёт запись.
- [ ] `PATCH /api/admin/resources/{slug}/{id}` обновляет.
- [ ] `DELETE /api/admin/resources/{slug}/{id}` удаляет.
- [ ] SPA при открытии `/admin/resources/users` показывает таблицу с данными.
- [ ] SPA при клике на строку открывает edit-страницу с формой.
- [ ] Сохранение формы отправляет PATCH и обновляет данные.
- [ ] CI green (pint, phpstan level 6, pest, vitest, vue-tsc).

После P1 пакет уже **минимально полезен** — пользователь может писать Resource и получать работающий CRUD. Все остальные фазы (P2–P20) расширяют функциональность.

---

## Зависимости между сделанным в P0/P1 и следующими фазами

| Фаза | Что нужно из P1 |
|---|---|
| P2 (Auth) | AdminGuardRegistrar (P0), AdminUser (P0), SystemController (P1) |
| P3 (Resource v1 advanced) | Resource abstract (P1), ResourceCompiler (P1) |
| P4 (Базовые Field) | Field abstract (P1), FieldRenderer (P1) |
| P6 (Tables) | TableColumn (новый), Filter abstract (P1), HttpFilterParser (новый) |
| P7 (Layouts/primitives) | Layout abstract (P1), Wizard skeleton (P1) |
| P8 (Widgets/Dashboard) | Manifest builder (P1) |
| P11 (Settings + Plugins) | ResourceRegistry (P1), Manifest (P1) |
| P12 (Actions advanced) | Action abstract (P1), ScreenRouter (P1) |
| P13 (Export/Import) | ResourceCompiler (P1), Wizard (P1) |
| P15 (Notifications + API tokens) | SystemController (P1), AdminUser (P0) |
| P17 (Bootstrap) | ShellController (P0), AdminCspNonce (P0) |

---

## Что **не входит** в P0/P1 (часто хочется добавить, но это поздние фазы)

- ❌ Validation rules с локализованными messages — частично P1 (на уровне rules-сериализатора), полная — P4.
- ❌ Auth (login, logout, 2FA, profile) — P2.
- ❌ Permissions middleware — P2.
- ❌ Audit log — P10.
- ❌ Notifications — P15.
- ❌ Themes (light/dark switcher) — P16.
- ❌ Real-data Resource'ы (только smoke-test fixture в `tests/Fixtures/`) — host-проект пишет свои.
- ❌ Полный набор Field-типов (Number/Password/Textarea/Select/...) — P4.
- ❌ Reactive fields (`->reactive()`), saved views, inline-edit — P6.
- ❌ Soft-delete, Replicate, Reorder — P9.

Если что-то из этого требуется раньше — обсуждаем переброску фаз.

---

## Риски P1

| Риск | Вероятность | Митигация |
|---|---|---|
| `laravel-api` API оказался не таким, как мы предположили (BaseModule/BaseApi) | средняя | проверить README пакета и пробежаться по `src/` перед стартом, при необходимости — мини-итерация на адаптер |
| Vite-конфиг lib-mode не идеально дружит с `@dskripchenko/ui` peerDependency | низкая | externals + bundleAnalyzer |
| Manifest-сериализатор оказывается тяжёлым на 50+ Resource'ов | низкая для P1 (там 1 demo Resource) | оптимизация по факту в P3 |
| SPA Vue-router не находит manifest при первой загрузке | средняя | строгая последовательность: bootstrap → manifest → router |
