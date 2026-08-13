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

it('FileUpload single валидируется как upload-first {disk, path}', function (): void {
    // The SPA does not send multipart on create/update: the file is already
    // uploaded through /uploads/upload and the form carries {disk, path}. The
    // file/mimes rules here rejected exactly the shape of the value the panel
    // sends — creating a row with a file field did not pass validation at all.
    // The size and the type are checked inside /uploads/upload itself.
    $rules = ValidationRulesExporter::export([
        FileUpload::make('avatar')->image()->maxSize(2048)->accept('image/png,image/jpeg'),
    ]);

    expect($rules['avatar'])->toContain('array');
    expect($rules['avatar'])->not->toContain('image');
    expect($rules['avatar'])->not->toContain('mimes:png,jpeg');
    expect($rules['avatar.disk'])->toBe(['required_with:avatar', 'string']);
    expect($rules['avatar.path'])->toBe(['required_with:avatar', 'string']);
});

it('FileUpload multiple adds array + max:maxFiles', function (): void {
    $rules = ValidationRulesExporter::export([
        FileUpload::make('docs')->multiple()->maxFiles(5),
    ]);

    expect($rules['docs'])->toContain('array');
    expect($rules['docs'])->toContain('max:5');
    expect($rules['docs.*.disk'])->toBe(['required_with:docs.*', 'string']);
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

    // A single select with no explicit rules gets the default nullable (so that
    // validate() does not cut the field out) but NOT array — array is only for
    // multiple.
    expect($rules['country'])->toBe(['nullable']);
    expect($rules['country'])->not->toContain('array');
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

    // An explicit rule wins — the implicit min/max must not be added.
    expect($rules['age'])->toContain('min:5');
    expect($rules['age'])->toContain('max:150');
    expect($rules['age'])->not->toContain('min:0');
    expect($rules['age'])->not->toContain('max:120');
});

it('fields with no rules get a nullable default so validate() keeps them', function (): void {
    $rules = ValidationRulesExporter::export([
        Input::make('comment'), // Input без type, без required, без rules
    ]);

    // The default nullable — otherwise Laravel's validate() cuts the field out
    // of $data even when the backend wants the value as is.
    expect($rules)->toHaveKey('comment');
    expect($rules['comment'])->toBe(['nullable']);
});
