<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Action\Link;

it('Button::make derives name from label', function (): void {
    $btn = Button::make('Save');
    expect($btn->name())->toBe('save');
    expect($btn->label())->toBe('Save');
});

it('Button::method() stores method attribute', function (): void {
    $btn = Button::make('Save')->method('save');
    $arr = $btn->toArray();
    expect($arr['attributes']['method'])->toBe('save');
});

it('Button supports primary and destructive flags', function (): void {
    $btn = Button::make('Delete')->primary()->destructive();
    $arr = $btn->toArray();
    expect($arr['primary'])->toBeTrue();
    expect($arr['destructive'])->toBeTrue();
});

it('confirm() accepts string and wraps it', function (): void {
    $btn = Button::make('Delete')->confirm('Are you sure?');
    $arr = $btn->toArray();
    expect($arr['confirm']['message'])->toBe('Are you sure?');
    expect($arr['confirm']['title'])->toBe('Подтверждение');
});

it('Link::href + target', function (): void {
    $link = Link::make('Open')->href('/admin')->target('_blank');
    $arr = $link->toArray();
    expect($arr['type'])->toBe('link');
    expect($arr['attributes']['href'])->toBe('/admin');
    expect($arr['attributes']['target'])->toBe('_blank');
});

it('action permission and position', function (): void {
    $btn = Button::make('Activate')
        ->permission('admin.users.update')
        ->position(['row', 'bulk']);

    $arr = $btn->toArray();
    expect($arr['permission'])->toBe('admin.users.update');
    expect($arr['position'])->toBe(['row', 'bulk']);
});

it('canSee controls visibility', function (): void {
    $btn = Button::make('Save')->canSee(false);
    expect($btn->isVisible())->toBeFalse();
});

it('withName overrides derived name', function (): void {
    $btn = Button::make('Save')->withName('save_form');
    expect($btn->name())->toBe('save_form');
});
