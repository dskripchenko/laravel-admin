<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Builder;
use Dskripchenko\LaravelAdmin\Field\Cascader;
use Dskripchenko\LaravelAdmin\Field\FileUpload;
use Dskripchenko\LaravelAdmin\Field\ImageCropper;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Markdown;
use Dskripchenko\LaravelAdmin\Field\TreeSelect;

it('TreeSelect has type=tree_select and tree config', function (): void {
    $tree = [
        ['value' => 1, 'label' => 'Root', 'children' => [
            ['value' => 2, 'label' => 'Child A'],
            ['value' => 3, 'label' => 'Child B'],
        ]],
    ];

    $f = TreeSelect::make('category')
        ->tree($tree)
        ->multiple()
        ->checkable()
        ->selectableParents(false);

    expect($f->fieldType())->toBe('tree_select');
    expect($f->getAttribute('tree'))->toBe($tree);
    expect($f->getAttribute('multiple'))->toBeTrue();
    expect($f->getAttribute('checkable'))->toBeTrue();
    expect($f->getAttribute('selectableParents'))->toBeFalse();
});

it('TreeSelect::fromModel stores model + columns', function (): void {
    $f = TreeSelect::make('cat')->fromModel(stdClass::class, 'parent', 'id', 'title');
    expect($f->getAttribute('relatedModel'))->toBe(stdClass::class);
    expect($f->getAttribute('parentColumn'))->toBe('parent');
    expect($f->getAttribute('valueColumn'))->toBe('id');
    expect($f->getAttribute('labelColumn'))->toBe('title');
});

it('Cascader has type=cascader and levels/separator/searchable', function (): void {
    $f = Cascader::make('location')
        ->levels([
            ['key' => 'country', 'label' => 'Страна'],
            ['key' => 'city', 'label' => 'Город'],
        ])
        ->separator(' / ')
        ->searchable();

    expect($f->fieldType())->toBe('cascader');
    expect($f->getAttribute('levels'))->toHaveCount(2);
    expect($f->getAttribute('separator'))->toBe(' / ');
    expect($f->getAttribute('searchable'))->toBeTrue();
});

it('Builder declares blocks and exposes allowedTypes', function (): void {
    $f = Builder::make('content')
        ->block('hero', [
            Input::make('title')->required(),
            Markdown::make('subtitle'),
        ], 'Hero', 'image')
        ->block('gallery', [
            FileUpload::make('images')->multiple(),
        ])
        ->maxBlocks(20)
        ->reorderable();

    expect($f->fieldType())->toBe('builder');
    expect($f->allowedTypes())->toBe(['hero', 'gallery']);
    $blocks = $f->getAttribute('blocks');
    expect($blocks['hero']['label'])->toBe('Hero');
    expect($blocks['hero']['icon'])->toBe('image');
    expect($blocks['hero']['fields'])->toHaveCount(2);
    expect($f->getAttribute('maxBlocks'))->toBe(20);
    expect($f->getAttribute('reorderable'))->toBeTrue();
});

it('Builder::fieldsForBlock returns the original Field objects', function (): void {
    $f = Builder::make('c')->block('text', [Input::make('body')]);
    $fields = $f->fieldsForBlock('text');
    expect($fields)->toHaveCount(1);
    expect($fields[0])->toBeInstanceOf(Input::class);
    expect($f->fieldsForBlock('unknown'))->toBeNull();
});

it('ImageCropper extends FileUpload and overrides type', function (): void {
    $f = ImageCropper::make('avatar')
        ->aspectRatio(1.0)
        ->minCrop(200, 200)
        ->outputSize(400, 400)
        ->quality(0.9)
        ->maxSize(2048);

    expect($f->fieldType())->toBe('image_cropper');
    expect($f)->toBeInstanceOf(FileUpload::class);
    expect($f->getAttribute('aspectRatio'))->toBe(1.0);
    expect($f->getAttribute('minCropWidth'))->toBe(200);
    expect($f->getAttribute('outputWidth'))->toBe(400);
    expect($f->getAttribute('quality'))->toBe(0.9);
    expect($f->getAttribute('maxSize'))->toBe(2048);
});

it('ImageCropper inherits image() helper from FileUpload', function (): void {
    $f = ImageCropper::make('a')->image();
    expect($f->getAttribute('accept'))->toBe('image/*');
});

it('ImageCropper toArray() exposes attributes to manifest', function (): void {
    $f = ImageCropper::make('avatar')
        ->aspectRatio(2.0)
        ->outputSize(600, 300)
        ->quality(0.85)
        ->required();

    $arr = $f->toArray();
    expect($arr['type'])->toBe('image_cropper');
    expect($arr['name'])->toBe('avatar');
    expect($arr['required'])->toBeTrue();
    expect($arr['attributes']['aspectRatio'])->toBe(2.0);
    expect($arr['attributes']['outputWidth'])->toBe(600);
    expect($arr['attributes']['outputHeight'])->toBe(300);
    expect($arr['attributes']['quality'])->toBe(0.85);
});
