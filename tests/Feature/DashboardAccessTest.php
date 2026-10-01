<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Menu\MenuNode;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Widget\DashboardContext;
use Dskripchenko\LaravelAdmin\Widget\DashboardLayout;
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelAdmin\Widget\RecentListWidget;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Dskripchenko\LaravelAdmin\Widget\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboards and their widgets obey the permissions everywhere they are
 * served — the manifest, the menu and the /dashboard/* endpoints — and a
 * forbidden widget's data is never computed.
 */
function dashUser(array $permissions): AdminUser
{
    $user = AdminUser::create([
        'name' => 'U',
        'email' => 'u-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $user->assignRole(Role::create([
        'name' => 'R',
        'slug' => 'r-'.uniqid(),
        'permissions' => $permissions,
    ]));

    return $user->refresh();
}

/**
 * @return array<string, mixed>|null
 */
function manifestDashboard(mixed $test, string $slug): ?array
{
    $dashboards = $test->getJson('/api/admin/system/manifest')->assertOk()->json('payload.dashboards');
    foreach ($dashboards as $dashboard) {
        if ($dashboard['slug'] === $slug) {
            return $dashboard;
        }
    }

    return null;
}

beforeEach(function (): void {
    app(ScreenRegistry::class)->clear();
    app(ScreenRegistry::class)->add(SecretDashboard::class);
    app(ScreenRegistry::class)->add(MixedDashboard::class);
    app(ScreenRegistry::class)->add(PeriodDashboard::class);
    app(ScreenRegistry::class)->add(LegacyPeriodDashboard::class);
    ExplodingWidget::$calls = 0;
});

it('leaves a forbidden dashboard out of the manifest and answers 403 on its endpoints', function (): void {
    $this->actingAs(dashUser(['other.permission']), 'admin');

    expect(manifestDashboard($this, 'secret-dash'))->toBeNull();
    expect(ExplodingWidget::$calls)->toBe(0);

    $this->getJson('/api/admin/dashboard/widgets?key=secret-dash')->assertStatus(403);
    $this->getJson('/api/admin/dashboard/get?key=secret-dash')->assertStatus(403);
    $this->postJson('/api/admin/dashboard/save', ['key' => 'secret-dash', 'widgets' => [['slug' => 'x']]])->assertStatus(403);
    $this->postJson('/api/admin/dashboard/savePeriod', ['key' => 'secret-dash', 'period' => '7d'])->assertStatus(403);
    $this->postJson('/api/admin/dashboard/reset', ['key' => 'secret-dash'])->assertStatus(403);

    expect(ExplodingWidget::$calls)->toBe(0);
    expect(DashboardLayout::count())->toBe(0);
});

it('requires every permission of a dashboard', function (): void {
    $this->actingAs(dashUser(['secret.view']), 'admin');

    expect(manifestDashboard($this, 'secret-dash'))->toBeNull();
    $this->getJson('/api/admin/dashboard/widgets?key=secret-dash')->assertStatus(403);
});

it('serves a dashboard to a user holding its permissions', function (): void {
    $this->actingAs(dashUser(['secret.view', 'secret.extra', 'widget.secret']), 'admin');

    $dashboard = manifestDashboard($this, 'secret-dash');
    expect($dashboard)->not->toBeNull();
    expect($dashboard['permission'])->toBe(['secret.view', 'secret.extra']);
    expect(array_column($dashboard['widgets'], 'slug'))->toBe(['allowed-stat']);

    $this->getJson('/api/admin/dashboard/widgets?key=secret-dash')->assertOk();
});

it('never computes the data of a widget the user may not see', function (): void {
    $this->actingAs(dashUser(['mixed.allowed']), 'admin');

    $dashboard = manifestDashboard($this, 'mixed-dash');
    expect(array_column($dashboard['widgets'], 'slug'))->toBe(['allowed-stat']);

    $widgets = $this->getJson('/api/admin/dashboard/widgets?key=mixed-dash')->assertOk()->json('payload.widgets');
    expect(array_column($widgets, 'slug'))->toBe(['allowed-stat']);

    // Neither the permission-gated widget nor the canSee(false) one ran a query.
    expect(ExplodingWidget::$calls)->toBe(0);
});

it('computes a permitted widget for a user who holds its permission', function (): void {
    $this->actingAs(dashUser(['mixed.allowed', 'widget.secret']), 'admin');

    $this->getJson('/api/admin/dashboard/widgets?key=mixed-dash')->assertStatus(500);
    expect(ExplodingWidget::$calls)->toBe(1);
});

it('does not let a saved layout bring back a forbidden widget', function (): void {
    $user = dashUser(['mixed.allowed']);
    $this->actingAs($user, 'admin');

    // A layout saved before the permission was revoked.
    DashboardLayout::create([
        'dashboard_key' => 'mixed-dash',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => $user->id,
        'widgets' => [
            ['slug' => 'exploding', 'position' => 0, 'type' => 'stats', 'config' => []],
            ['slug' => 'allowed-stat', 'position' => 1],
            ['slug' => 'custom.markdown.1', 'type' => 'markdown', 'position' => 2],
        ],
    ]);

    expect(array_column($this->getJson('/api/admin/dashboard/get?key=mixed-dash')->json('payload.layout'), 'slug'))
        ->toBe(['allowed-stat', 'custom.markdown.1']);

    $saved = $this->postJson('/api/admin/dashboard/save', [
        'key' => 'mixed-dash',
        'widgets' => [
            ['slug' => 'exploding', 'type' => 'stats'],
            ['slug' => 'allowed-stat'],
        ],
    ])->assertOk()->json('payload.widgets');
    expect(array_column($saved, 'slug'))->toBe(['allowed-stat']);

    $compiled = (new MixedDashboard)->compile();
    expect(array_column($compiled['layout'][0]['children'], 'slug'))->toBe(['allowed-stat']);
    expect(ExplodingWidget::$calls)->toBe(0);
});

it('removes a forbidden dashboard from the menu', function (): void {
    app(Admin::class)->menu()->add(
        MenuNode::make('group', 'Group')->children([
            MenuNode::dashboard('secret-dash'),
            MenuNode::dashboard('mixed-dash'),
        ]),
    );
    $this->actingAs(dashUser(['mixed.allowed']), 'admin');

    $items = $this->getJson('/api/admin/system/menu')->assertOk()->json('payload.items');
    $group = collect($items)->firstWhere('key', 'group');
    expect(array_column($group['children'], 'url'))->toBe(['/dashboard/mixed-dash']);
});

it('serves plugin-registered widgets everywhere the dashboard is served, respecting their permissions', function (): void {
    app(Admin::class)->widgets([FakePluginWidget::class, FakeGuardedPluginWidget::class]);
    $this->actingAs(dashUser(['mixed.allowed']), 'admin');

    $dashboard = manifestDashboard($this, 'mixed-dash');
    expect(array_column($dashboard['widgets'], 'slug'))->toBe(['allowed-stat', 'fake.plugin']);

    $widgets = $this->getJson('/api/admin/dashboard/widgets?key=mixed-dash')->json('payload.widgets');
    expect(array_column($widgets, 'slug'))->toBe(['allowed-stat', 'fake.plugin']);

    // The guarded plugin widget cannot be saved into a layout either.
    $saved = $this->postJson('/api/admin/dashboard/save', [
        'key' => 'mixed-dash',
        'widgets' => [['slug' => 'fake.guarded'], ['slug' => 'fake.plugin']],
    ])->json('payload.widgets');
    expect(array_column($saved, 'slug'))->toBe(['fake.plugin']);
    expect(FakeGuardedPluginWidget::$calls)->toBe(0);
});

it('hands the selected period to the widgets', function (): void {
    $this->actingAs(dashUser(['period.viewer']), 'admin');

    $dashboard = manifestDashboard($this, 'period-dash');
    expect($dashboard['periods'])->toBe(['7d', '30d', '90d', 'all']);
    expect($dashboard['period'])->toBe('30d');
    expect($dashboard['widgets'][0]['data']['stats'][0]['value'])->toBe('30d');

    $widgets = $this->getJson('/api/admin/dashboard/widgets?key=period-dash&period=7d')
        ->assertOk()
        ->assertJsonPath('payload.period', '7d')
        ->json('payload.widgets');
    expect($widgets[0]['data']['stats'][0]['value'])->toBe('7d');

    // An unknown period falls back to the default rather than failing.
    $this->getJson('/api/admin/dashboard/widgets?key=period-dash&period=bogus')
        ->assertOk()
        ->assertJsonPath('payload.period', '30d');

    $this->postJson('/api/admin/dashboard/savePeriod', ['key' => 'period-dash', 'period' => 'bogus'])
        ->assertStatus(422);
});

it('hides the period switcher of a dashboard nothing on which depends on the period', function (): void {
    $this->actingAs(dashUser(['mixed.allowed']), 'admin');

    expect(manifestDashboard($this, 'mixed-dash')['periods'])->toBe([]);
});

it('keeps the switcher of a dashboard that reads the period in widgets()', function (): void {
    $this->actingAs(dashUser(['period.viewer']), 'admin');

    $dashboard = manifestDashboard($this, 'legacy-period-dash');
    expect($dashboard['periods'])->toBe(['7d', '30d', '90d', 'all']);
    expect($dashboard['widgets'][0]['data']['stats'][0]['value'])->toBe(30);

    $widgets = $this->getJson('/api/admin/dashboard/widgets?key=legacy-period-dash&period=90d')->json('payload.widgets');
    expect($widgets[0]['data']['stats'][0]['value'])->toBe(90);
});

it('limits RecentListWidget to the period when asked to', function (): void {
    Schema::create('period_items', function ($table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    DB::table('period_items')->insert([
        ['name' => 'fresh', 'created_at' => now()->subDays(2), 'updated_at' => now()],
        ['name' => 'old', 'created_at' => now()->subDays(40), 'updated_at' => now()],
    ]);

    $widget = RecentListWidget::make()->model(PeriodItem::class)->column('name')->withinPeriod();
    expect($widget->isPeriodAware())->toBeTrue();

    $rows = $widget->withDashboardContext(new DashboardContext('7d'))->data()['rows'];
    expect(array_column($rows, 'name'))->toBe(['fresh']);

    $rows = $widget->withDashboardContext(new DashboardContext('all'))->data()['rows'];
    expect(array_column($rows, 'name'))->toBe(['fresh', 'old']);

    // Without withinPeriod() the widget ignores the period, as before.
    $plain = RecentListWidget::make()->model(PeriodItem::class)->column('name');
    expect(count($plain->withDashboardContext(new DashboardContext('7d'))->data()['rows']))->toBe(2);
    expect($plain->isPeriodAware())->toBeFalse();
});

it('parses periods', function (): void {
    expect((new DashboardContext('7d'))->days())->toBe(7);
    expect((new DashboardContext('all'))->days())->toBeNull();
    expect((new DashboardContext('all'))->from())->toBeNull();
    expect((new DashboardContext('nonsense'))->period)->toBe('30d');
    expect(DashboardContext::isValidPeriod('14d'))->toBeTrue();
    expect(DashboardContext::isValidPeriod('0d'))->toBeFalse();
});

final class PeriodItem extends Model
{
    protected $table = 'period_items';
}

class ExplodingWidget extends Widget
{
    public static int $calls = 0;

    public static function slug(): string
    {
        return 'exploding';
    }

    public function widgetType(): string
    {
        return 'stats';
    }

    public function data(): array
    {
        self::$calls++;

        throw new RuntimeException('The data of a hidden widget must not be computed');
    }
}

class AllowedStatWidget extends StatsOverviewWidget
{
    public static function slug(): string
    {
        return 'allowed-stat';
    }
}

class InvisibleExplodingWidget extends ExplodingWidget
{
    public static function slug(): string
    {
        return 'invisible-exploding';
    }
}

final class SecretDashboard extends DashboardScreen
{
    public static function slug(): string
    {
        return 'secret-dash';
    }

    public function permission(): array|string|null
    {
        return ['secret.view', 'secret.extra'];
    }

    public function widgets(): array
    {
        return [
            (new ExplodingWidget)->permission('nobody.has.this'),
            new AllowedStatWidget,
        ];
    }
}

final class MixedDashboard extends DashboardScreen
{
    public static function slug(): string
    {
        return 'mixed-dash';
    }

    public function permission(): array|string|null
    {
        return 'mixed.allowed';
    }

    public function widgets(): array
    {
        return [
            (new ExplodingWidget)->permission('widget.secret'),
            new AllowedStatWidget,
            (new InvisibleExplodingWidget)->canSee(false),
        ];
    }
}

final class PeriodDashboard extends DashboardScreen
{
    public static function slug(): string
    {
        return 'period-dash';
    }

    public function widgets(): array
    {
        return [new PeriodEchoWidget];
    }
}

final class PeriodEchoWidget extends Widget
{
    public function widgetType(): string
    {
        return 'stats';
    }

    public function data(): array
    {
        return ['stats' => [['label' => 'Period', 'value' => $this->dashboardContext()->period]]];
    }
}

final class LegacyPeriodDashboard extends DashboardScreen
{
    public static function slug(): string
    {
        return 'legacy-period-dash';
    }

    public function widgets(): array
    {
        // The way dashboards were written before widgets had a context.
        return [StatsOverviewWidget::make()->stat('Days', $this->periodDays())];
    }
}

class FakePluginWidget extends StatsOverviewWidget
{
    public static function slug(): string
    {
        return 'fake.plugin';
    }
}

class FakeGuardedPluginWidget extends Widget
{
    public static int $calls = 0;

    public function __construct()
    {
        $this->permission('fake.guarded.view');
    }

    public static function slug(): string
    {
        return 'fake.guarded';
    }

    public function widgetType(): string
    {
        return 'stats';
    }

    public function data(): array
    {
        self::$calls++;

        return [];
    }
}
