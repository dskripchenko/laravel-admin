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

it('Wysiwyg has no driver by default and serializes none', function (): void {
    $f = Wysiwyg::make('body');
    expect($f->getDriver())->toBe('default');
    expect($f->toArray()['attributes'])->not->toHaveKey('driver')->not->toHaveKey('driverConfig');
});

it('Wysiwyg::driver(quill) serializes the engine and its options', function (): void {
    $f = Wysiwyg::make('body')->driver('quill', [
        'toolbar' => ['text', 'header', 'list'],
        'modules' => ['clipboard' => ['matchVisual' => false]],
        'output' => 'html',
    ]);
    $attributes = $f->toArray()['attributes'];

    expect($attributes['driver'])->toBe('quill');
    expect($attributes['driverConfig'])->toBe([
        'toolbar' => ['text', 'header', 'list'],
        'modules' => ['clipboard' => ['matchVisual' => false]],
        'output' => 'html',
    ]);
    // The manifest is JSON: the options survive the round trip.
    expect(json_decode((string) json_encode($f->toArray()), true)['attributes']['driverConfig']['toolbar'])
        ->toBe(['text', 'header', 'list']);
});

it('Wysiwyg output()/modules() switch to Quill and merge their options', function (): void {
    $f = Wysiwyg::make('body')->modules(['toolbar' => [['bold']]])->output('delta');
    expect($f->getDriver())->toBe('quill');
    expect($f->getAttribute('driverConfig'))->toBe(['modules' => ['toolbar' => [['bold']]], 'output' => 'delta']);
});

it('Wysiwyg::driver(tinymce) serializes toolbar, plugins and height', function (): void {
    $f = Wysiwyg::make('body')
        ->driver('tinymce', ['toolbar' => 'bold italic | link', 'height' => 400, 'script' => '/js/tinymce/tinymce.min.js'])
        ->plugins(['lists', 'link']);
    $attributes = $f->toArray()['attributes'];

    expect($attributes['driver'])->toBe('tinymce');
    expect($attributes['driverConfig'])->toBe([
        'toolbar' => 'bold italic | link',
        'height' => 400,
        'script' => '/js/tinymce/tinymce.min.js',
        'plugins' => 'lists link',
    ]);
});

it('Wysiwyg tinymce takes the script from config unless the field names one', function (): void {
    config()->set('admin.wysiwyg.tinymce.script', '/js/platform/tinymce/tinymce/tinymce.min.js');

    expect(Wysiwyg::make('a')->driver('tinymce')->toArray()['attributes']['driverConfig'])
        ->toBe(['script' => '/js/platform/tinymce/tinymce/tinymce.min.js']);
    expect(Wysiwyg::make('b')->driver('tinymce', ['script' => '/own.js'])->toArray()['attributes']['driverConfig']['script'])
        ->toBe('/own.js');
    // Not for other engines.
    expect(Wysiwyg::make('c')->driver('quill')->toArray()['attributes']['driverConfig'])->toBe([]);
    // The field's ->height() reaches TinyMCE unless its options name one.
    expect(Wysiwyg::make('d')->height(500)->driver('tinymce')->toArray()['attributes']['driverConfig']['height'])->toBe(500);
    expect(Wysiwyg::make('e')->height(500)->driver('tinymce', ['height' => 300])->toArray()['attributes']['driverConfig']['height'])->toBe(300);
});

it('Wysiwyg::driver(default) drops a previous engine; switching engines drops its options', function (): void {
    $f = Wysiwyg::make('body')->driver('quill', ['output' => 'html'])->driver('default');
    expect($f->getDriver())->toBe('default');
    expect($f->toArray()['attributes'])->not->toHaveKey('driverConfig');

    $g = Wysiwyg::make('body')->driver('quill', ['output' => 'html'])->driver('tinymce', ['height' => 300]);
    expect($g->getAttribute('driverConfig'))->toBe(['height' => 300]);
});

it('Wysiwyg::driver rejects unknown engines and Quill outputs', function (): void {
    expect(fn () => Wysiwyg::make('b')->driver('ckeditor'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Wysiwyg::make('b')->driver('quill', ['output' => 'markdown']))->toThrow(InvalidArgumentException::class);
    expect(fn () => Wysiwyg::make('b')->output('text'))->toThrow(InvalidArgumentException::class);
});

it('Wysiwyg with Quill keeps Quill 2 markup through the sanitizer byte for byte', function (): void {
    $field = Wysiwyg::make('body')->driver('quill');
    $sanitizer = new Dskripchenko\LaravelAdmin\Uploads\HtmlSanitizer($field->sanitizerWhitelist(), $field->sanitizerConstrains());
    $html = '<ol><li data-list="bullet"><span class="ql-ui" contenteditable="false"></span>one</li>'
        .'<li data-list="ordered" class="ql-indent-1"><span class="ql-ui" contenteditable="false"></span>two</li></ol>'
        .'<p class="ql-align-center">centred <span style="color: rgb(230, 0, 0);">red</span></p>'
        .'<h2 class="ql-align-right">T</h2><p>a&nbsp;b <a href="https://example.com" rel="noopener noreferrer" target="_blank">l</a></p>'
        .'<div class="ql-code-block-container" spellcheck="false"><div class="ql-code-block" data-language="plain">code</div></div>'
        .'<p><br></p>';

    expect($sanitizer->sanitize($html))->toBe($html);
    // Still a sanitizer: scripts and handlers go.
    expect($sanitizer->sanitize('<p onclick="x()">a</p><script>alert(1)</script>'))->toBe('<p>a</p>');
    // The default whitelist would have turned the bullet list into a numbered one.
    expect((new Dskripchenko\LaravelAdmin\Uploads\HtmlSanitizer)->sanitize($html))->not->toContain('data-list="bullet"');
});

it('Wysiwyg with TinyMCE keeps its inline styles through the sanitizer', function (): void {
    $field = Wysiwyg::make('body')->driver('tinymce');
    $sanitizer = new Dskripchenko\LaravelAdmin\Uploads\HtmlSanitizer($field->sanitizerWhitelist(), $field->sanitizerConstrains());
    $html = '<p style="text-align: center;">c</p><ul style="list-style-type: square;"><li>x</li></ul>'
        .'<table style="border-collapse: collapse; width: 100%;" border="1"><tbody><tr><td style="width: 50%;">a</td></tr></tbody></table>';

    expect($sanitizer->sanitize($html))->toBe($html);
});

it('Wysiwyg with Quill delta output is not sanitized as HTML', function (): void {
    expect(Wysiwyg::make('b')->driver('quill', ['output' => 'delta'])->shouldSanitize())->toBeFalse();
    expect(Wysiwyg::make('b')->driver('quill')->shouldSanitize())->toBeTrue();
    expect(Wysiwyg::make('b')->driver('quill')->sanitize(false)->shouldSanitize())->toBeFalse();
});
