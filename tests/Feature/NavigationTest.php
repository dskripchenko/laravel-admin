<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Audit\AuditLog;
use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Menu\MenuNode;
use Dskripchenko\LaravelAdmin\Menu\MenuRegistry;
use Dskripchenko\LaravelAdmin\Menu\QuickCreate;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Navigation\NavigationState;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Support\GlobalSearch;
use Dskripchenko\LaravelAdmin\Support\Mask;
use Dskripchenko\LaravelAdmin\Support\SearchProvider;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    app(MenuRegistry::class)->clear();
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestQueueResource::class);
    $screens = app(ScreenRegistry::class);
    $screens->clear();
    $screens->add(TestQueueTabsScreen::class);
    $screens->add(TestSecretScreen::class);
    $screens->add(TestPeriodDashboard::class);
    AdminApi::clearCache();
    TestQueueTabsScreen::$badgeCalls = 0;

    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('name')->nullable();
        $t->string('email')->nullable();
        $t->string('status')->nullable();
        $t->timestamps();
    });
    TestResourceUserModel::create(['name' => 'Ann', 'email' => 'ann.lee@mail.ru']);
    TestResourceUserModel::create(['name' => 'Bob', 'email' => 'bob@corp.io']);
    TestResourceUserModel::create(['name' => 'Cid', 'email' => 'cid@corp.io', 'status' => 'sent']);
});

/**
 * @param  list<string>  $permissions
 */
function navActingAs(array $permissions = ['*']): AdminUser
{
    $admin = AdminUser::create(['name' => 'A', 'email' => 'a-'.uniqid().'@example.com', 'password' => 'secret']);
    $role = Role::create(['name' => 'S', 'slug' => 's-'.uniqid(), 'permissions' => $permissions]);
    $admin->assignRole($role);
    test()->actingAs($admin->refresh(), 'admin');

    return $admin;
}

/**
 * The same user with other permissions — a second user in one test is not
 * let through by the panel's session guard.
 *
 * @param  list<string>  $permissions
 */
function navRegrant(AdminUser $admin, array $permissions): void
{
    $admin->roles()->first()->update(['permissions' => $permissions]);
    test()->actingAs($admin->refresh(), 'admin');
}

// 1. Counters computed on the request

it('computes a closure badge per request, for a user who may see the node only', function (): void {
    $calls = 0;
    app(MenuRegistry::class)->withAuto(false)->add(
        MenuNode::make('queues', 'Queues')->children([
            MenuNode::resource('test-queue')->badge(function (?object $user) use (&$calls): int {
                $calls++;

                return $user === null ? 0 : 150;
            }, 'danger'),
            MenuNode::make('calls', 'Calls')->url('/calls')->badge(fn () => ['value' => 4, 'tone' => 'danger']),
        ]),
    );

    $admin = navActingAs(['admin.queue.view']);
    $items = $this->getJson('/api/admin/system/menu')->assertOk()->json('payload.items');
    $queue = $items[0]['children'][0];
    expect($queue['badge'])->toBe(150)
        ->and($queue['badgeTone'])->toBe('danger')
        ->and($items[0]['children'][1]['badge'])->toBe(4)
        ->and($calls)->toBe(1);

    $badges = $this->getJson('/api/admin/system/menuCounters')->assertOk()->json('payload.badges');
    expect($badges)->toBe([
        'resource.test-queue' => ['badge' => 150, 'tone' => 'danger'],
        'calls' => ['badge' => 4, 'tone' => 'danger'],
    ]);

    // Without the node's permission the closure is not called at all.
    $calls = 0;
    navRegrant($admin, ['admin.other.view']);
    $badges = $this->getJson('/api/admin/system/menuCounters')->assertOk()->json('payload.badges');
    expect($badges)->not->toHaveKey('resource.test-queue')->and($calls)->toBe(0);
});

it('a badge closure that throws shows nothing', function (): void {
    navActingAs();
    app(MenuRegistry::class)->withAuto(false)->add(
        MenuNode::make('x', 'X')->url('/x')->badge(fn () => throw new RuntimeException('boom')),
    );
    $items = $this->getJson('/api/admin/system/menu')->assertOk()->json('payload.items');
    expect($items[0]['badge'])->toBeNull();
});

// 2. Folded groups, the remembered state, the section

it('serializes collapsed, sumBadges and sections, and remembers a group per user', function (): void {
    app(MenuRegistry::class)->withAuto(false)
        ->add(MenuNode::section('Pinned', [MenuNode::screen('test-queues')]))
        ->add(MenuNode::make('dicts', 'Dictionaries')->collapsed()->sumBadges()->children([
            MenuNode::resource('test-queue'),
        ]));

    $admin = navActingAs();
    $payload = $this->getJson('/api/admin/system/menu')->assertOk()->json('payload');
    expect($payload['items'][0]['kind'])->toBe('section')
        ->and($payload['items'][0]['key'])->toBe('section.pinned')
        ->and($payload['items'][0]['children'][0]['url'])->toBe('/screens/test-queues')
        ->and($payload['items'][1]['collapsed'])->toBeTrue()
        ->and($payload['items'][1]['sumBadges'])->toBeTrue()
        ->and($payload['groups'])->toBe([]);

    $this->postJson('/api/admin/navigation/group', ['key' => 'dicts', 'open' => true])->assertOk();
    expect($this->getJson('/api/admin/system/menu')->json('payload.groups'))->toBe(['dicts' => true]);
    expect(NavigationState::query()->where('owner_id', (string) $admin->getKey())->first()?->menu_groups)
        ->toBe(['dicts' => true]);
    // Another user has a state of their own.
    expect(NavigationState::menuGroupsOf(AdminUser::query()->whereKeyNot($admin->getKey())->first() ?? new AdminUser(['id' => 999]), 'admin'))->toBe([]);
});

it('a section without visible children is dropped', function (): void {
    app(MenuRegistry::class)->withAuto(false)->add(MenuNode::section('Pinned', [MenuNode::resource('test-queue')]));
    navActingAs(['admin.other.view']);
    expect($this->getJson('/api/admin/system/menu')->json('payload.items'))->toBe([]);
});

// 3. Favourites and recent pages

it('keeps favourites in order, up to the limit, and only what the user may open', function (): void {
    config()->set('admin.navigation.favorites_limit', 2);
    $admin = navActingAs();

    $this->postJson('/api/admin/navigation/favorite', ['url' => '/r/test-queue?filter[name]=Ann', 'label' => 'Ann'])->assertOk();
    $this->postJson('/api/admin/navigation/favorite', ['url' => '/screens/test-queues?tab=all', 'label' => 'Queues · All'])->assertOk();
    $this->postJson('/api/admin/navigation/favorite', ['url' => '/screens/test-secret', 'label' => 'Secret'])
        ->assertStatus(422);
    // A page outside the panel, or one the user may not open, is refused.
    $this->postJson('/api/admin/navigation/unfavorite', ['url' => '/screens/test-queues?tab=all'])->assertOk();
    $this->postJson('/api/admin/navigation/favorite', ['url' => 'https://evil.example', 'label' => 'x'])->assertStatus(422);
    $this->postJson('/api/admin/navigation/favorite', ['url' => '/r/unknown', 'label' => 'x'])->assertStatus(422);
    $this->postJson('/api/admin/navigation/favorite', ['url' => '/screens/test-secret', 'label' => 'Secret'])->assertOk();

    $state = $this->postJson('/api/admin/navigation/reorderFavorites', ['urls' => ['/screens/test-secret', '/r/test-queue?filter[name]=Ann']])
        ->assertOk()->json('payload');
    expect(array_column($state['favorites'], 'url'))->toBe(['/screens/test-secret', '/r/test-queue?filter[name]=Ann'])
        ->and($state['favoritesLimit'])->toBe(2);

    // The permission goes: the favourite is not shown, but stays saved.
    $role = $admin->roles()->first();
    $role->update(['permissions' => ['admin.queue.view']]);
    test()->actingAs($admin->refresh(), 'admin');
    $state = $this->getJson('/api/admin/navigation/state')->assertOk()->json('payload');
    expect(array_column($state['favorites'], 'url'))->toBe(['/r/test-queue?filter[name]=Ann']);
    expect(NavigationState::query()->first()->favorites)->toHaveCount(2);
});

it('keeps the recent pages newest first, one per path, up to the limit', function (): void {
    config()->set('admin.navigation.recent_limit', 2);
    navActingAs();
    $this->postJson('/api/admin/navigation/visit', ['url' => '/screens/test-queues?tab=pending', 'label' => 'Queues', 'kind' => 'screen'])->assertOk();
    $this->postJson('/api/admin/navigation/visit', ['url' => '/r/test-queue/1', 'label' => 'Ann', 'subtitle' => 'Queue', 'kind' => 'record'])->assertOk();
    $state = $this->postJson('/api/admin/navigation/visit', ['url' => '/screens/test-queues?tab=all', 'label' => 'Queues · All', 'kind' => 'screen'])
        ->assertOk()->json('payload');

    expect(array_column($state['recent'], 'url'))->toBe(['/screens/test-queues?tab=all', '/r/test-queue/1'])
        ->and($state['recent'][1]['subtitle'])->toBe('Queue')
        ->and($state['recent'][1]['kind'])->toBe('record');

    $this->postJson('/api/admin/navigation/visit', ['url' => '/r/test-queue/1', 'label' => 'x', 'kind' => 'nope'])->assertStatus(422);
});

it('decides what a panel path needs', function (): void {
    navActingAs(['admin.queue.view']);
    $access = app(Dskripchenko\LaravelAdmin\Navigation\UrlAccess::class);
    expect($access->allows('/r/test-queue'))->toBeTrue()
        ->and($access->allows('/r/test-queue/5'))->toBeTrue()
        ->and($access->allows('/r/test-queue/5/edit'))->toBeFalse()
        ->and($access->allows('/r/test-queue/create'))->toBeFalse()
        ->and($access->allows('/screens/test-queues?tab=all'))->toBeTrue()
        ->and($access->allows('/screens/test-secret'))->toBeFalse()
        ->and($access->allows('/dashboard/test-periods'))->toBeTrue()
        ->and($access->allows('/profile'))->toBeTrue()
        ->and($access->allows('//evil.example/r'))->toBeFalse();
});

// 4. The palette: the screens' tabs and the host's providers

it('lists the tabs of the menu screens kept in the address, nested ones with their parents', function (): void {
    app(MenuRegistry::class)->withAuto(false)->add(
        MenuNode::make('clients', 'Clients')->children([MenuNode::screen('test-queues')]),
    );
    navActingAs(['admin.queue.view']);
    $items = $this->getJson('/api/admin/system/menuTabs')->assertOk()->json('payload.items');

    $urls = array_column($items, 'url');
    expect($urls)->toBe([
        '/screens/test-queues?tab=pending',
        '/screens/test-queues?tab=all',
        '/screens/test-queues?tab=all&all-tab=recent',
    ]);
    expect($items[2]['path'])->toBe(['Clients', 'Queues', 'All', 'Recent'])
        ->and($items[2]['keywords'])->toBe(['all', 'recent']);
});

it('runs the host search providers: a recognized group goes on top', function (): void {
    navActingAs(['admin.queue.view']);
    app(Admin::class)->searchProviders([
        SearchProvider::make('contracts', 'Contracts')
            ->recognizes('/^\d{3}-\d+$/')
            ->priority(10)
            ->using(fn (string $q): array => [['title' => 'Contract '.$q, 'subtitle' => Mask::name('Petrov Ivan'), 'url' => '/screens/contract?n='.$q]]),
        SearchProvider::make('phones', 'Phones')
            ->recognizesPhone()
            ->onlyWhenRecognized()
            ->using(fn (string $q): array => [['title' => $q, 'url' => '/r/test-queue?phone='.$q]]),
        SearchProvider::make('secret', 'Secret')->permission('admin.secret.nobody')->using(fn (): array => [['title' => 'x', 'url' => '/x']]),
    ]);

    $groups = app(GlobalSearch::class)->search('Ann');
    expect(array_column($groups, 'slug'))->toBe(['provider.contracts', 'test-queue']);
    expect($groups[0]['recognized'])->toBeFalse();

    $groups = app(GlobalSearch::class)->search('123-456');
    expect($groups[0]['slug'])->toBe('provider.contracts')
        ->and($groups[0]['recognized'])->toBeTrue()
        ->and($groups[0]['items'][0]['subtitle'])->toBe('P*** I.');

    $groups = app(GlobalSearch::class)->search('+7 999 123-45-67');
    expect($groups[0]['slug'])->toBe('provider.phones')->and($groups[0]['recognized'])->toBeTrue();

    $response = $this->getJson('/api/admin/system/search?q=123-456')->assertOk();
    expect($response->json('payload.groups.0.slug'))->toBe('provider.contracts');
});

// 5. A list's scope and default filters, the tabs' badges

it('narrows the list by a named scope, refuses an unknown one, counts in it', function (): void {
    navActingAs();
    $rows = $this->postJson('/api/admin/test-queue/search', ['scope' => 'pending', 'order' => [['column' => 'id']]])->assertOk()->json('payload.data');
    expect(array_column($rows, 'name'))->toBe(['Ann', 'Bob']);

    $rows = $this->postJson('/api/admin/test-queue/search', ['scope' => 'pending', 'filters' => ['name' => 'Bob']])->json('payload.data');
    expect(array_column($rows, 'name'))->toBe(['Bob']);

    $this->postJson('/api/admin/test-queue/search', ['scope' => 'drop-table'])->assertStatus(422);
    expect(app(TestQueueResource::class)->countIn('sent'))->toBe(1)
        ->and(app(TestQueueResource::class)->countIn())->toBe(3);
});

it('serializes a list scope, its default filters, the tabs badges and an embedded screen', function (): void {
    $admin = navActingAs(['admin.queue.view']);
    $layout = $this->getJson('/api/admin/test-queues/state')->assertOk()->json('payload.layout');
    $tabs = $layout[0]['items'];

    expect($tabs[0]['key'])->toBe('pending')
        ->and($tabs[0]['badge'])->toBe(2)
        ->and($tabs[0]['badgeTone'])->toBe('danger')
        ->and($tabs[1]['badge'])->toBe(120)
        ->and($tabs[0]['items'][0]['scope'])->toBe('pending')
        ->and($tabs[0]['items'][0]['defaultFilters'])->toBe(['name' => 'Ann'])
        ->and(TestQueueTabsScreen::$badgeCalls)->toBe(1);

    // The embedded screen needs its own permission: without it the tab goes.
    $nested = $tabs[1]['items'][0]['items'];
    expect(array_column($nested, 'key'))->toBe(['recent']);

    navRegrant($admin, ['admin.queue.view', 'admin.secret']);
    $nested = $this->getJson('/api/admin/test-queues/state')->json('payload.layout.0.items.1.items.0.items');
    expect(array_column($nested, 'key'))->toBe(['recent', 'secret'])
        ->and($nested[1]['items'][0]['type'])->toBe('admin.screen')
        ->and($nested[1]['items'][0]['screen'])->toBe('test-secret')
        ->and($nested[1]['items'][0]['params'])->toBe(['mode' => 'embedded']);

    // It is loaded by its own endpoint, with its own permission.
    expect($this->getJson('/api/admin/test-secret/state?mode=embedded')->assertOk()->json('payload.state.mode'))->toBe('embedded');
});

it('refuses a scope the resource does not declare when the layout is built', function (): void {
    Layout::resourceIndex(TestQueueResource::class)->scope('nope');
})->throws(InvalidArgumentException::class);

// 6. «+ Create»

it('offers the create links the user may use', function (): void {
    app(MenuRegistry::class)->withAuto(false)->quickCreate([
        QuickCreate::resource(TestQueueResource::class),
        QuickCreate::resource('test-queue', 'Queue item')->icon('plus'),
        QuickCreate::make('upload', 'Upload', '/screens/test-secret')->permission('admin.secret'),
        QuickCreate::resource('missing'),
    ]);

    $admin = navActingAs(['admin.queue.view', 'admin.queue.create']);
    $create = $this->getJson('/api/admin/system/menu')->assertOk()->json('payload.create');
    expect(array_column($create, 'url'))->toBe(['/r/test-queue/create', '/r/test-queue/create'])
        ->and($create[1]['label'])->toBe('Queue item')
        ->and($create[1]['icon'])->toBe('plus');

    navRegrant($admin, ['admin.queue.view', 'admin.secret']);
    $create = $this->getJson('/api/admin/system/menu')->json('payload.create');
    expect(array_column($create, 'key'))->toBe(['upload']);
});

// 8. The dashboard's periods

it('takes the named periods, a range and the comparison', function (): void {
    app()->setLocale('ru');
    $now = Carbon\CarbonImmutable::parse('2026-11-13 15:00:00');
    $ctx = new Dskripchenko\LaravelAdmin\Widget\DashboardContext('today', $now);
    expect($ctx->from()?->toDateTimeString())->toBe('2026-11-13 00:00:00')
        ->and($ctx->label())->toBe('Сегодня');

    $q = new Dskripchenko\LaravelAdmin\Widget\DashboardContext('quarter', $now, compare: true);
    expect($q->from()?->toDateString())->toBe('2026-10-01')
        ->and($q->compare())->toBeTrue()
        ->and($q->comparing()?->from()?->toDateString())->toBe('2026-07-01')
        ->and($q->comparing()?->to()->toDateString())->toBe('2026-08-13');

    $range = new Dskripchenko\LaravelAdmin\Widget\DashboardContext('2026-01-01..2026-01-31', $now);
    expect($range->days())->toBe(31)
        ->and($range->to()->toDateTimeString())->toBe('2026-01-31 23:59:59')
        ->and($range->previous()?->from()->toDateString())->toBe('2025-12-01')
        ->and($range->label())->toBe('01.01.2026 – 31.01.2026')
        ->and($range->filterQuery('created_at'))->toBe([
            'filter[created_at][from]' => '2026-01-01',
            'filter[created_at][to]' => '2026-01-31',
        ])
        ->and($range->listUrl('/r/orders', 'created_at', ['status' => 'new']))
        ->toBe('/r/orders?status=new&filter%5Bcreated_at%5D%5Bfrom%5D=2026-01-01&filter%5Bcreated_at%5D%5Bto%5D=2026-01-31');

    $week = new Dskripchenko\LaravelAdmin\Widget\DashboardContext('7d', $now);
    expect($week->previous()?->to()->toDateTimeString())->toBe('2026-11-06 14:59:59');

    expect(Dskripchenko\LaravelAdmin\Widget\DashboardContext::isValidPeriod('2026-02-30..2026-03-01'))->toBeFalse()
        ->and(Dskripchenko\LaravelAdmin\Widget\DashboardContext::isValidPeriod('2026-03-02..2026-03-01'))->toBeFalse()
        ->and(Dskripchenko\LaravelAdmin\Widget\DashboardContext::isValidPeriod('month'))->toBeTrue()
        ->and((new Dskripchenko\LaravelAdmin\Widget\DashboardContext('all', $now))->filterQuery('x'))->toBe([]);
});

it('serves the periods, a range and the comparison of a dashboard', function (): void {
    navActingAs();
    app()->setLocale('ru');
    $manifest = app(TestPeriodDashboard::class)->toManifest('test-periods');
    expect($manifest['periods'])->toBe(['today', '7d', 'month', 'quarter'])
        ->and($manifest['customRange'])->toBeTrue()
        ->and($manifest['comparable'])->toBeTrue()
        ->and((array) $manifest['periodLabels'])->toHaveKey('quarter', 'Этот квартал')
        ->and($manifest['widgets'][0]['link'])->toBe('/r/test-queue')
        ->and($manifest['widgets'][0]['data']['stats'][0]['url'])->toStartWith('/r/test-queue?filter%5Bcreated_at%5D%5Bfrom%5D=');

    $payload = $this->getJson('/api/admin/dashboard/widgets?key=test-periods&period=2026-01-01..2026-01-31&compare=1')
        ->assertOk()->json('payload');
    expect($payload['period'])->toBe('2026-01-01..2026-01-31')
        ->and($payload['compare'])->toBeTrue()
        ->and($payload['widgets'][0]['data']['stats'][0]['previous'])->toBe(7)
        ->and($payload['widgets'][0]['data']['stats'][0]['url'])->toContain('2026-01-31');

    // A period the dashboard does not list falls back to its default.
    expect($this->getJson('/api/admin/dashboard/widgets?key=test-periods&period=90d')->json('payload.period'))->toBe('7d');
    $this->postJson('/api/admin/dashboard/savePeriod', ['key' => 'test-periods', 'period' => '2026-01-01..2026-01-31'])->assertOk();
});

it('leaves out a widget the user may not see on an ordinary screen dashboard', function (): void {
    navActingAs(['admin.queue.view']);
    $layout = Layout::dashboard([
        StatsOverviewWidget::make()->withSlug('open')->stat('A', 1),
        StatsOverviewWidget::make()->withSlug('closed')->permission('admin.finance.view')->stat('B', 2),
    ])->toArray();

    expect(array_column($layout['items'], 'slug'))->toBe(['open']);
});

// 9. Masked personal data

it('masks the values', function (): void {
    expect(Mask::phone('+7 (999) 123-45-67'))->toBe('+7 *** ***-**-67')
        ->and(Mask::phone('89991234567'))->toBe('+7 *** ***-**-67')
        ->and(Mask::email('ivan.petrov@mail.ru'))->toBe('i***@mail.ru')
        ->and(Mask::name('Petrov Ivan Sergeevich'))->toBe('P*** I. S.')
        ->and(Mask::passport('4510 123456'))->toBe('**** ***456')
        ->and(Mask::apply('full', 'x'))->toBe('***')
        ->and(Mask::apply('phone', null))->toBeNull()
        ->and(TableColumn::make('inn')->masked(fn ($v) => '*'.substr((string) $v, -2))->maskValue('123456'))->toBe('*56');
});

it('sends a masked column masked, reveals it by permission and audits the reveal', function (): void {
    $admin = navActingAs(['admin.queue.view']);
    $payload = $this->postJson('/api/admin/test-queue/search', ['order' => [['column' => 'id']]])->assertOk()->json('payload');
    expect($payload['data'][0]['email'])->toBe('a***@mail.ru')
        ->and($payload['meta']['revealable'])->toBe([]);

    $id = $payload['data'][0]['id'];
    $this->postJson('/api/admin/test-queue/reveal', ['id' => $id, 'column' => 'email'])->assertStatus(403);
    expect(AuditLog::query()->where('event', 'pii.revealed')->count())->toBe(0);

    navRegrant($admin, ['admin.queue.view', 'admin.queue.reveal']);
    expect($this->postJson('/api/admin/test-queue/search', [])->json('payload.meta.revealable'))->toBe(['email']);
    $this->postJson('/api/admin/test-queue/reveal', ['id' => $id, 'column' => 'name'])->assertStatus(422);
    $value = $this->postJson('/api/admin/test-queue/reveal', ['id' => $id, 'column' => 'email'])->assertOk()->json('payload.value');
    expect($value)->toBe('ann.lee@mail.ru');

    $log = AuditLog::query()->where('event', 'pii.revealed')->first();
    expect($log)->not->toBeNull()
        ->and((string) $log->actor_id)->toBe((string) $admin->getKey())
        ->and((string) $log->subject_id)->toBe((string) $id)
        ->and($log->changes)->toBe(['resource' => 'test-queue', 'field' => 'email']);
});

it('exports a masked column masked', function (): void {
    navActingAs();
    $csv = $this->get('/api/admin/test-queue/export?format=csv')->assertOk()->streamedContent();
    expect($csv)->toContain('a***@mail.ru')->not->toContain('ann.lee@mail.ru');
});
