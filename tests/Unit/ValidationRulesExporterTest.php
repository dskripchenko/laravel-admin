<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\ColorPicker;
use Dskripchenko\LaravelAdmin\Field\DatePicker;
use Dskripchenko\LaravelAdmin\Field\FileUpload;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Field\Password;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Field\TimePicker;
use Dskripchenko\LaravelAdmin\Field\ValidationRulesExporter;

it('exports explicit rules from Input', function (): void {
    $rules = ValidationRulesExporter::export([
        Input::make('email')->required()->rules(['email', 'max:255']),
    ]);

    expect($rules)->toHaveKey('email');
    expect($rules['email'])->toContain('required');
    expect($rules['email'])->toContain('email');
    expect($rules['email'])->toContain('max:255');
});

it('Input with type=email implicitly adds email rule', function (): void {
    $rules = ValidationRulesExporter::export([
        Input::make('e')->type('email')->required(),
    ]);

    expect($rules['e'])->toContain('email');
    expect($rules['e'])->toContain('required');
});

it('Number adds numeric + integer + min/max', function (): void {
    $rules = ValidationRulesExporter::export([
        Number::make('age')->required()->integer()->min(0)->max(120),
    ]);

    expect($rules['age'])->toContain('required');
    expect($rules['age'])->toContain('integer');
    expect($rules['age'])->toContain('min:0');
    expect($rules['age'])->toContain('max:120');
});

it('Number without ->integer() adds numeric (not integer)', function (): void {
    $rules = ValidationRulesExporter::export([
        Number::make('price'),
    ]);

    expect($rules['price'])->toContain('numeric');
    expect($rules['price'])->not->toContain('integer');
});

it('DatePicker adds date rule', function (): void {
    $rules = ValidationRulesExporter::export([
        DatePicker::make('birth')->required(),
    ]);

    expect($rules['birth'])->toContain('required');
    expect($rules['birth'])->toContain('date');
});

it('TimePicker adds date_format rule based on format', function (): void {
    $rules = ValidationRulesExporter::export([
        TimePicker::make('open')->format('H:i:s'),
    ]);

    expect($rules['open'])->toContain('date_format:H:i:s');
});

it('FileUpload single adds file/max/mimes', function (): void {
    $rules = ValidationRulesExporter::export([
        FileUpload::make('avatar')->image()->maxSize(2048)->accept('image/png,image/jpeg'),
    ]);

    expect($rules['avatar'])->toContain('image');
    expect($rules['avatar'])->toContain('max:2048');
    expect($rules['avatar'])->toContain('mimes:png,jpeg');
});

it('FileUpload multiple adds array + max:maxFiles', function (): void {
    $rules = ValidationRulesExporter::export([
        FileUpload::make('docs')->multiple()->maxFiles(5),
    ]);

    expect($rules['docs'])->toContain('array');
    expect($rules['docs'])->toContain('max:5');
});

it('Select multiple adds array rule', function (): void {
    $rules = ValidationRulesExporter::export([
        Select::make('tags')->options(['a', 'b'])->multiple(),
    ]);

    expect($rules['tags'])->toContain('array');
});

it('Select single (no multiple) does not add array', function (): void {
    $rules = ValidationRulesExporter::export([
        Select::make('country')->options(['ru', 'en']),
    ]);

    // Без явных rules() и без required — поле может вообще не попасть в payload.
    expect($rules)->not->toHaveKey('country');
});

it('ColorPicker adds hex regex by default', function (): void {
    $rules = ValidationRulesExporter::export([
        ColorPicker::make('bg')->required(),
    ]);

    expect($rules['bg'])->toContain('required');
    $hexRule = collect($rules['bg'])->first(fn ($r) => str_starts_with($r, 'regex:'));
    expect($hexRule)->not->toBeNull();
});

it('Password::confirmed adds confirmed via getRules()', function (): void {
    $rules = ValidationRulesExporter::export([
        Password::make('password')->required()->confirmed(),
    ]);

    expect($rules['password'])->toContain('required');
    expect($rules['password'])->toContain('confirmed');
});

it('respects appliesTo($context) — onCreate-only field skipped on update', function (): void {
    $createRules = ValidationRulesExporter::export([
        Password::make('password')->onCreate()->onUpdate(false)->required(),
    ], 'create');
    $updateRules = ValidationRulesExporter::export([
        Password::make('password')->onCreate()->onUpdate(false)->required(),
    ], 'update');

    expect($createRules)->toHaveKey('password');
    expect($updateRules)->not->toHaveKey('password');
});

it('does not duplicate min/max from explicit and implicit', function (): void {
    $rules = ValidationRulesExporter::export([
        Number::make('age')->min(0)->max(120)->rules(['min:5', 'max:150']),
    ]);

    // Explicit имеет приоритет — implicit min/max не должны добавиться.
    expect($rules['age'])->toContain('min:5');
    expect($rules['age'])->toContain('max:150');
    expect($rules['age'])->not->toContain('min:0');
    expect($rules['age'])->not->toContain('max:120');
});

it('skips fields with no rules and no implicit type rules', function (): void {
    $rules = ValidationRulesExporter::export([
        Input::make('comment'), // Input без type, без required, без rules
    ]);

    expect($rules)->toBeEmpty();
});
