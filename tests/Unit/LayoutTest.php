<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Layout\Block;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Layout\View;

it('Layout::rows produces Rows instance with type=rows', function (): void {
    $layout = Layout::rows([Input::make('name')]);

    expect($layout)->toBeInstanceOf(Rows::class);
    expect($layout->type())->toBe('rows');
});

it('serializes Rows to JSON-friendly array', function (): void {
    $layout = Layout::rows([
        Input::make('name')->required()->title('Имя'),
        Input::make('email')->title('Email'),
    ]);

    $arr = $layout->toArray();

    expect($arr['type'])->toBe('rows');
    expect($arr['children'])->toHaveCount(2);
    expect($arr['children'][0]['name'])->toBe('name');
    expect($arr['children'][0]['required'])->toBeTrue();
});

it('Layout::columns supports ratios and gap', function (): void {
    $layout = Layout::columns([Input::make('a'), Input::make('b')])
        ->ratios([1, 2])
        ->gap(4);

    $arr = $layout->toArray();
    expect($arr['type'])->toBe('columns');
    expect($arr['props']['ratios'])->toBe([1, 2]);
    expect($arr['props']['gap'])->toBe(4);
});

it('Layout::tabs builds labels + child layouts', function (): void {
    $layout = Layout::tabs([
        'Профиль' => Layout::rows([Input::make('name')]),
        'Безопасность' => [Input::make('password')],
    ]);

    $arr = $layout->toArray();
    expect($arr['type'])->toBe('tabs');
    expect($arr['props']['labels'])->toBe(['Профиль', 'Безопасность']);
    expect($arr['children'])->toHaveCount(2);
});

it('Layout::block has title/description/icon props', function (): void {
    $block = Block::make('Контакт', [Input::make('email')])
        ->description('Контактные данные')
        ->icon('user');

    $arr = $block->toArray();
    expect($arr['props']['title'])->toBe('Контакт');
    expect($arr['props']['description'])->toBe('Контактные данные');
    expect($arr['props']['icon'])->toBe('user');
});

it('Layout::view wraps arbitrary Vue component', function (): void {
    $layout = View::make('CustomChart', ['range' => '7d']);
    $arr = $layout->toArray();

    expect($arr['type'])->toBe('view');
    expect($arr['props']['component'])->toBe('CustomChart');
    expect($arr['props']['range'])->toBe('7d');
});

it('hidden children are filtered out of toArray', function (): void {
    $layout = Layout::rows([
        Input::make('visible'),
        Input::make('hidden')->canSee(false),
    ]);

    $arr = $layout->toArray();
    expect($arr['children'])->toHaveCount(1);
    expect($arr['children'][0]['name'])->toBe('visible');
});

it('Layout has stable id and toJson works', function (): void {
    $layout = Layout::rows([])->withId('main-form');
    expect($layout->id())->toBe('main-form');

    $json = $layout->toJson();
    $decoded = json_decode($json, true);
    expect($decoded['id'])->toBe('main-form');
});
