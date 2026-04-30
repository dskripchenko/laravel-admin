<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\ColorPicker;
use Dskripchenko\LaravelAdmin\Field\DatePicker;
use Dskripchenko\LaravelAdmin\Field\DateRange;
use Dskripchenko\LaravelAdmin\Field\Rating;
use Dskripchenko\LaravelAdmin\Field\Slider;
use Dskripchenko\LaravelAdmin\Field\TimePicker;

it('DatePicker has type=date and supports format/displayFormat', function (): void {
    $f = DatePicker::make('birth')
        ->format('Y-m-d')
        ->displayFormat('DD.MM.YYYY');
    expect($f->fieldType())->toBe('date');
    expect($f->getAttribute('format'))->toBe('Y-m-d');
    expect($f->getAttribute('displayFormat'))->toBe('DD.MM.YYYY');
});

it('DatePicker::min/max accept string and DateTimeInterface', function (): void {
    $f = DatePicker::make('d')
        ->min('2020-01-01')
        ->max(new DateTimeImmutable('2030-12-31'));

    expect($f->getAttribute('min'))->toBe('2020-01-01');
    expect($f->getAttribute('max'))->toContain('2030-12-31');
});

it('DatePicker::withTime sets default format to Y-m-d H:i:s', function (): void {
    $f = DatePicker::make('d')->withTime();
    expect($f->getAttribute('withTime'))->toBeTrue();
    expect($f->getAttribute('format'))->toBe('Y-m-d H:i:s');
});

it('DateRange has type=date_range and presets', function (): void {
    $f = DateRange::make('period')->presets(['today', 'last_7_days']);
    expect($f->fieldType())->toBe('date_range');
    expect($f->getAttribute('presets'))->toBe(['today', 'last_7_days']);
});

it('TimePicker has type=time and step/withSeconds', function (): void {
    $f = TimePicker::make('open')->step(15)->withSeconds(false);
    expect($f->fieldType())->toBe('time');
    expect($f->getAttribute('step'))->toBe(15);
    expect($f->getAttribute('withSeconds'))->toBeFalse();
});

it('TimePicker::withSeconds sets H:i:s format by default', function (): void {
    $f = TimePicker::make('t')->withSeconds();
    expect($f->getAttribute('format'))->toBe('H:i:s');
});

it('ColorPicker has type=color and validates format', function (): void {
    $f = ColorPicker::make('bg')->format('rgb')->withAlpha();
    expect($f->fieldType())->toBe('color');
    expect($f->getAttribute('format'))->toBe('rgb');
    expect($f->getAttribute('withAlpha'))->toBeTrue();
});

it('ColorPicker rejects invalid format', function (): void {
    expect(fn () => ColorPicker::make('c')->format('cmyk'))
        ->toThrow(InvalidArgumentException::class);
});

it('ColorPicker::palette stores list of colors', function (): void {
    $f = ColorPicker::make('c')->palette(['#ff0000', '#00ff00', '#0000ff']);
    expect($f->getAttribute('palette'))->toBe(['#ff0000', '#00ff00', '#0000ff']);
});

it('Slider has type=slider with min/max/step/marks', function (): void {
    $f = Slider::make('volume')
        ->min(0)
        ->max(100)
        ->step(5)
        ->marks([0 => '0%', 50 => '50%', 100 => '100%']);
    expect($f->fieldType())->toBe('slider');
    expect($f->getAttribute('min'))->toBe(0);
    expect($f->getAttribute('max'))->toBe(100);
    expect($f->getAttribute('step'))->toBe(5);
    expect($f->getAttribute('marks'))->toBe([0 => '0%', 50 => '50%', 100 => '100%']);
});

it('Rating has type=rating and count/half/icon', function (): void {
    $f = Rating::make('score')->count(5)->half()->icon('star');
    expect($f->fieldType())->toBe('rating');
    expect($f->getAttribute('count'))->toBe(5);
    expect($f->getAttribute('half'))->toBeTrue();
    expect($f->getAttribute('icon'))->toBe('star');
});
