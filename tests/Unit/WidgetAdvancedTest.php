<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Layout\Dashboard;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdmin\Widget\GaugeWidget;
use Dskripchenko\LaravelAdmin\Widget\HeatmapWidget;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Dskripchenko\LaravelAdmin\Widget\TableWidget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->timestamps();
        });
    }
});

it('TableWidget assembles columns + rows from Eloquent', function (): void {
    TestResourceUserModel::create(['name' => 'A', 'email' => 'a@example.com']);
    TestResourceUserModel::create(['name' => 'B', 'email' => 'b@example.com']);

    $w = TableWidget::make()
        ->model(TestResourceUserModel::class)
        ->columns([
            TableColumn::make('name')->label('Имя'),
            TableColumn::make('email')->copyable(),
        ])
        ->orderBy('id', 'asc')
        ->limit(10);

    $data = $w->data();
    expect($data['rows'])->toHaveCount(2);
    expect($data['rows'][0]['name'])->toBe('A');
    expect($data['columns'])->toHaveCount(2);
    expect($data['columns'][0]['label'])->toBe('Имя');
});

it('TableWidget::query applies custom builder modifier', function (): void {
    TestResourceUserModel::create(['name' => 'A']);
    TestResourceUserModel::create(['name' => 'Bob']);

    $w = TableWidget::make()
        ->model(TestResourceUserModel::class)
        ->columns([TableColumn::make('name')])
        ->query(fn ($q) => $q->where('name', 'like', 'B%'));

    $rows = $w->data()['rows'];
    expect($rows)->toHaveCount(1);
    expect($rows[0]['name'])->toBe('Bob');
});

it('TableWidget without model returns empty rows', function (): void {
    $data = TableWidget::make()->columns([TableColumn::make('a')])->data();
    expect($data['rows'])->toBe([]);
    expect($data['columns'])->toHaveCount(1);
});

it('HeatmapWidget stores axes/matrix/colorScale', function (): void {
    $w = HeatmapWidget::make()
        ->axes(['Mon', 'Tue', 'Wed'], ['Morning', 'Afternoon'])
        ->matrix([[1, 2], [3, 4], [5, 6]])
        ->colorScale('plasma');

    $data = $w->data();
    expect($data['rows'])->toBe(['Mon', 'Tue', 'Wed']);
    expect($data['cols'])->toBe(['Morning', 'Afternoon']);
    expect($data['matrix'])->toBe([[1, 2], [3, 4], [5, 6]]);
    expect($data['colorScale'])->toBe('plasma');
});

it('GaugeWidget stores value/range/unit/thresholds', function (): void {
    $w = GaugeWidget::make()
        ->value(75)
        ->range(0, 100)
        ->threshold(0, 50, 'green')
        ->threshold(50, 80, 'yellow')
        ->threshold(80, 100, 'red')
        ->unit('%');

    $data = $w->data();
    expect($data['value'])->toBe(75.0);
    expect($data['min'])->toBe(0.0);
    expect($data['max'])->toBe(100.0);
    expect($data['unit'])->toBe('%');
    expect($data['thresholds'])->toHaveCount(3);
});

it('GaugeWidget::range rejects max <= min', function (): void {
    expect(fn () => GaugeWidget::make()->range(100, 50))
        ->toThrow(InvalidArgumentException::class);
});

it('Dashboard layout aggregates widgets and stores grid props', function (): void {
    $d = Dashboard::make([
        StatsOverviewWidget::make()->size(4)->title('Users'),
        StatsOverviewWidget::make()->size(8)->title('Revenue'),
    ])->gridColumns(12)->gap('1rem')->key('main-dashboard');

    $arr = $d->toArray();
    expect($arr['type'])->toBe('dashboard');
    expect($arr['children'])->toHaveCount(2);
    expect($arr['children'][0]['kind'])->toBe('widget');
    expect($arr['children'][0]['size'])->toBe(4);
    expect($arr['props']['gridColumns'])->toBe(12);
    expect($arr['props']['gap'])->toBe('1rem');
    expect($arr['props']['key'])->toBe('main-dashboard');
});

it('Dashboard::add appends widget fluently', function (): void {
    $d = Dashboard::make()
        ->add(StatsOverviewWidget::make())
        ->add(StatsOverviewWidget::make());
    expect($d->toArray()['children'])->toHaveCount(2);
});

it('Layout::dashboard factory returns Dashboard instance', function (): void {
    expect(Layout::dashboard()->type())->toBe('dashboard');
});

it('Dashboard filters out invisible widgets on toArray', function (): void {
    $d = Dashboard::make([
        StatsOverviewWidget::make()->title('shown'),
        StatsOverviewWidget::make()->canSee(false),
    ]);
    expect($d->toArray()['children'])->toHaveCount(1);
});
