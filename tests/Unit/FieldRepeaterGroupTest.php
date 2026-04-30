<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Group;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Field\Repeater;
use Dskripchenko\LaravelAdmin\Field\Select;

it('Repeater has type=repeater and stores child fields', function (): void {
    $f = Repeater::make('items')
        ->fields([
            Input::make('name')->required(),
            Number::make('qty')->integer(),
            Select::make('unit')->options(['pcs', 'kg']),
        ])
        ->minItems(1)
        ->maxItems(10)
        ->addable()
        ->removable()
        ->reorderable();

    expect($f->fieldType())->toBe('repeater');
    expect($f->getAttribute('fields'))->toHaveCount(3);
    expect($f->getAttribute('fields')[0]['name'])->toBe('name');
    expect($f->getAttribute('minItems'))->toBe(1);
    expect($f->getAttribute('maxItems'))->toBe(10);
    expect($f->getAttribute('addable'))->toBeTrue();
    expect($f->getAttribute('removable'))->toBeTrue();
    expect($f->getAttribute('reorderable'))->toBeTrue();
});

it('Repeater::defaultItem stores default state', function (): void {
    $f = Repeater::make('lines')->defaultItem(['qty' => 1, 'unit' => 'pcs']);
    expect($f->getAttribute('defaultItem'))->toBe(['qty' => 1, 'unit' => 'pcs']);
});

it('Group has type=group and stores child fields', function (): void {
    $f = Group::make('address')
        ->fields([
            Input::make('city'),
            Input::make('street'),
        ])
        ->layout('columns');

    expect($f->fieldType())->toBe('group');
    expect($f->getAttribute('fields'))->toHaveCount(2);
    expect($f->getAttribute('layout'))->toBe('columns');
});

it('Group::collapsed implies collapsible=true', function (): void {
    $f = Group::make('a')->collapsed();
    expect($f->getAttribute('collapsed'))->toBeTrue();
    expect($f->getAttribute('collapsible'))->toBeTrue();
});

it('Group serializes child fields to arrays (not Field objects)', function (): void {
    $f = Group::make('a')->fields([Input::make('x')]);
    $fields = $f->getAttribute('fields');
    expect($fields[0])->toBeArray();
    expect($fields[0]['type'])->toBe('input');
});

it('toArray exposes fields key in attributes', function (): void {
    $f = Repeater::make('items')->fields([Input::make('a')]);
    $arr = $f->toArray();
    expect($arr['attributes']['fields'])->toHaveCount(1);
});
