---
title: Navigation — counters, favourites, search, tabs
audience: developer
status: stable
locale: en
---

# Navigation

Since 1.73 the panel does more than draw a menu tree: queue counters that are
computed per request and refresh themselves, folded groups that are
remembered, favourites and recent pages, a Ctrl+K command palette with
searches of your own, tab badges, a fixed condition and default filters of an
embedded list, "+ Create", a screen inside another screen's tab, dashboard
periods, masked personal data and compact breadcrumbs. Everything is opt-in:
without configuration the panel behaves as before.

The favourites, the recent pages and the folded groups need the package
migration `admin_navigation_states` — `php artisan migrate`.

## Settings

```php
// config/admin.php
'navigation' => [
    'poll_interval' => 60,       // seconds between the polls of the menu counters; 0 — off
    'groups_expanded' => false,  // menu groups start unfolded
    'sum_badges' => false,       // a folded group shows the sum of its counters
    'favorites_limit' => 7,
    'recent_limit' => 10,
    'breadcrumbs' => 'full',     // 'compact' — Area › Screen › Tab › Record
],
```

## 1. Menu counters

`MenuNode::badge()` takes a closure. It runs on every menu request
(`system/menu`) with the signed-in user — and only when the node is visible to
them — so a middleware filling numbers in after login is no longer needed:

```php
MenuNode::resource('client-claim-requests')->badge(
    fn (?Model $user): int => ClientClaimRequest::whereNull('sent_at')->count(),
    'danger',                    // a red badge; null — the ordinary one
),
MenuNode::screen('applications')->badge(fn () => ['value' => $crm + $prolongation, 'tone' => 'danger']),
```

The closure returns a number, a text, `null` (no badge) or
`['value' => …, 'tone' => …]`. One that throws shows nothing and is
`report()`ed. A number above 99 is shown as `99+`.

The panel polls the counters alone — `GET system/menuCounters` returns
`{badges: {node key: {badge, tone}}}` for the closure badges — every
`poll_interval` seconds and when the browser tab becomes visible again.
Caching an expensive count is the closure's business (`Cache::remember`).

**Sums.** `->sumBadges()` on a group or an area (or `navigation.sum_badges`
for all) — a folded group shows the sum of its descendants' numeric badges,
red when one of them is.

## 2. Groups and pinned items

```php
$admin->menu()
    ->add(MenuNode::section('Pinned', [
        MenuNode::dashboard('desktop'),
        MenuNode::screen('contracts'),
    ]))
    ->add(MenuNode::make('clients', 'Clients')->expanded()->children([
        MenuNode::make('queues', 'Queues')->children([...]),
        MenuNode::make('client-dicts', 'Dictionaries')->collapsed()->children([...]),
    ]));
```

- `MenuNode::section($title, $children)` — a titled section of the root: its
  items are listed flat under the title; it is neither a link nor foldable,
  and the breadcrumbs leave it out. The other root nodes need no title.
- `->collapsed()` / `->expanded()` — the group's initial state; without them
  `navigation.groups_expanded` applies (folded, as before). The group of the
  open page unfolds anyway.
- A user's folding is saved on the server (`POST navigation/group {key, open}`)
  and comes with the menu (`groups`).

## 3. Favourites and recent pages

**The star** next to the breadcrumbs, on any page: a screen, a list, a record,
a tab. The address is kept with its query — the open tab (`?tab=crm`), the
list's filters — and the label comes from the breadcrumbs ("Requests · CRM").
The favourites are a block above the menu, reordered by dragging or with
Alt+↑ / Alt+↓, at most `favorites_limit` of them.

**Recent** — the last `recent_limit` screens and records opened (a record
with its title and its resource), one entry per path; shown by the Ctrl+K
palette on an empty query.

Kept on the server per user and panel. Before they are shown, every address
is checked against the user's permissions *now* (`UrlAccess`): `/r/{slug}` —
the resource's `.view` (`/create` — `.create`, `/{id}/edit` — `.update`),
`/screens/{slug}` and `/dashboard/{slug}` — the screen's own rule,
`/settings/{slug}` — `.view`. What is closed is hidden, not deleted.

API: `GET navigation/state`, `POST navigation/favorite {url, label, icon?}`,
`navigation/unfavorite {url}`, `navigation/reorderFavorites {urls}`,
`navigation/visit {url, label, subtitle?, kind?}`, `navigation/group {key, open}`.

## 4. Ctrl+K search

⌘K / Ctrl+K or a click on the search field. ↑/↓ choose, Enter goes, Esc closes.
It shows:

- with an empty query — Favourites and Recent;
- **Sections** — the menu's items (by the label and the path) and the **tabs
  of the menu's screens** kept in the address (`persistInQuery()`):
  "Requests › CRM" opens `/screens/applications?tab=crm`, also found by the
  tab's key. The tabs come once from `GET system/menuTabs`;
- **Actions** — the "+ Create" items (section 6);
- the resources' **records** and **your own searches**:

```php
use Dskripchenko\LaravelAdmin\Support\Mask;
use Dskripchenko\LaravelAdmin\Support\SearchProvider;

$admin->searchProviders([
    SearchProvider::make('contracts', 'Contracts')
        ->recognizes('/^\d{3}-\d{6,}$/')        // a policy number: the group goes on top
        ->priority(100)
        ->permission('admin.contracts.view')
        ->using(fn (string $q, ?Model $user): array => Contract::where('number', $q)->limit(6)->get()
            ->map(fn (Contract $c) => [
                'title' => $c->number,
                'subtitle' => Mask::name($c->holder_name),
                'url' => '/screens/contract?number='.urlencode($c->number),
            ])->all()),
    SearchProvider::make('by-phone', 'By phone')->recognizesPhone()->onlyWhenRecognized()->using(...),
    SearchProvider::make('by-email', 'By e-mail')->recognizesEmail()->onlyWhenRecognized()->using(...),
]);
```

A group whose recognizer recognized the input goes above everything, then by
`priority`; a provider without a recognizer runs on every query. Masking the
personal data of the items is the provider's business (`Support\Mask`).

## 5. A list's condition and default filters, tab badges

```php
// the journal's resource
public function scopes(): array
{
    return ['pending' => fn (Builder $q) => $q->whereNull('sent_at')];
}

// the screen
Layout::tabs([
    'Not sent' => Layout::resourceIndex(CrmRequestResource::class)->queryKey('crm-pending')->scope('pending'),
    'All' => Layout::resourceIndex(CrmRequestResource::class)->queryKey('crm-all')->defaultFilters(['status' => 'new']),
])->persistInQuery('crm-tab')->keys(['pending', 'all'])->badges([
    'pending' => fn () => ['value' => app(CrmRequestResource::class)->countIn('pending'), 'tone' => 'danger'],
]);
```

- `->scope($name)` — a named condition of `Resource::scopes()`, sent as
  `scope` with every request of the list (search, filters, export, summary,
  tree, grouping); the user cannot lift it. An unknown name fails when the
  screen is built and gets a 422 from the API.
- `->defaultFilters([...])` — the filters the list opens with when the
  address names none; the user may clear them, and a cleared list stays
  cleared (`filter=none`).
- `Resource::countIn(?$scope)` — the count, for a badge.
- `Tabs::badges([key => number|text|closure|['value','tone']])` or
  `->badge($key, $value, $tone)`; a closure is computed when the screen is
  sent, for a tab the user sees only.

## 6. "+ Create"

```php
use Dskripchenko\LaravelAdmin\Menu\QuickCreate;

$admin->menu()->quickCreate([
    QuickCreate::resource(NewsResource::class),                 // /r/news/create, admin.news.create
    QuickCreate::resource('vacancies', 'Vacancy')->icon('briefcase'),
    QuickCreate::make('price', 'Upload a price list', '/screens/price-import')->permission('admin.prices.import'),
]);
```

The top bar's "Create" menu, filtered by the server (a resource needs
`.create` and form fields); the same items are the palette's actions.

## 7. A screen in another screen's tab

```php
Layout::tabs([
    'Site' => [...$this->siteFields()],
    'Captcha' => Layout::screen(CaptchaSettingsScreen::class, ['mode' => 'v3']),
])->persistInQuery('tab')->keys(['site', 'captcha']);
```

The embedded screen stays a screen: its state comes from its own
`/{slug}/state` (with `$params` as the query), its command bar and methods
are its own, and so are its permissions — without them there is no tab.
Register it; keep it out of the menu with `menu()->hideAuto($slug)`.
`->title(false)` hides its name.

## 8. Dashboard periods

```php
final class DesktopDashboard extends DashboardScreen
{
    public function periods(): ?array { return ['today', '7d', '30d', '90d', 'month', 'quarter']; }
    public function customRange(): bool { return true; }   // "Custom range…"
    public function comparable(): bool { return true; }    // "Compare with the previous period"

    public function widgets(): array
    {
        $ctx = $this->dashboardContext();
        $count = $ctx->constrain(Order::query(), 'created_at')->count();
        $before = $ctx->comparing()?->constrain(Order::query(), 'created_at')->count();

        return [
            StatsOverviewWidget::make()
                ->stat('Requests', $count, url: $ctx->listUrl('/r/orders', 'created_at'))
                ->previous($before)
                ->link('/screens/sales'),
        ];
    }
}
```

| Period | Window |
|---|---|
| `today` | since midnight |
| `Nd` | the last N days |
| `month`, `quarter` | since the start of the month / quarter |
| `2026-01-01..2026-03-31` | whole days, both ends included |
| `all` | no bounds |

`DashboardContext`: `from()`, `to()`, `days()`, `label()` /
`labelFor($period)`, `constrain()` (both bounds for a range), `compare()`,
`previous()` (the window of the same length before; the same span of the
previous month or quarter), `comparing()`, `filterQuery($field)` and
`listUrl($path, $field)` — the period as a list's date filter in the address
(`?filter[created_at][from]=…&filter[created_at][to]=…`), which the list reads
(a `DateRangeFilter`). The manifest carries `periodLabels`, `customRange` and
`comparable`; `dashboard/widgets` takes `compare=1`.

`Widget::link($url)` — a link on any widget (an arrow in its corner);
`StatsOverviewWidget::url()` / `stat(..., url: …)` — on each number.

`Layout::dashboard([...])` on an ordinary screen now leaves out the widgets
whose `permission()` the user lacks.

## 9. Masked personal data

```php
TableColumn::make('phone')->masked('phone'),                          // +7 *** ***-**-67
TableColumn::make('email')->masked('email'),                          // i***@mail.ru
TableColumn::make('holder')->masked('name', 'admin.contracts.pii'),   // P*** I. S.
TableColumn::make('passport')->masked('passport'),                    // **** ***456
TableColumn::make('inn')->masked(fn ($v) => '*****'.substr($v, -4)),
```

The value leaves the server masked — in the list, the tree, a table widget
and the **export** (always masked there). The cell offers "Show" to the users
with the permission (`masked()`'s second argument, else
`{resource base}.reveal`): `POST /{slug}/reveal {id, column}` returns the open
value of a record within the resource's `indexQuery()` and writes
`pii.revealed` to the package's audit log — the actor, the record (subject)
and `changes: {resource, field}`. The list's `meta.revealable` names the
columns this user may reveal.

The record's card and form are not masked.

## 10. Breadcrumbs

- A section is left out.
- `navigation.breadcrumbs = 'compact'` — `Area › Screen › Tab › Record`, the
  menu's intermediate groups skipped.
- The open tabs kept in the address follow the screen, outer first.
- A crumb of a list or a screen leads back **with the query it was left
  with** — the filters, the page, the tab (the state was always in the
  address; the crumb used to drop it).

Not done: a record opened from a screen's tab shows its resource in the
crumbs, not the screen and the tab it was opened from.

## data-testid

`menu-favorites`, `favorite-item` (`data-url`), `favorite-remove`,
`favorite-toggle`, `menu-badge-{key}`, `quick-create`, `quick-create-{key}`,
`search-group-{key}` (`favorites`, `recent`, `sections`, `actions`, a
resource slug, `provider-{key}`), `search-hint`, `tab-badge-{key}`,
`embedded-screen`, `dash-period-{key}`, `dash-period-range`, `dash-range`,
`dash-range-from`, `dash-range-to`, `dash-range-apply`, `dash-range-cancel`,
`dash-compare`, `widget-link`, `stat-link`, `stat-previous`, `masked-cell`,
`masked-reveal`, `masked-hide`.

## See also

- [Menu](menu.md)
- [Screens](screens.md)
- [Widgets and dashboards](widgets-and-dashboards.md)
- [Permissions](permissions.md)
