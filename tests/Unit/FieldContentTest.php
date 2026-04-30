<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\KeyValue;
use Dskripchenko\LaravelAdmin\Field\Markdown;
use Dskripchenko\LaravelAdmin\Field\Slug;
use Dskripchenko\LaravelAdmin\Field\TagsInput;
use Dskripchenko\LaravelAdmin\Field\TranslatableInput;

it('Markdown has type=markdown and toolbar/preview/uploadImages', function (): void {
    $f = Markdown::make('body')
        ->preview()
        ->toolbar()
        ->height(400)
        ->uploadImages();

    expect($f->fieldType())->toBe('markdown');
    expect($f->getAttribute('preview'))->toBeTrue();
    expect($f->getAttribute('toolbar'))->toBeTrue();
    expect($f->getAttribute('height'))->toBe(400);
    expect($f->getAttribute('uploadImages'))->toBeTrue();
});

it('Slug has type=slug and from()/separator()/reactive()', function (): void {
    $f = Slug::make('slug')->from('title')->separator('_')->reactive(false);
    expect($f->fieldType())->toBe('slug');
    expect($f->getAttribute('from'))->toBe('title');
    expect($f->getAttribute('separator'))->toBe('_');
    expect($f->getAttribute('reactive'))->toBeFalse();
});

it('Slug::generate() creates URL-safe slug', function (): void {
    expect(Slug::generate('Hello World!'))->toBe('hello-world');
    expect(Slug::generate('Привет мир'))->toBe('privet-mir');
    expect(Slug::generate('two_words', '_'))->toBe('two_words');
});

it('KeyValue has type=key_value and labels/addable/removable', function (): void {
    $f = KeyValue::make('meta')
        ->keyLabel('Свойство')
        ->valueLabel('Значение')
        ->addable()
        ->removable()
        ->allowedKeys(['title', 'description']);

    expect($f->fieldType())->toBe('key_value');
    expect($f->getAttribute('keyLabel'))->toBe('Свойство');
    expect($f->getAttribute('valueLabel'))->toBe('Значение');
    expect($f->getAttribute('addable'))->toBeTrue();
    expect($f->getAttribute('removable'))->toBeTrue();
    expect($f->getAttribute('allowedKeys'))->toBe(['title', 'description']);
});

it('TagsInput has type=tags and suggestions/maxItems/separator', function (): void {
    $f = TagsInput::make('tags')
        ->suggestions(['php', 'js', 'laravel'])
        ->maxItems(10)
        ->separator(',');

    expect($f->fieldType())->toBe('tags');
    expect($f->getAttribute('suggestions'))->toHaveCount(3);
    expect($f->getAttribute('maxItems'))->toBe(10);
    expect($f->getAttribute('separator'))->toBe(',');
});

it('TranslatableInput has type=translatable and as/locales', function (): void {
    $f = TranslatableInput::make('title')
        ->as('input')
        ->locales(['ru', 'en', 'de'])
        ->requireAllLocales();

    expect($f->fieldType())->toBe('translatable');
    expect($f->getAttribute('as'))->toBe('input');
    expect($f->getAttribute('locales'))->toBe(['ru', 'en', 'de']);
    expect($f->getAttribute('requireAllLocales'))->toBeTrue();
});

it('TranslatableInput::getLocales falls back to admin.ui.available_locales', function (): void {
    config()->set('admin.ui.available_locales', ['ru', 'fr', 'pl']);
    $f = TranslatableInput::make('t');
    expect($f->getLocales())->toBe(['ru', 'fr', 'pl']);
});

it('TranslatableInput::getLocales prefers explicit locales()', function (): void {
    config()->set('admin.ui.available_locales', ['ru', 'fr']);
    $f = TranslatableInput::make('t')->locales(['de', 'es']);
    expect($f->getLocales())->toBe(['de', 'es']);
});

it('TranslatableInput::getLocales falls back to [ru, en] without config', function (): void {
    config()->set('admin.ui.available_locales', null);
    $f = TranslatableInput::make('t');
    expect($f->getLocales())->toBe(['ru', 'en']);
});
