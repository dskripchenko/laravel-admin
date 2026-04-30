<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Code;
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Field\Password;
use Dskripchenko\LaravelAdmin\Field\Textarea;

it('Number has type=number and supports min/max/step via __call', function (): void {
    $f = Number::make('age')->min(0)->max(120)->step(1);
    expect($f->fieldType())->toBe('number');
    expect($f->getAttribute('min'))->toBe(0);
    expect($f->getAttribute('max'))->toBe(120);
    expect($f->getAttribute('step'))->toBe(1);
});

it('Number::integer() sets integer attribute', function (): void {
    $f = Number::make('count')->integer();
    expect($f->getAttribute('integer'))->toBeTrue();
});

it('Password has type=password', function (): void {
    expect(Password::make('p')->fieldType())->toBe('password');
});

it('Password::revealable() toggles attribute', function (): void {
    $f = Password::make('p')->revealable();
    expect($f->getAttribute('revealable'))->toBeTrue();
});

it('Password::confirmed() adds confirmed rule + attribute', function (): void {
    $f = Password::make('p')->confirmed();
    expect($f->getRules())->toContain('confirmed');
    expect($f->getAttribute('confirmed'))->toBeTrue();
});

it('Password::confirmed() does not duplicate rule on repeated calls', function (): void {
    $f = Password::make('p')->confirmed()->confirmed();
    expect(array_count_values(array_map('strval', $f->getRules()))['confirmed'])->toBe(1);
});

it('Textarea has type=textarea and rows/autosize', function (): void {
    $f = Textarea::make('bio')->rows(5)->autosize();
    expect($f->fieldType())->toBe('textarea');
    expect($f->getAttribute('rows'))->toBe(5);
    expect($f->getAttribute('autosize'))->toBeTrue();
});

it('Code has type=code and language/theme/lineNumbers', function (): void {
    $f = Code::make('snippet')->language('php')->theme('vs-dark')->lineNumbers();
    expect($f->fieldType())->toBe('code');
    expect($f->getAttribute('language'))->toBe('php');
    expect($f->getAttribute('theme'))->toBe('vs-dark');
    expect($f->getAttribute('lineNumbers'))->toBeTrue();
});

it('all P4.1 fields serialize through toArray with correct type', function (): void {
    foreach ([
        Number::make('a')->fieldType() => 'number',
        Password::make('a')->fieldType() => 'password',
        Textarea::make('a')->fieldType() => 'textarea',
        Code::make('a')->fieldType() => 'code',
    ] as $actual => $expected) {
        expect($actual)->toBe($expected);
    }
});
