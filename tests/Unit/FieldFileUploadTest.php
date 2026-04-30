<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\FileUpload;

it('FileUpload has type=file and supports multiple/accept/maxSize/maxFiles', function (): void {
    $f = FileUpload::make('docs')
        ->multiple()
        ->accept(['application/pdf', '.docx'])
        ->maxSize(2048)
        ->maxFiles(5);

    expect($f->fieldType())->toBe('file');
    expect($f->getAttribute('multiple'))->toBeTrue();
    expect($f->getAttribute('accept'))->toBe('application/pdf,.docx');
    expect($f->getAttribute('maxSize'))->toBe(2048);
    expect($f->getAttribute('maxFiles'))->toBe(5);
});

it('FileUpload::accept accepts a string and stores it as is', function (): void {
    $f = FileUpload::make('f')->accept('image/*');
    expect($f->getAttribute('accept'))->toBe('image/*');
});

it('FileUpload::image() implicitly sets accept=image/*', function (): void {
    $f = FileUpload::make('avatar')->image();
    expect($f->getAttribute('image'))->toBeTrue();
    expect($f->getAttribute('accept'))->toBe('image/*');
});

it('FileUpload::image() does not overwrite explicit accept', function (): void {
    $f = FileUpload::make('avatar')->accept('image/png')->image();
    expect($f->getAttribute('accept'))->toBe('image/png');
});

it('FileUpload::disk stores disk attribute', function (): void {
    $f = FileUpload::make('f')->disk('s3');
    expect($f->getAttribute('disk'))->toBe('s3');
});
