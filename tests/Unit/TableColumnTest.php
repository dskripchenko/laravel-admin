<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Table\TableColumn;

it('TableColumn::make exposes name and humanized label by default', function (): void {
    $col = TableColumn::make('user_email');
    $arr = $col->toArray();

    expect($arr['name'])->toBe('user_email');
    expect($arr['label'])->toBe('User email');
});

it('label() overrides default', function (): void {
    $col = TableColumn::make('email')->label('Электронная почта');
    expect($col->toArray()['label'])->toBe('Электронная почта');
});

it('sort/search/copyable flags', function (): void {
    $col = TableColumn::make('name')->sort()->search()->copyable();
    $arr = $col->toArray();
    expect($arr['sortable'])->toBeTrue();
    expect($arr['searchable'])->toBeTrue();
    expect($arr['copyable'])->toBeTrue();
});

it('width/align/defaultHidden/cantHide', function (): void {
    $col = TableColumn::make('name')
        ->width('200px')
        ->align('center')
        ->defaultHidden()
        ->cantHide();
    $arr = $col->toArray();
    expect($arr['width'])->toBe('200px');
    expect($arr['align'])->toBe('center');
    expect($arr['defaultHidden'])->toBeTrue();
    expect($arr['cantHide'])->toBeTrue();
});

it('as() preset with meta', function (): void {
    $col = TableColumn::make('amount')->as('money', ['currency' => 'RUB']);
    $arr = $col->toArray();
    expect($arr['preset'])->toBe('money');
    expect($arr['type'])->toBe('money');
    expect($arr['meta'])->toBe(['currency' => 'RUB']);
});

it('editable() enables inline-edit with rules', function (): void {
    $col = TableColumn::make('is_active')->editable(['boolean']);
    $arr = $col->toArray();
    expect($arr['editable'])->toBe(['field' => 'is_active', 'validation' => ['boolean']]);
});

it('summary() lists aggregates', function (): void {
    $col = TableColumn::make('amount')->summary(['sum', 'avg']);
    $arr = $col->toArray();
    expect($arr['summary'])->toBe(['sum', 'avg']);
});

it('summary defaults to null when empty', function (): void {
    $col = TableColumn::make('id');
    expect($col->toArray()['summary'])->toBeNull();
});

it('isSortable / isSearchable expose flags', function (): void {
    $col = TableColumn::make('name')->sort()->search();
    expect($col->isSortable())->toBeTrue();
    expect($col->isSearchable())->toBeTrue();

    $col2 = TableColumn::make('id');
    expect($col2->isSortable())->toBeFalse();
    expect($col2->isSearchable())->toBeFalse();
});
