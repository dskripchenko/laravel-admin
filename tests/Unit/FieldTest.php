<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Hidden;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Label;

it('Input::make stores name and exposes type', function (): void {
    $field = Input::make('email');
    expect($field->name())->toBe('email');
    expect($field->type())->toBe('input');
});

it('fluent __call sets boolean attribute when called without args', function (): void {
    $field = Input::make('email')->required();
    expect($field->getAttribute('required'))->toBeTrue();
});

it('fluent __call sets value attribute when called with one arg', function (): void {
    $field = Input::make('email')->placeholder('user@example.com');
    expect($field->getAttribute('placeholder'))->toBe('user@example.com');
});

it('fluent chain returns same instance for chaining', function (): void {
    $field = Input::make('email');
    expect($field->title('Email'))->toBe($field);
});

it('required() also adds rule "required"', function (): void {
    $field = Input::make('email')->required();
    expect($field->getRules())->toContain('required');
});

it('required(false) does not add rule', function (): void {
    $field = Input::make('email')->required(false);
    expect($field->getRules())->toBeEmpty();
});

it('rules() replaces rule list', function (): void {
    $field = Input::make('email')->rules(['email', 'max:255']);
    expect($field->getRules())->toBe(['email', 'max:255']);
});

it('default() sets defaultValue', function (): void {
    $field = Input::make('locale')->default('ru');
    $arr = $field->toArray();
    expect($arr['defaultValue'])->toBe('ru');
});

it('canSee(false) hides field from output', function (): void {
    $field = Input::make('secret')->canSee(false);
    expect($field->isVisible())->toBeFalse();
});

it('canSee(callable) is evaluated at isVisible()', function (): void {
    $shown = false;
    $field = Input::make('x')->canSee(function () use (&$shown): bool {
        return $shown;
    });
    expect($field->isVisible())->toBeFalse();

    $shown = true;
    expect($field->isVisible())->toBeTrue();
});

it('toArray exposes the standard schema shape', function (): void {
    $field = Input::make('email')
        ->title('Email')
        ->placeholder('user@example.com')
        ->help('Введите рабочий email')
        ->required();

    $arr = $field->toArray();
    expect($arr['kind'])->toBe('field');
    expect($arr['name'])->toBe('email');
    expect($arr['type'])->toBe('input');
    expect($arr['label'])->toBe('Email');
    expect($arr['placeholder'])->toBe('user@example.com');
    expect($arr['help'])->toBe('Введите рабочий email');
    expect($arr['required'])->toBeTrue();
    expect($arr['rules'])->toContain('required');
    expect($arr['visibility'])->toBe(['create' => true, 'update' => true, 'view' => true]);
});

it('onCreate/onUpdate/onView control visibility flags', function (): void {
    $field = Input::make('password')->onCreate()->onUpdate(false)->onView(false);
    $arr = $field->toArray();
    expect($arr['visibility'])->toBe(['create' => true, 'update' => false, 'view' => false]);
});

it('Hidden has type=hidden', function (): void {
    expect(Hidden::make('id')->type())->toBe('hidden');
});

it('Label has type=label', function (): void {
    expect(Label::make('description')->type())->toBe('label');
});

it('withOptions merges options', function (): void {
    $field = Input::make('x')->withOptions(['mask' => '+7 (###) ###-##-##']);
    $arr = $field->toArray();
    expect($arr['options']['mask'])->toBe('+7 (###) ###-##-##');
});
