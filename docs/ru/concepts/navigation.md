---
title: Навигация — счётчики, избранное, поиск, вкладки
audience: developer
status: stable
locale: ru
translated_from: en/concepts/navigation.md
translated_at: 2026-11-13
---

# Навигация

С 1.73 панель умеет больше, чем дерево меню: счётчики очередей, которые
считаются на запросе и обновляются сами, свёрнутые группы с запоминанием,
избранное и недавнее, командную палитру Ctrl+K со своими поисками, бейджи
вкладок, условие и фильтры по умолчанию у встроенного списка, «+ Создать»,
экран во вкладке другого экрана, периоды рабочего стола, маскирование
персональных данных и компактные хлебные крошки. Всё — по желанию: без
настройки панель ведёт себя как раньше.

Для избранного, недавнего и свёрнутых групп нужна миграция пакета
`admin_navigation_states` — `php artisan migrate`.

## Настройки

```php
// config/admin.php
'navigation' => [
    'poll_interval' => 60,       // секунд между опросами счётчиков меню; 0 — не опрашивать
    'groups_expanded' => false,  // группы меню раскрыты, пока пользователь не свернёт
    'sum_badges' => false,       // свёрнутая группа показывает сумму счётчиков
    'favorites_limit' => 7,      // сколько страниц в избранном
    'recent_limit' => 10,        // сколько страниц в недавнем
    'breadcrumbs' => 'full',     // 'compact' — Область › Экран › Вкладка › Запись
],
```

## 1. Счётчики в меню

`MenuNode::badge()` принимает замыкание. Оно вызывается при каждой отдаче
меню (`system/menu`) с вошедшим пользователем — и только если пункт ему виден
по правам, — поэтому middleware, подставлявший числа после входа, больше не
нужен:

```php
MenuNode::resource('client-claim-requests')->badge(
    fn (?Model $user): int => ClientClaimRequest::whereNull('sent_at')->count(),
    'danger',                    // красный бейдж; null — обычный
),
MenuNode::screen('applications')->badge(fn () => [
    'value' => $crm + $prolongation,
    'tone' => $overdue > 0 ? 'danger' : null,
]),
```

Замыкание возвращает число, текст, `null` (без бейджа) или
`['value' => …, 'tone' => …]`. Исключение в нём не ломает меню — бейдж просто
не показывается, а ошибка уходит в `report()`. Число больше 99 показывается
как `99+`.

Панель опрашивает только счётчики — `GET system/menuCounters` отдаёт
`{badges: {ключ пункта: {badge, tone}}}` для пунктов с замыканием — раз в
`poll_interval` секунд и при возврате на вкладку браузера. Дерево, подписи и
права не перезапрашиваются. Кешировать дорогие запросы — дело замыкания
(`Cache::remember(...)`).

**Сумма в группу.** `->sumBadges()` у группы или области (или
`navigation.sum_badges` для всех) — свёрнутая группа показывает сумму
числовых бейджей потомков, красную, если красный хоть один. Раскрытая — нет:
числа видны у пунктов.

## 2. Группы и закреплённые пункты

```php
$admin->menu()
    ->add(MenuNode::section('Закреплено', [
        MenuNode::dashboard('desktop'),
        MenuNode::screen('contracts'),
    ]))
    ->add(MenuNode::make('clients', 'Клиенты')->expanded()->children([
        MenuNode::make('queues', 'Очереди')->children([...]),
        MenuNode::make('client-dicts', 'Справочники')->collapsed()->children([...]),
    ]));
```

- `MenuNode::section($title, $children)` — секция корня с заголовком: её
  пункты идут плоско под заголовком, секция не ссылка и не сворачивается,
  в хлебных крошках её нет. Остальные корневые узлы (области) — без заголовка.
- `->collapsed()` / `->expanded()` — начальное состояние группы. Без них
  действует `navigation.groups_expanded` (по умолчанию свёрнуты, как раньше).
  Группа открытой страницы раскрывается всегда.
- Пользователь свернул или раскрыл группу — выбор сохраняется на сервере
  (`POST navigation/group {key, open}`) и приходит с меню (`groups`), в
  другом браузере тоже.

## 3. Избранное и недавнее

**Звёздочка** в верхней панели рядом с хлебными крошками — у любой страницы:
экрана, списка, записи, вкладки. Сохраняется адрес с query — открытая
вкладка (`?tab=crm`), фильтры списка, — а подпись берётся из крошек
(«Заявки · CRM»). Избранное — блок «Избранное» над меню, порядок меняется
перетаскиванием или Alt+↑ / Alt+↓, крестик убирает. Не больше
`favorites_limit` страниц.

**Недавнее** — последние `recent_limit` открытых экранов и записей (запись —
с заголовком и названием ресурса); одна строка на адрес без query. Видно в
палитре Ctrl+K при пустом запросе.

Хранится на сервере (`admin_navigation_states`, по пользователю и панели).
Перед показом каждый адрес проверяется правами *сейчас* (`UrlAccess`):
`/r/{slug}` — `.view` ресурса (`/create` — `.create`, `/{id}/edit` —
`.update`), `/screens/{slug}` и `/dashboard/{slug}` — правило самого экрана,
`/settings/{slug}` — `.view`. Недоступное не показывается, но остаётся
сохранённым — вернётся с правом. Адрес вне панели не принимается.

API: `GET navigation/state`, `POST navigation/favorite {url, label, icon?}`,
`navigation/unfavorite {url}`, `navigation/reorderFavorites {urls}`,
`navigation/visit {url, label, subtitle?, kind?}`, `navigation/group {key, open}`.

## 4. Поиск Ctrl+K

Палитра открывается по ⌘K / Ctrl+K или кликом по полю поиска. ↑/↓ — выбор,
Enter — переход, Esc — закрыть. В выдаче:

- пустой запрос — «Избранное» и «Недавнее»;
- **Разделы** — пункты меню (по подписи и пути: «очереди заявки») и
  **вкладки экранов** меню, которые хранятся в адресе (`persistInQuery()`):
  «Заявки › CRM» ведёт на `/screens/applications?tab=crm`; ищутся и по ключу
  вкладки (`crm`). Список вкладок приходит один раз — `GET system/menuTabs`;
- **Действия** — пункты «+ Создать» (см. п. 6): «создать новость»;
- **записи** ресурсов (`->search()` колонки) и **ваши поиски**.

Свой поиск регистрируется с распознавателем ввода и приоритетом:

```php
use Dskripchenko\LaravelAdmin\Support\Mask;
use Dskripchenko\LaravelAdmin\Support\SearchProvider;

$admin->searchProviders([
    SearchProvider::make('contracts', 'Договоры')
        ->recognizes('/^\d{3}-\d{6,}$/')         // номер полиса: группа наверх
        ->priority(100)
        ->permission('admin.contracts.view')
        ->using(fn (string $q, ?Model $user): array => Contract::where('number', $q)->limit(6)->get()
            ->map(fn (Contract $c) => [
                'title' => $c->number,
                'subtitle' => Mask::name($c->holder_name),
                'url' => '/screens/contract?number='.urlencode($c->number),
            ])->all()),
    SearchProvider::make('by-phone', 'По телефону')
        ->recognizesPhone()                      // +7…, 8 (999)…
        ->onlyWhenRecognized()                   // иначе не запускать
        ->using(fn (string $q) => ...),
    SearchProvider::make('by-email', 'По e-mail')->recognizesEmail()->onlyWhenRecognized()->using(...),
]);
```

Группа, чей распознаватель узнал ввод, стоит выше всех (выше разделов), затем
по `priority`; без распознавателя провайдер работает на любом запросе.
`->limit(n)` — сколько пунктов (по умолчанию 5). Маскировать персональные
данные в выдаче — дело провайдера (`Support\Mask`). Ресурсы по-прежнему ищут
сами, без распознавателя.

## 5. Условие и фильтры по умолчанию у списка, бейджи вкладок

Вкладка очереди больше не требует ресурса-наследника со своим slug:

```php
// ресурс журнала
public function scopes(): array
{
    return [
        'pending' => fn (Builder $q) => $q->whereNull('sent_ad'),
    ];
}

// экран
Layout::tabs([
    'Не переданы' => Layout::resourceIndex(CrmRequestResource::class)
        ->queryKey('crm-pending')->scope('pending'),
    'Все' => Layout::resourceIndex(CrmRequestResource::class)
        ->queryKey('crm-all')->defaultFilters(['status' => 'new']),
])->persistInQuery('crm-tab')->keys(['pending', 'all'])->badges([
    'pending' => fn () => ['value' => app(CrmRequestResource::class)->countIn('pending'), 'tone' => 'danger'],
]);
```

- `->scope($name)` — именованное условие из `Resource::scopes()`: уходит
  параметром `scope` в каждый запрос списка (поиск, фильтры, экспорт, сводка,
  дерево, группировка), пользователь его не снимет. Неизвестное имя —
  ошибка при сборке экрана и 422 от API. Права — ресурса.
- `->defaultFilters([...])` — фильтры при открытии, если в адресе их нет;
  пользователь может снять, и снятые остаются снятыми (`filter=none` в
  адресе).
- `Resource::countIn(?$scope)` — число записей списка или условия, для бейджа.
- `Tabs::badges([ключ => число|текст|замыкание|['value','tone']])` или
  `->badge($ключ, $value, $tone)` — бейдж у вкладки; замыкание считается при
  отдаче экрана и только для вкладки, которую пользователь видит.

## 6. «+ Создать»

```php
use Dskripchenko\LaravelAdmin\Menu\QuickCreate;

$admin->menu()->quickCreate([
    QuickCreate::resource(NewsResource::class),                    // /r/news/create, право admin.news.create
    QuickCreate::resource('vacancies', 'Вакансию')->icon('briefcase'),
    QuickCreate::make('price', 'Загрузить прайс', '/screens/price-import')->permission('admin.prices.import'),
]);
```

Кнопка «Создать» в верхней панели — меню этих ссылок, отфильтрованное
сервером по правам (у ресурса — `.create` и наличие полей формы). Те же
пункты — действия палитры Ctrl+K. Без регистрации кнопки нет.

## 7. Экран во вкладке другого экрана

```php
Layout::tabs([
    'Сайт' => [...$this->siteFields()],
    'Капча' => Layout::screen(CaptchaSettingsScreen::class, ['mode' => 'v3']),
])->persistInQuery('tab')->keys(['site', 'captcha']);
```

Встроенный экран — полноценный экран: состояние грузится его `/{slug}/state`
(с `$params` как query), панель команд и методы — его собственные, права —
его (`permission()`/`permissionMode()`): без них вкладки нет. Экран должен
быть зарегистрирован; чтобы он не появился в меню отдельно —
`menu()->hideAuto($slug)` или `withAuto(false)`. `->title(false)` скрывает
его название над содержимым.

## 8. Период рабочего стола

```php
final class DesktopDashboard extends DashboardScreen
{
    public function periods(): ?array { return ['today', '7d', '30d', '90d', 'month', 'quarter']; }
    public function customRange(): bool { return true; }   // «Свой период…» — с … по …
    public function comparable(): bool { return true; }    // «Сравнить с прошлым периодом»

    public function widgets(): array
    {
        $ctx = $this->dashboardContext();
        $count = $ctx->constrain(Order::query(), 'created_at')->count();
        $before = $ctx->comparing()?->constrain(Order::query(), 'created_at')->count();

        return [
            StatsOverviewWidget::make()
                ->stat('Заявки', $count, url: $ctx->listUrl('/r/orders', 'created_at'))
                ->previous($before)                      // под числом, и тренд в %
                ->link('/screens/sales'),                // стрелка у карточки
        ];
    }
}
```

`DashboardContext`:

| Период | Окно |
|---|---|
| `today` | с полуночи |
| `Nd` (`7d`, `30d`, `90d`) | последние N дней |
| `month`, `quarter` | с начала месяца / квартала |
| `2026-01-01..2026-03-31` | дни целиком, оба края включены |
| `all` | без границ |

`from()`, `to()`, `days()`, `label()` (и `DashboardContext::labelFor($period)`
для подписи), `constrain($query, $column)` — с обеими границами для
диапазона, `compare()` — включено ли сравнение, `previous()` — окно той же
длины перед текущим (для месяца и квартала — тот же отрезок прошлого
месяца/квартала), `comparing()` — `previous()` только при включённом
сравнении. `filterQuery($field)` и `listUrl($path, $field)` — период как
фильтр списка в адресе: `?filter[created_at][from]=…&filter[created_at][to]=…`;
список читает такой диапазон (`DateRangeFilter`), так виджет ведёт «в журнал
за период».

В манифесте дашборда — `periodLabels` (подписи сервера), `customRange`,
`comparable`. `dashboard/widgets` принимает `compare=1` и возвращает
`periodLabel` и `compare`. Свой диапазон сохраняется как выбранный период.

`Widget::link($url)` — ссылка у любого виджета (стрелка в углу);
`StatsOverviewWidget::url($url)` / `stat(..., url: …)` — ссылка у каждой
цифры. Адрес панели открывается в ней, внешний — в новой вкладке.

`Layout::dashboard([...])` на обычном экране теперь не отдаёт виджеты, на
которые у пользователя нет `permission()` (раньше проверялся только
`canSee()`).

## 9. Маскированные персональные данные

```php
TableColumn::make('phone')->masked('phone'),                          // +7 *** ***-**-67
TableColumn::make('email')->masked('email'),                          // i***@mail.ru
TableColumn::make('holder')->masked('name', 'admin.contracts.pii'),   // П*** И. С.
TableColumn::make('passport')->masked('passport'),                    // **** ***456
TableColumn::make('inn')->masked(fn ($v) => '*****'.substr($v, -4)),
```

Значение уходит с сервера уже в маске — в списке, дереве, табличном виджете
и **экспорте** (в файле — маска всегда). В ячейке — кнопка «Показать», если
у пользователя есть право: второй аргумент `masked()` или `{база ресурса}.reveal`
(например, `admin.contracts.reveal` — зарегистрируйте его в своих правах).
Кнопка вызывает `POST /{slug}/reveal {id, column}`: запись берётся в рамках
`indexQuery()` ресурса, открытое значение возвращается, а в журнал аудита
пакета пишется событие `pii.revealed` — кто (actor), какая запись (subject),
`changes: {resource, field}`. Список отдаёт `meta.revealable` — колонки, которые
этот пользователь может раскрыть.

Не маскируются карточка и форма записи: их значения — исходные, давайте
доступ к ним тем, кому можно видеть данные. Маски — `Support\Mask`
(`phone`, `email`, `name`, `passport`, `full`).

## 10. Хлебные крошки

- Секция (`MenuNode::section()`) в крошках не появляется.
- `navigation.breadcrumbs = 'compact'` — `Область › Экран › Вкладка › Запись`:
  промежуточные группы меню (узлы без адреса) пропускаются.
- Открытые вкладки, хранящиеся в адресе (`persistInQuery()`), — в крошках
  после экрана, вложенные после внешних.
- Крошка списка или экрана ведёт на него **с тем query, с которым его
  оставили**: фильтры, страница, вкладка. Само состояние списка уже было в
  адресе (`?filter[status]=new&page=2`, у списка с `queryKey` — с префиксом),
  но крошка раньше вела на голый адрес.

Не сделано: крошки записи, открытой из вкладки экрана, показывают ресурс
записи, а не экран и вкладку, из которых её открыли.

## data-testid

`menu-favorites`, `favorite-item` (`data-url`), `favorite-remove`,
`favorite-toggle`, `menu-badge-{key}` (бейдж группы), `quick-create`,
`quick-create-{key}`, `search-group-{key}` (`favorites`, `recent`,
`sections`, `actions`, slug ресурса, `provider-{key}`), `search-hint`,
`tab-badge-{key}`, `embedded-screen`, `dash-period-{key}`,
`dash-period-range`, `dash-range`, `dash-range-from`, `dash-range-to`,
`dash-range-apply`, `dash-range-cancel`, `dash-compare`, `widget-link`,
`stat-link`, `stat-previous`, `masked-cell`, `masked-reveal`, `masked-hide`.

## См. также

- [Меню](menu.md)
- [Экраны](screens.md)
- [Виджеты и дашборды](widgets-and-dashboards.md)
- [Права](permissions.md)
