<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Widget\ChartWidget;
use Dskripchenko\LaravelAdmin\Widget\IframeWidget;
use Dskripchenko\LaravelAdmin\Widget\MarkdownWidget;
use Dskripchenko\LaravelAdmin\Widget\RecentListWidget;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
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

it('Widget::slug derives from class basename without Widget suffix', function (): void {
    expect(StatsOverviewWidget::slug())->toBe('stats-overview');
    expect(ChartWidget::slug())->toBe('chart');
});

it('Widget shared API: title/size/refresh/permission', function (): void {
    $w = StatsOverviewWidget::make()
        ->title('Today')
        ->size(8)
        ->refresh(60)
        ->permission('admin.dashboard.view');

    $arr = $w->toArray();
    expect($arr['title'])->toBe('Today');
    expect($arr['size'])->toBe(8);
    expect($arr['refresh'])->toBe(60);
    expect($arr['permission'])->toBe('admin.dashboard.view');
});

it('Widget::size clamps to 1..12', function (): void {
    expect(StatsOverviewWidget::make()->size(0)->toArray()['size'])->toBe(1);
    expect(StatsOverviewWidget::make()->size(99)->toArray()['size'])->toBe(12);
});

it('StatsOverviewWidget collects stats with optional trend', function (): void {
    $w = StatsOverviewWidget::make()
        ->stat('Users', 1234, 'green', 'users')
        ->trend(12.5, 'up')
        ->stat('Orders', 567, 'blue');

    $data = $w->data();
    expect($data['stats'])->toHaveCount(2);
    expect($data['stats'][0]['label'])->toBe('Users');
    expect($data['stats'][0]['value'])->toBe(1234);
    expect($data['stats'][0]['change'])->toBe(['delta' => 12.5, 'direction' => 'up']);
    expect($data['stats'][1])->not->toHaveKey('change');
});

it('ChartWidget rejects invalid chart type', function (): void {
    expect(fn () => ChartWidget::make()->chartType('candlestick'))
        ->toThrow(InvalidArgumentException::class);
});

it('ChartWidget assembles labels + datasets + stacked', function (): void {
    $w = ChartWidget::make()
        ->chartType('bar')
        ->labels(['Jan', 'Feb', 'Mar'])
        ->dataset('Revenue', [100, 200, 300], 'green')
        ->dataset('Costs', [50, 80, 90])
        ->stacked();

    $data = $w->data();
    expect($data['chartType'])->toBe('bar');
    expect($data['labels'])->toBe(['Jan', 'Feb', 'Mar']);
    expect($data['datasets'])->toHaveCount(2);
    expect($data['datasets'][0]['color'])->toBe('green');
    expect($data['datasets'][1])->not->toHaveKey('color');
    expect($data['stacked'])->toBeTrue();
});

it('RecentListWidget loads rows from Eloquent', function (): void {
    TestResourceUserModel::create(['name' => 'Old', 'email' => 'old@example.com']);
    sleep(1);
    TestResourceUserModel::create(['name' => 'New', 'email' => 'new@example.com']);

    $w = RecentListWidget::make()
        ->model(TestResourceUserModel::class)
        ->orderBy('id', 'desc')
        ->limit(2)
        ->column('name', 'Имя')
        ->column('email')
        ->linkTo('users');

    $data = $w->data();
    expect($data['rows'])->toHaveCount(2);
    expect($data['rows'][0]['name'])->toBe('New');
    expect($data['columns'])->toBe([
        ['column' => 'name', 'label' => 'Имя'],
        ['column' => 'email', 'label' => 'email'],
    ]);
    expect($data['linkTo'])->toBe('users');
});

it('RecentListWidget without model returns empty rows', function (): void {
    $data = RecentListWidget::make()->data();
    expect($data['rows'])->toBe([]);
});

it('MarkdownWidget accepts string and callable content', function (): void {
    $static = MarkdownWidget::make()->content('# Hello');
    expect($static->data()['content'])->toBe('# Hello');

    $dynamic = MarkdownWidget::make()->content(fn () => '## Dynamic '.date('Y'));
    expect($dynamic->data()['content'])->toContain('Dynamic '.date('Y'));
});

it('IframeWidget stores src/height/sandbox', function (): void {
    $w = IframeWidget::make()
        ->src('https://grafana.example.com/panel')
        ->height(400)
        ->sandbox('allow-scripts');

    $data = $w->data();
    expect($data['src'])->toBe('https://grafana.example.com/panel');
    expect($data['height'])->toBe(400);
    expect($data['sandbox'])->toBe('allow-scripts');
});

it('IframeWidget rejects URL outside allowedHosts', function (): void {
    expect(fn () => IframeWidget::make()
        ->allowedHosts(['*.trusted.com'])
        ->src('https://attacker.com/steal'))
        ->toThrow(InvalidArgumentException::class);
});

it('IframeWidget accepts URL matching allowedHosts pattern', function (): void {
    $w = IframeWidget::make()
        ->allowedHosts(['*.trusted.com'])
        ->src('https://grafana.trusted.com/panel');

    expect($w->data()['src'])->toBe('https://grafana.trusted.com/panel');
});

it('Widget::canSee(false) hides widget from output', function (): void {
    $w = StatsOverviewWidget::make()->canSee(false);
    expect($w->isVisible())->toBeFalse();
});

it('Widget::toArray includes type=widget marker and widgetType', function (): void {
    $w = StatsOverviewWidget::make();
    $arr = $w->toArray();
    expect($arr['kind'])->toBe('widget');
    expect($arr['type'])->toBe('stats');
});
