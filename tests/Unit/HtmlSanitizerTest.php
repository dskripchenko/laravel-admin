<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Uploads\HtmlSanitizer;

it('removes script tags entirely', function (): void {
    $s = new HtmlSanitizer;
    $clean = $s->sanitize('<p>Hello</p><script>alert(1)</script><p>World</p>');
    expect($clean)->not->toContain('script');
    expect($clean)->not->toContain('alert(1)');
    expect($clean)->toContain('Hello');
    expect($clean)->toContain('World');
});

it('strips on*-event handlers', function (): void {
    $s = new HtmlSanitizer;
    $clean = $s->sanitize('<a href="https://example.com" onclick="evil()">Click</a>');
    expect($clean)->not->toContain('onclick');
    expect($clean)->toContain('href="https://example.com"');
});

it('removes javascript: URLs from href', function (): void {
    $s = new HtmlSanitizer;
    $clean = $s->sanitize('<a href="javascript:alert(1)">click</a>');
    expect($clean)->not->toContain('javascript:');
});

it('removes vbscript: and data:text/html URLs', function (): void {
    $s = new HtmlSanitizer;
    $a = $s->sanitize('<a href="vbscript:msgbox(1)">x</a>');
    expect($a)->not->toContain('vbscript');

    $b = $s->sanitize('<a href="data:text/html,<script>alert(1)</script>">y</a>');
    expect($b)->not->toContain('data:text/html');
});

it('keeps allowed tags + attributes', function (): void {
    $s = new HtmlSanitizer;
    $clean = $s->sanitize(
        '<h1 id="t">Title</h1><p>Hello <strong>world</strong></p>'
        .'<ul><li>One</li><li>Two</li></ul>'
        .'<img src="https://cdn.example.com/x.png" alt="x" width="100">'
    );

    expect($clean)->toContain('<h1 id="t">Title</h1>');
    expect($clean)->toContain('<strong>world</strong>');
    expect($clean)->toContain('<li>One</li>');
    expect($clean)->toContain('src="https://cdn.example.com/x.png"');
    expect($clean)->toContain('alt="x"');
});

it('strips disallowed tags but keeps text content', function (): void {
    $s = new HtmlSanitizer;
    $clean = $s->sanitize('<p>Hello <iframe src="https://evil.com"></iframe>World</p>');
    expect($clean)->not->toContain('iframe');
    expect($clean)->toContain('Hello');
    expect($clean)->toContain('World');
});

it('removes HTML comments', function (): void {
    $s = new HtmlSanitizer;
    $clean = $s->sanitize('<p>Visible</p><!-- secret --><p>Also visible</p>');
    expect($clean)->not->toContain('secret');
    expect($clean)->not->toContain('<!--');
});

it('returns empty string on empty/whitespace input', function (): void {
    $s = new HtmlSanitizer;
    expect($s->sanitize(''))->toBe('');
    expect($s->sanitize('   '))->toBe('');
});

it('custom whitelist limits allowed tags', function (): void {
    $s = new HtmlSanitizer(['p' => [], 'em' => []]);
    $clean = $s->sanitize('<p>ok <strong>strong removed</strong> <em>em kept</em></p>');
    expect($clean)->toContain('strong removed'); // text сохраняется
    expect($clean)->not->toContain('<strong>');
    expect($clean)->toContain('<em>em kept</em>');
});

it('custom whitelist filters attributes', function (): void {
    $s = new HtmlSanitizer(['a' => ['href']]); // только href, не target/rel
    $clean = $s->sanitize('<a href="x" target="_blank" rel="noopener">link</a>');
    expect($clean)->toContain('href="x"');
    expect($clean)->not->toContain('target=');
    expect($clean)->not->toContain('rel=');
});

it('preserves UTF-8 content correctly', function (): void {
    $s = new HtmlSanitizer;
    $clean = $s->sanitize('<p>Привет, мир! 你好世界</p>');
    expect($clean)->toContain('Привет');
    expect($clean)->toContain('你好世界');
});

it('Wysiwyg::shouldSanitize defaults to true', function (): void {
    $f = Dskripchenko\LaravelAdmin\Field\Wysiwyg::make('body');
    expect($f->shouldSanitize())->toBeTrue();
});

it('Wysiwyg::sanitize(false) disables sanitization', function (): void {
    $f = Dskripchenko\LaravelAdmin\Field\Wysiwyg::make('body')->sanitize(false);
    expect($f->shouldSanitize())->toBeFalse();
});
