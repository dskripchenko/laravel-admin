<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Layout\Accordion;
use Dskripchenko\LaravelAdmin\Layout\Drawer;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Layout\Modal;
use Dskripchenko\LaravelAdmin\Layout\Step;
use Dskripchenko\LaravelAdmin\Layout\Wizard;
use Dskripchenko\LaravelAdmin\Layout\Wrapper;

it('Accordion::make creates sections from assoc array', function (): void {
    $a = Accordion::make([
        'Section A' => [Input::make('a')],
        'Section B' => [Input::make('b')],
    ])->multi();

    $arr = $a->toArray();
    expect($arr['type'])->toBe('accordion');
    expect($arr['props']['sections'])->toHaveCount(2);
    expect($arr['props']['sections'][0]['title'])->toBe('Section A');
    expect($arr['props']['sections'][0]['children'])->toHaveCount(1);
    expect($arr['props']['multi'])->toBeTrue();
});

it('Accordion::section appends a section with defaultOpen', function (): void {
    $a = Accordion::make()->section('First', [Input::make('x')], true);
    $arr = $a->toArray();
    expect($arr['props']['sections'][0]['defaultOpen'])->toBeTrue();
});

it('Modal stores title/size/dismissable/footer', function (): void {
    $m = Modal::make('Confirm', [Input::make('reason')])
        ->size('lg')
        ->dismissable(false)
        ->footer([Button::make('OK')]);

    $arr = $m->toArray();
    expect($arr['type'])->toBe('modal');
    expect($arr['props']['title'])->toBe('Confirm');
    expect($arr['props']['size'])->toBe('lg');
    expect($arr['props']['dismissable'])->toBeFalse();
    expect($arr['props']['footer'])->toHaveCount(1);
    expect($arr['children'])->toHaveCount(1);
});

it('Drawer rejects invalid position', function (): void {
    expect(fn () => Drawer::make()->position('center'))
        ->toThrow(InvalidArgumentException::class);
});

it('Drawer accepts valid positions and stores props', function (): void {
    $d = Drawer::make('Side', [Input::make('x')])
        ->position('left')
        ->size('md');

    $arr = $d->toArray();
    expect($arr['type'])->toBe('drawer');
    expect($arr['props']['position'])->toBe('left');
    expect($arr['props']['size'])->toBe('md');
});

it('Wrapper has type=wrapper and stores tag', function (): void {
    $w = Wrapper::make([Input::make('a')])->tag('section');
    $arr = $w->toArray();
    expect($arr['type'])->toBe('wrapper');
    expect($arr['props']['tag'])->toBe('section');
    expect($arr['children'])->toHaveCount(1);
});

it('Wizard with Steps stores submitMethod and freeForm', function (): void {
    $w = Wizard::make([
        Step::make('Personal', [Input::make('name')])->description('User info'),
        Step::make('Confirm', [Input::make('password')])
            ->rules(['password' => ['required']]),
    ])->submit('createUser')->freeForm();

    $arr = $w->toArray();
    expect($arr['type'])->toBe('wizard');
    expect($arr['props']['submitMethod'])->toBe('createUser');
    expect($arr['props']['freeForm'])->toBeTrue();
    expect($arr['children'])->toHaveCount(2);
    expect($arr['children'][0]['type'])->toBe('step');
    expect($arr['children'][0]['props']['title'])->toBe('Personal');
    expect($arr['children'][0]['props']['description'])->toBe('User info');
    expect($arr['children'][1]['props']['rules'])->toBe(['password' => ['required']]);
});

it('Wizard::persistKey stores key for state persistence', function (): void {
    $w = Wizard::make()->persistKey('import-wizard');
    expect($w->toArray()['props']['persistKey'])->toBe('import-wizard');
});

it('Wizard::persistKey rejects empty key', function (): void {
    expect(fn () => Wizard::make()->persistKey('  '))
        ->toThrow(InvalidArgumentException::class);
});

it('Layout::accordion/modal/drawer/wrapper/wizard/step factories work', function (): void {
    expect(Layout::accordion(['A' => [Input::make('x')]])->type())->toBe('accordion');
    expect(Layout::modal('T')->type())->toBe('modal');
    expect(Layout::drawer('T')->type())->toBe('drawer');
    expect(Layout::wrapper([])->type())->toBe('wrapper');
    expect(Layout::wizard([])->type())->toBe('wizard');
    expect(Layout::step('T')->type())->toBe('step');
});

it('Step::icon stores icon prop', function (): void {
    $s = Step::make('T')->icon('user');
    expect($s->toArray()['props']['icon'])->toBe('user');
});
