<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\DatePicker;
use Dskripchenko\LaravelAdmin\Field\Password;
use Dskripchenko\LaravelAdmin\Field\ValidationRulesExporter;
use Dskripchenko\LaravelAdmin\I18n\Localize;
use Dskripchenko\LaravelAdmin\Infolist\RepeatableEntry;
use Dskripchenko\LaravelAdmin\Infolist\TextEntry;
use Dskripchenko\LaravelAdmin\Layout\Accordion;
use Dskripchenko\LaravelAdmin\Layout\Block;
use Dskripchenko\LaravelAdmin\Layout\Drawer;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Layout\Step;
use Dskripchenko\LaravelAdmin\Menu\MenuNode;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Widget\ChartWidget;
use Dskripchenko\LaravelAdmin\Widget\HeatmapWidget;
use Dskripchenko\LaravelAdmin\Widget\RecentListWidget;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;

/**
 * Captions the serialization used to leave in the source language, and the
 * neighbouring defects found while building the public showcase.
 */
beforeEach(function (): void {
    Lang::addLines([
        '*.Выручка' => 'Revenue',
        '*.Январь' => 'January',
        '*.Заказы' => 'Orders',
        '*.Имя' => 'Name',
        '*.Пн' => 'Mon',
        '*.Описание блока' => 'Block description',
        '*.Описание шага' => 'Step description',
        '*.Раздел' => 'Section',
        '*.новое' => 'new',
        // Lines added here mark the JSON translations as loaded, so the
        // package's en.json is not read in this file.
        '*.Удалённые' => 'Trashed',
    ], 'en');
    app()->setLocale('en');
});

it('a caption equal to a translation group name stays a string', function (): void {
    // `__('Validation')` resolves to the validation group's array when the
    // host has lang/en/validation.php.
    Lang::addLines(['validation.required' => 'Required'], 'en');

    expect(Localize::string('validation'))->toBe('validation');
    expect(Localize::attributes(['title' => 'validation']))->toBe(['title' => 'validation']);
});

it('translates the stat, chart and heatmap captions of the built-in widgets', function (): void {
    $stats = StatsOverviewWidget::make()->stat('Выручка', 100)->data();
    expect($stats['stats'][0]['label'])->toBe('Revenue');

    $chart = ChartWidget::make()->labels(['Январь', 2026])->dataset('Заказы', [1, 2])->data();
    expect($chart['labels'])->toBe(['January', 2026]);
    expect($chart['datasets'][0]['label'])->toBe('Orders');

    $heatmap = HeatmapWidget::make()->axes(['Пн'], ['10'])->data();
    expect($heatmap['rows'])->toBe(['Mon']);
});

it('RecentListWidget serves accessor columns and translates column labels', function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->timestamps();
        });
    }
    TestRecentListAccessorModel::query()->create(['name' => 'Alice', 'email' => 'a@example.com']);

    $data = RecentListWidget::make()
        ->model(TestRecentListAccessorModel::class)
        ->orderBy('id')
        ->column('name', 'Имя')
        ->column('shout')
        ->data();

    expect($data['columns'][0]['label'])->toBe('Name');
    expect($data['rows'][0])->toMatchArray(['name' => 'Alice', 'shout' => 'ALICE']);
    expect($data['rows'][0])->toHaveKey('id');
    expect($data['rows'][0])->not->toHaveKey('email');
});

it('translates block and step descriptions and accordion section titles', function (): void {
    $block = Block::make('Блок')->description('Описание блока')->icon('star')->toArray();
    expect($block['description'])->toBe('Block description');
    expect($block['icon'])->toBe('star');

    expect(Step::make('Шаг')->description('Описание шага')->toArray()['description'])
        ->toBe('Step description');

    $accordion = Accordion::make()->section('Раздел', [Rows::make([])])->toArray();
    expect($accordion['sections'][0]['title'])->toBe('Section');
});

it('translates a text menu badge and leaves a count as it is', function (): void {
    $resources = app(ResourceRegistry::class);
    $screens = app(ScreenRegistry::class);

    expect(MenuNode::make('a', 'A')->badge('новое')->toArray($resources, $screens)['badge'])->toBe('new');
    expect(MenuNode::make('b', 'B')->badge(3)->toArray($resources, $screens)['badge'])->toBe(3);
});

it('Drawer serializes dismissable and footer', function (): void {
    $drawer = Drawer::make('Детали')->dismissable(false)->footer([
        Dskripchenko\LaravelAdmin\Action\Button::make('Закрыть')->withName('close'),
    ])->toArray();

    expect($drawer['dismissable'])->toBeFalse();
    expect($drawer['footer'])->toHaveCount(1);
    expect($drawer['footer'][0]['name'])->toBe('close');
});

it('the trashed filter caption is translated per request', function (): void {
    $filter = Dskripchenko\LaravelAdmin\Filter\TrashedFilter::for('trashed');
    expect($filter->toArray()['label'])->toBe('Trashed');

    app()->setLocale('ru');
    expect($filter->toArray()['label'])->toBe('Удалённые');
});

it('an optional DatePicker accepts an empty value; a required one does not', function (): void {
    $rules = ValidationRulesExporter::export([
        DatePicker::make('starts_at'),
        DatePicker::make('ends_at')->required(),
    ]);

    expect($rules['starts_at'])->toBe(['nullable', 'date']);
    expect($rules['ends_at'])->toBe(['required', 'date']);
    expect(validator(['starts_at' => null, 'ends_at' => '2026-01-01'], $rules)->passes())->toBeTrue();
});

it('rules() after confirmed() keeps the confirmed rule', function (): void {
    $field = Password::make('password')->confirmed()->rules(['min:8']);

    expect($field->getRules())->toBe(['min:8', 'confirmed']);
    expect(ValidationRulesExporter::export([$field])['password'])->toContain('confirmed');
});

it('nested repeatable entries keep money settings and get default labels', function (): void {
    $entry = RepeatableEntry::make('lines')->entries([
        TextEntry::make('unit_price')->asMoney('USD', 0),
    ])->toArray();

    $nested = $entry['attributes']['entries'][0];
    expect($nested['label'])->toBe('Unit Price');
    expect($nested['attributes']['meta'])->toBe(['currency' => 'USD', 'decimals' => 0]);

    expect(TextEntry::make('total')->label('')->toArray()['label'])->toBe('');
});

/**
 * @internal
 */
final class TestRecentListAccessorModel extends Illuminate\Database\Eloquent\Model
{
    protected $table = 'users';

    protected $guarded = [];

    public function getShoutAttribute(): string
    {
        return mb_strtoupper((string) $this->getAttribute('name'));
    }
}
