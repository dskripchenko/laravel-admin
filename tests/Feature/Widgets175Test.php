<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Menu\MenuRegistry;
use Dskripchenko\LaravelAdmin\Menu\QuickCreate;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdmin\Widget\ActionsWidget;
use Dskripchenko\LaravelAdmin\Widget\ListWidget;
use Dskripchenko\LaravelAdmin\Widget\RecentListWidget;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Dskripchenko\LaravelAdmin\Widget\TableWidget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * 1.75: the dashboard's widgets after the designer's desktop — a share in
 * percentage points, a list widget, a progress column, a cell's tone, header
 * links, compact numbers with a list, tiles and a row of action buttons.
 */

beforeEach(function (): void {
    app(MenuRegistry::class)->clear();
    $registry = app(ResourceRegistry::class);
    $registry->clear();
    $registry->add(TestQueueResource::class);

    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
        });
    }
});

/**
 * @param  list<string>  $permissions
 */
function widgets175ActingAs(array $permissions): AdminUser
{
    $admin = AdminUser::create(['name' => 'A', 'email' => 'w175-'.uniqid().'@example.com', 'password' => 'secret']);
    $role = Role::create(['name' => 'W', 'slug' => 'w-'.uniqid(), 'permissions' => $permissions]);
    $admin->assignRole($role);
    test()->actingAs($admin->refresh(), 'admin');

    return $admin;
}

// 1. A share in percentage points

it('marks a share: its change is in percentage points', function (): void {
    $stats = StatsOverviewWidget::make()
        ->stat('Сбои оформления', 0.6, 'danger')->suffix('%')->share()->previous(0.8)
        ->stat('Договоров', 120)->trend(8, 'up')
        ->stat('Доля', 12.5)->trend(0.4, 'down')->share()
        ->stat('Доля 2', 3.0)->share()->trend(0.1, 'up')
        ->data()['stats'];

    expect($stats[0])->toMatchArray(['share' => true, 'previous' => 0.8, 'suffix' => '%'])
        ->and($stats[0])->not->toHaveKey('change')
        ->and($stats[1]['change'])->toBe(['delta' => 8.0, 'direction' => 'up'])
        ->and($stats[2]['change'])->toBe(['delta' => 0.4, 'direction' => 'down', 'unit' => 'pp'])
        ->and($stats[3]['change'])->toBe(['delta' => 0.1, 'direction' => 'up', 'unit' => 'pp'])
        ->and(StatsOverviewWidget::make()->stat('A', 1)->trend(1, 'up', 'pp')->data()['stats'][0]['change']['unit'])->toBe('pp');
});

// 2. The list widget

it('lists rows without a table header: caption, secondary text, toned badge, link', function (): void {
    app()->setLocale('en');
    $widget = ListWidget::make()->title('Очереди')->size(4)
        ->item('Заявки', '3 ч', 31, 'neutral', '/screens/queue?tab=requests', 'requests')
        ->items([['label' => 'Обращения', 'badge' => 12, 'tone' => 'danger']])
        ->emptyText('Нет данных');

    expect($widget->widgetType())->toBe('list')
        ->and($widget->data())->toBe([
            'items' => [
                ['label' => 'Заявки', 'secondary' => '3 ч', 'badge' => 31, 'tone' => 'neutral', 'url' => '/screens/queue?tab=requests', 'key' => 'requests'],
                ['label' => 'Обращения', 'badge' => 12, 'tone' => 'danger'],
            ],
            // A caption is translated per request.
            'emptyText' => 'No data',
        ])
        ->and(ListWidget::make()->data())->toBe(['items' => [], 'emptyText' => null]);
});

// 3. A progress column

it('describes a progress column: 0..100 by default, 0..1 on request', function (): void {
    expect(TableColumn::make('share')->asProgress()->toArray())->toMatchArray([
        'preset' => 'progress',
        'type' => 'progress',
        'meta' => ['max' => 100, 'decimals' => 0, 'tone' => null],
    ])
        ->and(TableColumn::make('ratio')->asProgress(1, 1, 'success')->toArray()['meta'])->toBe(['max' => 1, 'decimals' => 1, 'tone' => 'success'])
        ->and(TableColumn::make('bad')->asProgress(0)->toArray()['meta']['max'])->toBe(100);
});

// 4. A cell's tone

it('computes a cell tone per row and carries it beside the value', function (): void {
    $columns = [
        TableColumn::make('product'),
        TableColumn::make('change')->tone(fn (mixed $v): ?string => $v > 0 ? 'success' : ($v < 0 ? 'danger' : null)),
        TableColumn::make('share')->asProgress()->tone('info'),
        TableColumn::make('author.name')->tone(fn (mixed $v, array $row): string => $row['author']['vip'] ? 'warning' : 'neutral'),
    ];
    $rows = TableColumn::formatRows($columns, [
        ['product' => 'ВЗР', 'change' => 18, 'share' => 33, 'author' => ['name' => 'Ann', 'vip' => true]],
        ['product' => 'ИФЛ', 'change' => -4, 'share' => 4, 'author' => ['name' => 'Bob', 'vip' => false]],
        ['product' => 'КАСКО', 'change' => 0, 'share' => 0, 'author' => ['name' => 'Cid', 'vip' => false]],
    ]);

    expect($rows[0]['__tones'])->toBe(['change' => 'success', 'share' => 'info', 'author.name' => 'warning'])
        ->and($rows[1]['__tones'])->toBe(['change' => 'danger', 'share' => 'info', 'author.name' => 'neutral'])
        ->and($rows[2]['__tones'])->toBe(['share' => 'info', 'author.name' => 'neutral'])
        // The values stay as they were.
        ->and($rows[1]['change'])->toBe(-4)
        // Without tones the rows are untouched.
        ->and(TableColumn::formatRows([TableColumn::make('a')], [['a' => 1]]))->toBe([['a' => 1]]);
});

it('puts the tones of a table widget and a recent list beside their rows', function (): void {
    TestResourceUserModel::create(['name' => 'Ann', 'status' => 'sent']);
    TestResourceUserModel::create(['name' => 'Bob', 'status' => null]);
    $tone = fn (mixed $v): ?string => $v === 'sent' ? 'success' : null;

    $table = TableWidget::make()->model(TestResourceUserModel::class)
        ->columns([TableColumn::make('name'), TableColumn::make('status')->tone($tone)])
        ->orderBy('id', 'asc')
        ->data();
    expect($table['rows'][0]['__tones'])->toBe(['status' => 'success'])
        ->and($table['rows'][1])->not->toHaveKey('__tones');

    $recent = RecentListWidget::make()->model(TestResourceUserModel::class)
        ->orderBy('id', 'asc')
        ->column('name')
        ->column(TableColumn::make('status')->tone($tone))
        ->data();
    expect($recent['rows'][0]['__tones'])->toBe(['status' => 'success'])
        ->and($recent['rows'][1])->not->toHaveKey('__tones');
});

// 5. Header links

it('adds header links, filtered by permission and translated', function (): void {
    widgets175ActingAs(['admin.reports']);
    app()->setLocale('en');

    $widget = TableWidget::make()->title('Продажи по продуктам')
        ->headerLink('Открыть', '/screens/sales', 'report')
        ->headerLink('Журнал', '/r/journal', 'journal', 'admin.journal')
        ->headerLinks(['Сводка' => '/dashboard/summary', ['label' => 'Reports', 'url' => '/screens/reports', 'permission' => 'admin.reports']]);

    expect($widget->toArray()['headerLinks'])->toBe([
        ['label' => 'Open', 'url' => '/screens/sales', 'key' => 'report'],
        ['label' => 'Сводка', 'url' => '/dashboard/summary', 'key' => null],
        ['label' => 'Reports', 'url' => '/screens/reports', 'key' => null],
    ])
        // A widget without links keeps its payload as it was.
        ->and(TableWidget::make()->toArray())->not->toHaveKey('headerLinks');
});

// 6–7. Compact numbers with a list, tiles

it('lays the numbers out compactly or as tiles, with a list under them', function (): void {
    $compact = StatsOverviewWidget::make()->title('Отложенные процессы')->compact()
        ->stat('В очереди', 142)
        ->stat('Сбои за сутки', 3, 'danger')
        ->item('Выпуск полиса ВЗР', '14:12', 'ошибка КИАС', 'danger', '/r/processes/15')
        ->data();
    expect($compact['layout'])->toBe('compact')
        ->and($compact['stats'])->toHaveCount(2)
        ->and($compact['items'])->toBe([['label' => 'Выпуск полиса ВЗР', 'secondary' => '14:12', 'badge' => 'ошибка КИАС', 'tone' => 'danger', 'url' => '/r/processes/15']])
        ->and($compact['emptyText'])->toBeNull();

    $tiles = StatsOverviewWidget::make()->tiles()
        ->stat('Обращения', 12, 'danger', null, '/screens/q', 'старейшее 26 ч')->hintTone('danger')
        ->stat('Заявки', 31, null, null, null, '3 ч')
        ->data();
    expect($tiles['layout'])->toBe('tiles')
        ->and($tiles['stats'][0]['hintTone'])->toBe('danger')
        ->and($tiles['stats'][1])->not->toHaveKey('hintTone')
        ->and($tiles)->not->toHaveKey('items');

    // A plain set of cards keeps its payload as it was.
    expect(StatsOverviewWidget::make()->stat('A', 1)->data())->toBe(['stats' => [
        ['label' => 'A', 'value' => 1, 'color' => null, 'icon' => null],
    ]])
        ->and(StatsOverviewWidget::make()->compact()->compact(false)->data())->not->toHaveKey('layout')
        ->and(StatsOverviewWidget::make()->emptyText('Пусто')->data()['items'])->toBe([]);
});

// 8. Action buttons

it('shows the action buttons the user may use, the panel’s «+ Create» first', function (): void {
    app(MenuRegistry::class)->withAuto(false)->quickCreate([
        QuickCreate::make('upload', 'Upload', '/screens/upload')->permission('admin.upload'),
    ]);
    $widget = ActionsWidget::make()->title('Создать')->actions([
        QuickCreate::resource(TestQueueResource::class, 'Queue item')->icon('plus'),
        QuickCreate::make('docs', 'Docs', 'https://example.com/docs'),
        QuickCreate::make('secret', 'Secret', '/screens/secret')->permission('admin.secret'),
        QuickCreate::resource('missing'),
    ]);
    expect($widget->widgetType())->toBe('actions');

    widgets175ActingAs(['admin.queue.view', 'admin.queue.create']);
    expect($widget->data()['items'])->toBe([
        ['key' => 'resource.test-queue', 'label' => 'Queue item', 'url' => '/r/test-queue/create', 'icon' => 'plus'],
        ['key' => 'docs', 'label' => 'Docs', 'url' => 'https://example.com/docs', 'icon' => null],
    ]);

    widgets175ActingAs(['admin.upload', 'admin.secret']);
    expect(array_column($widget->fromQuickCreate()->data()['items'], 'key'))->toBe(['upload', 'docs', 'secret']);
});

it('resolves the actions only in data(), skipping a resource the panel does not have', function (): void {
    // Declaring the widget does not look the resource up; data() does.
    $widget = ActionsWidget::make()->title('Создать')->size(12)->actions([QuickCreate::resource('not-registered')]);
    widgets175ActingAs(['*']);
    expect($widget->data()['items'])->toBe([]);
});

it('marks a stat whose fall is good', function (): void {
    $stats = StatsOverviewWidget::make()
        ->stat('Сбои оформления', 0.6)->share()->lowerIsBetter()
        ->stat('Договоров', 10)
        ->data()['stats'];

    expect($stats[0]['lowerIsBetter'])->toBeTrue()
        ->and($stats[1])->not->toHaveKey('lowerIsBetter');
});
