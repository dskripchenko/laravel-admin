<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Checkbox;
use Dskripchenko\LaravelAdmin\Field\Combobox;
use Dskripchenko\LaravelAdmin\Field\Radio;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Field\Switcher;

enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}

it('Select::options accepts assoc array and normalizes to {value,label}', function (): void {
    $f = Select::make('country')->options(['ru' => 'Россия', 'en' => 'England']);
    expect($f->getAttribute('options'))->toBe([
        ['value' => 'ru', 'label' => 'Россия'],
        ['value' => 'en', 'label' => 'England'],
    ]);
});

it('Select::options accepts already-normalized list and passes through', function (): void {
    $f = Select::make('s')->options([
        ['value' => 1, 'label' => 'One', 'disabled' => true],
        ['value' => 2, 'label' => 'Two'],
    ]);
    expect($f->getAttribute('options'))->toBe([
        ['value' => 1, 'label' => 'One', 'disabled' => true],
        ['value' => 2, 'label' => 'Two'],
    ]);
});

it('Select::fromEnum reads BackedEnum cases', function (): void {
    $f = Select::make('p')->fromEnum(Priority::class);
    $choices = $f->getAttribute('options');
    expect($choices)->toHaveCount(3);
    expect($choices[0])->toBe(['value' => 'low', 'label' => 'Low']);
    expect($choices[2])->toBe(['value' => 'high', 'label' => 'High']);
});

it('Select::multiple sets multiple attribute', function (): void {
    expect(Select::make('s')->multiple()->getAttribute('multiple'))->toBeTrue();
});

it('Select::searchable + clearable', function (): void {
    $f = Select::make('s')->searchable()->clearable();
    expect($f->getAttribute('searchable'))->toBeTrue();
    expect($f->getAttribute('clearable'))->toBeTrue();
});

it('Combobox::creatable enables free-text input', function (): void {
    $f = Combobox::make('c')->options(['a', 'b'])->creatable();
    expect($f->fieldType())->toBe('combobox');
    expect($f->getAttribute('creatable'))->toBeTrue();
});

it('Radio::inline + options', function (): void {
    $f = Radio::make('r')->options(['a' => 'A', 'b' => 'B'])->inline();
    expect($f->fieldType())->toBe('radio');
    expect($f->getAttribute('inline'))->toBeTrue();
    expect($f->getAttribute('options'))->toHaveCount(2);
});

it('Checkbox can work as boolean (no options) or as group (options)', function (): void {
    $bool = Checkbox::make('agreed');
    expect($bool->fieldType())->toBe('checkbox');
    expect($bool->getAttribute('options'))->toBeNull();

    $group = Checkbox::make('tags')->options(['php', 'js'])->inline();
    expect($group->getAttribute('options'))->toHaveCount(2);
    expect($group->getAttribute('inline'))->toBeTrue();
});

it('Switcher has type=switch and size/labels setters', function (): void {
    $f = Switcher::make('active')->size('lg')->labels('Да', 'Нет');
    expect($f->fieldType())->toBe('switch');
    expect($f->getAttribute('size'))->toBe('lg');
    expect($f->getAttribute('onLabel'))->toBe('Да');
    expect($f->getAttribute('offLabel'))->toBe('Нет');
});

it('fromEnum throws on non-enum class', function (): void {
    expect(fn () => Select::make('s')->fromEnum(stdClass::class))
        ->toThrow(InvalidArgumentException::class);
});

it('Combobox: подсказки есть, а чужое значение разрешено', function (): void {
    // The set of values is not ours: a provider's model identifier has to be
    // spelt precisely, remembering it is unreasonable, and a closed select
    // would go stale the day the provider ships a new model.
    $f = Combobox::make('model')
        ->options(['claude-opus-5' => 'Claude Opus 5', 'llama3.1:8b' => 'Llama 3.1 8B']);

    expect($f->fieldType())->toBe('combobox');
    expect($f->getAttribute('options'))->toBe([
        ['value' => 'claude-opus-5', 'label' => 'Claude Opus 5'],
        ['value' => 'llama3.1:8b', 'label' => 'Llama 3.1 8B'],
    ]);
    // The field's own knob keeps its name: hosts have been calling
    // `creatable()` since before the SPA learned to draw the field.
    expect($f->creatable()->getAttribute('creatable'))->toBeTrue();
});

it('Combobox::creatable(false) закрывает список', function (): void {
    $f = Combobox::make('model')->creatable(false);

    expect($f->getAttribute('creatable'))->toBeFalse();
});
