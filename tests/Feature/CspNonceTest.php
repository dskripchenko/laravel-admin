<?php

declare(strict_types=1);

it('AdminCspNonce middleware injects nonce into shell view', function (): void {
    $response = $this->get('/admin');
    $response->assertOk();

    $html = $response->getContent();
    expect($html)->toMatch('/<script[^>]+nonce="[A-Za-z0-9+\/=]+"/');
});

it('nonce differs between requests', function (): void {
    $first = $this->get('/admin')->getContent();
    $second = $this->get('/admin')->getContent();

    preg_match('/nonce="([A-Za-z0-9+\/=]+)"/', $first, $a);
    preg_match('/nonce="([A-Za-z0-9+\/=]+)"/', $second, $b);

    expect($a[1] ?? '')->not->toBeEmpty();
    expect($b[1] ?? '')->not->toBeEmpty();
    expect($a[1])->not->toBe($b[1]);
});

it('inline bootstrap script uses nonce when strategy=inline', function (): void {
    config()->set('admin.bootstrap.strategy', 'inline');

    $response = $this->get('/admin');
    $html = $response->getContent();

    // Должен быть <script nonce="..."> с window.__ADMIN_BOOTSTRAP__
    expect($html)->toContain('window.__ADMIN_BOOTSTRAP__');
    expect($html)->toMatch('/<script\s+nonce="[A-Za-z0-9+\/=]+"\s*>\s*\n?\s*window\.__ADMIN_BOOTSTRAP__/');
});

it('xhr strategy emits no inline bootstrap', function (): void {
    config()->set('admin.bootstrap.strategy', 'xhr');

    $html = $this->get('/admin')->getContent();
    expect($html)->not->toContain('window.__ADMIN_BOOTSTRAP__');
});

it('nonce length is base64-encoded 16 random bytes (128 bit entropy)', function (): void {
    $html = $this->get('/admin')->getContent();
    preg_match('/nonce="([A-Za-z0-9+\/=]+)"/', $html, $matches);
    $nonce = $matches[1] ?? '';

    expect(strlen(base64_decode($nonce)))->toBe(16);
});
