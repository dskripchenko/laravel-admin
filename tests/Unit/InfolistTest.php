<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Infolist\BadgeEntry;
use Dskripchenko\LaravelAdmin\Infolist\ColorEntry;
use Dskripchenko\LaravelAdmin\Infolist\IconEntry;
use Dskripchenko\LaravelAdmin\Infolist\ImageEntry;
use Dskripchenko\LaravelAdmin\Infolist\KeyValueEntry;
use Dskripchenko\LaravelAdmin\Infolist\MapEntry;
use Dskripchenko\LaravelAdmin\Infolist\RelationEntry;
use Dskripchenko\LaravelAdmin\Infolist\RepeatableEntry;
use Dskripchenko\LaravelAdmin\Infolist\TextEntry;
use Dskripchenko\LaravelAdmin\Layout\Infolist;
use Dskripchenko\LaravelAdmin\Layout\Layout;

it('TextEntry has type=text and exposes label/help/copyable', function (): void {
    $e = TextEntry::make('email')
        ->label('Email')
        ->help('Рабочий email')
        ->copyable();

    $arr = $e->toArray();
    expect($arr['type'])->toBe('text');
    expect($arr['name'])->toBe('email');
    expect($arr['label'])->toBe('Email');
    expect($arr['help'])->toBe('Рабочий email');
    expect($arr['attributes']['copyable'])->toBeTrue();
});

it('TextEntry::asMoney sets preset/currency/decimals', function (): void {
    $e = TextEntry::make('price')->asMoney('USD', 2);
    $arr = $e->toArray();
    expect($arr['attributes']['preset'])->toBe('money');
    expect($arr['attributes']['currency'])->toBe('USD');
    expect($arr['attributes']['decimals'])->toBe(2);
});

it('BadgeEntry has type=badge and stores colors/labels maps', function (): void {
    $e = BadgeEntry::make('status')
        ->colors(['active' => 'green', 'banned' => 'red'])
        ->labels(['active' => 'Активен', 'banned' => 'Забанен']);

    $arr = $e->toArray();
    expect($arr['type'])->toBe('badge');
    expect($arr['attributes']['colors'])->toBe(['active' => 'green', 'banned' => 'red']);
    expect($arr['attributes']['labels'])->toBe(['active' => 'Активен', 'banned' => 'Забанен']);
});

it('IconEntry has type=icon and stores icons map', function (): void {
    $e = IconEntry::make('type')->icons(['user' => 'user', 'admin' => 'shield'])->size('lg');
    $arr = $e->toArray();
    expect($arr['type'])->toBe('icon');
    expect($arr['attributes']['icons'])->toBe(['user' => 'user', 'admin' => 'shield']);
    expect($arr['attributes']['size'])->toBe('lg');
});

it('ColorEntry has type=color', function (): void {
    $e = ColorEntry::make('bg')->format('hex')->showValue();
    $arr = $e->toArray();
    expect($arr['type'])->toBe('color');
    expect($arr['attributes']['format'])->toBe('hex');
    expect($arr['attributes']['showValue'])->toBeTrue();
});

it('KeyValueEntry has type=key_value with labels', function (): void {
    $e = KeyValueEntry::make('meta')->keyLabel('Свойство')->valueLabel('Значение');
    $arr = $e->toArray();
    expect($arr['type'])->toBe('key_value');
    expect($arr['attributes']['keyLabel'])->toBe('Свойство');
    expect($arr['attributes']['valueLabel'])->toBe('Значение');
});

it('RepeatableEntry has type=repeatable and serializes nested entries', function (): void {
    $e = RepeatableEntry::make('items')
        ->entries([
            TextEntry::make('name'),
            BadgeEntry::make('status'),
        ])
        ->layout('columns');

    $arr = $e->toArray();
    expect($arr['type'])->toBe('repeatable');
    expect($arr['attributes']['entries'])->toHaveCount(2);
    expect($arr['attributes']['entries'][0]['type'])->toBe('text');
    expect($arr['attributes']['layout'])->toBe('columns');
});

it('ImageEntry has type=image with size/rounded/clickToZoom', function (): void {
    $e = ImageEntry::make('avatar')->size(200, 200)->rounded()->clickToZoom();
    $arr = $e->toArray();
    expect($arr['type'])->toBe('image');
    expect($arr['attributes']['width'])->toBe(200);
    expect($arr['attributes']['rounded'])->toBeTrue();
    expect($arr['attributes']['clickToZoom'])->toBeTrue();
});

it('RelationEntry has type=relation with relation/linkTo', function (): void {
    $e = RelationEntry::make('author')
        ->relation('author')
        ->display('name')
        ->linkTo('users');

    $arr = $e->toArray();
    expect($arr['type'])->toBe('relation');
    expect($arr['attributes']['relation'])->toBe('author');
    expect($arr['attributes']['displayColumn'])->toBe('name');
    expect($arr['attributes']['linkTo'])->toBe('users');
});

it('MapEntry has type=map with lat/lng/zoom', function (): void {
    $e = MapEntry::make('location')
        ->latColumn('lat')
        ->lngColumn('lng')
        ->zoom(12)
        ->height(400);

    $arr = $e->toArray();
    expect($arr['type'])->toBe('map');
    expect($arr['attributes']['latColumn'])->toBe('lat');
    expect($arr['attributes']['zoom'])->toBe(12);
    expect($arr['attributes']['height'])->toBe(400);
});

it('Entry::canSee(false) hides entry from output', function (): void {
    $e = TextEntry::make('secret')->canSee(false);
    expect($e->isVisible())->toBeFalse();
});

it('Infolist layout aggregates entries and has columns prop', function (): void {
    $info = Infolist::make([
        TextEntry::make('name'),
        BadgeEntry::make('status'),
    ])->layout('columns')->gridColumns(2);

    $arr = $info->toArray();
    expect($arr['type'])->toBe('infolist');
    expect($arr['children'])->toHaveCount(2);
    expect($arr['props']['layout'])->toBe('columns');
    expect($arr['props']['columns'])->toBe(2);
});

it('Infolist::add appends children fluently', function (): void {
    $info = Infolist::make()
        ->add(TextEntry::make('a'))
        ->add(TextEntry::make('b'));
    expect($info->toArray()['children'])->toHaveCount(2);
});

it('Layout::infolist factory returns Infolist instance', function (): void {
    expect(Layout::infolist([TextEntry::make('x')])->type())->toBe('infolist');
});

it('Infolist filters out invisible entries on toArray', function (): void {
    $info = Infolist::make([
        TextEntry::make('shown'),
        TextEntry::make('hidden')->canSee(false),
    ]);
    $arr = $info->toArray();
    expect($arr['children'])->toHaveCount(1);
    expect($arr['children'][0]['name'])->toBe('shown');
});
