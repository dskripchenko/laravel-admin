<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Wysiwyg;

it('Wysiwyg has type=wysiwyg and default extensions', function (): void {
    $f = Wysiwyg::make('body');
    expect($f->fieldType())->toBe('wysiwyg');
    expect($f->getExtensions())->toContain('paragraph', 'heading', 'bold', 'italic');
});

it('Wysiwyg::defaultExtensions returns presets correctly', function (): void {
    expect(Wysiwyg::defaultExtensions('minimal'))->toBe([
        'paragraph', 'bold', 'italic', 'link',
    ]);
    expect(Wysiwyg::defaultExtensions('full'))->toContain('table', 'youtube', 'mention');
    expect(Wysiwyg::defaultExtensions('default'))->toContain('paragraph', 'image');
});

it('Wysiwyg::preset switches extensions list', function (): void {
    $minimal = Wysiwyg::make('b')->preset('minimal');
    expect($minimal->getExtensions())->toBe(['paragraph', 'bold', 'italic', 'link']);

    $full = Wysiwyg::make('b')->preset('full');
    expect($full->getExtensions())->toContain('table', 'youtube');
});

it('Wysiwyg::preset rejects invalid name', function (): void {
    expect(fn () => Wysiwyg::make('b')->preset('crazy'))
        ->toThrow(InvalidArgumentException::class);
});

it('Wysiwyg::withExtension adds without duplicates', function (): void {
    $f = Wysiwyg::make('b')->preset('minimal')->withExtension('strike')->withExtension('strike');
    $exts = $f->getExtensions();
    expect(array_count_values($exts)['strike'] ?? 0)->toBe(1);
    expect($exts)->toContain('strike');
});

it('Wysiwyg::withoutExtension removes extension', function (): void {
    $f = Wysiwyg::make('b')->preset('default')->withoutExtension('image');
    expect($f->getExtensions())->not->toContain('image');
    expect($f->getExtensions())->toContain('paragraph');
});

it('Wysiwyg::extensions overrides any preset', function (): void {
    $f = Wysiwyg::make('b')->preset('full')->extensions(['paragraph', 'bold']);
    expect($f->getExtensions())->toBe(['paragraph', 'bold']);
});

it('Wysiwyg::height/placeholder/toolbar serialized in attributes', function (): void {
    $f = Wysiwyg::make('b')
        ->height(400)
        ->placeholder('Type something...')
        ->toolbar('sticky');
    $arr = $f->toArray();

    expect($arr['attributes']['height'])->toBe(400);
    expect($arr['attributes']['placeholder'])->toBe('Type something...');
    expect($arr['attributes']['toolbar'])->toBe('sticky');
});

it('Wysiwyg::toolbar accepts groups array', function (): void {
    $groups = [['bold', 'italic'], ['heading', 'link'], ['image']];
    $f = Wysiwyg::make('b')->toolbar($groups);
    expect($f->getAttribute('toolbar'))->toBe($groups);
});

it('Wysiwyg::uploadImages enables endpoint config', function (): void {
    $f = Wysiwyg::make('b')->uploadImages();
    expect($f->getAttribute('uploadImages'))->toBeTrue();
    expect($f->getAttribute('uploadEndpoint'))->toBe('/api/admin/uploads/image');

    $f2 = Wysiwyg::make('b')->uploadImages(true, '/custom/upload');
    expect($f2->getAttribute('uploadEndpoint'))->toBe('/custom/upload');
});

it('Wysiwyg toArray returns extensions in attributes', function (): void {
    $f = Wysiwyg::make('body')->preset('minimal');
    $arr = $f->toArray();
    expect($arr['attributes']['extensions'])->toBe(['paragraph', 'bold', 'italic', 'link']);
});
