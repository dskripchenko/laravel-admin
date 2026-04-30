<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\BulkAction;
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Action\DropDown;
use Dskripchenko\LaravelAdmin\Action\Link;
use Dskripchenko\LaravelAdmin\Action\ModalAction;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;

it('BulkAction has type=bulk and bulk-only position by default', function (): void {
    $a = BulkAction::make('Удалить выделенные')
        ->method('bulkDelete')
        ->requiresAtLeast(1)
        ->requiresAtMost(100);

    $arr = $a->toArray();
    expect($arr['type'])->toBe('bulk');
    expect($arr['position'])->toBe(['bulk']);
    expect($arr['attributes']['method'])->toBe('bulkDelete');
    expect($arr['attributes']['requiresAtLeast'])->toBe(1);
    expect($arr['attributes']['requiresAtMost'])->toBe(100);
});

it('BulkAction::requiresAtLeast clamps to 1 minimum', function (): void {
    $a = BulkAction::make('X')->requiresAtLeast(0);
    expect($a->toArray()['attributes']['requiresAtLeast'])->toBe(1);
});

it('ModalAction has type=modal and serializes fields', function (): void {
    $a = ModalAction::make('Отправить')
        ->method('sendNotification')
        ->fields([
            Input::make('subject')->required(),
            Textarea::make('body')->rows(5),
        ])
        ->modalSize('lg')
        ->modalTitle('Уведомление')
        ->submitLabel('Отправить');

    $arr = $a->toArray();
    expect($arr['type'])->toBe('modal');
    expect($arr['attributes']['method'])->toBe('sendNotification');
    expect($arr['attributes']['fields'])->toHaveCount(2);
    expect($arr['attributes']['fields'][0]['name'])->toBe('subject');
    expect($arr['attributes']['modalSize'])->toBe('lg');
    expect($arr['attributes']['modalTitle'])->toBe('Уведомление');
    expect($arr['attributes']['submitLabel'])->toBe('Отправить');
});

it('DropDown has type=dropdown and serializes nested items', function (): void {
    $d = DropDown::make('Ещё')
        ->items([
            Button::make('Replicate')->withName('replicate'),
            Link::make('Audit')->href('/admin/audit'),
        ]);

    $arr = $d->toArray();
    expect($arr['type'])->toBe('dropdown');
    expect($arr['items'])->toHaveCount(2);
    expect($arr['items'][0]['type'])->toBe('button');
    expect($arr['items'][1]['type'])->toBe('link');
});

it('DropDown::add appends item fluently', function (): void {
    $d = DropDown::make('More')
        ->add(Button::make('A'))
        ->add(Button::make('B'));
    expect($d->toArray()['items'])->toHaveCount(2);
});

it('DropDown filters out invisible items', function (): void {
    $d = DropDown::make('More')
        ->add(Button::make('Visible'))
        ->add(Button::make('Hidden')->canSee(false));

    $items = $d->toArray()['items'];
    expect($items)->toHaveCount(1);
    expect($items[0]['label'])->toBe('Visible');
});

it('all advanced actions inherit __call attribute setters', function (): void {
    $a = BulkAction::make('X')->icon('trash')->color('red');
    $arr = $a->toArray();
    expect($arr['icon'])->toBe('trash');
    expect($arr['attributes']['color'])->toBe('red');
});
