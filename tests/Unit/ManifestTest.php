<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Support\Manifest;

beforeEach(function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();

    /** @var ScreenRegistry $screens */
    $screens = app(ScreenRegistry::class);
    $screens->clear();
});

it('builds manifest with resources, screens and metadata', function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->add(TestUserResource::class);

    /** @var ScreenRegistry $sr */
    $sr = app(ScreenRegistry::class);
    $sr->add(TestDashboardScreen::class);

    /** @var Manifest $manifest */
    $manifest = app(Manifest::class);

    $payload = $manifest->build('ru');

    expect($payload)->toHaveKeys(['version', 'locale', 'resources', 'screens', 'plugins']);
    expect($payload['locale'])->toBe('ru');
    expect($payload['resources'])->toHaveCount(1);
    expect($payload['resources'][0]['slug'])->toBe('test-users');
    expect($payload['screens'])->toHaveCount(1);
    expect($payload['screens'][0]['slug'])->toBe('test-dashboard');
});

it('manifest version is deterministic for the same content', function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->add(TestUserResource::class);

    /** @var Manifest $manifest */
    $manifest = app(Manifest::class);

    $v1 = $manifest->build('ru')['version'];
    $v2 = $manifest->build('ru')['version'];

    expect($v1)->toBe($v2);
});

it('manifest version changes when adding a resource', function (): void {
    /** @var Manifest $manifest */
    $manifest = app(Manifest::class);

    $empty = $manifest->build('ru')['version'];

    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->add(TestUserResource::class);
    $manifest->flush(); // memo: сборка кэшируется per-instance — реестр мутирован

    $withResource = $manifest->build('ru')['version'];

    expect($empty)->not->toBe($withResource);
});

it('manifest version changes between locales', function (): void {
    /** @var Manifest $manifest */
    $manifest = app(Manifest::class);

    $ru = $manifest->build('ru')['version'];
    $en = $manifest->build('en')['version'];

    expect($ru)->not->toBe($en);
});

it('manifest version() returns same hash as full build', function (): void {
    /** @var Manifest $manifest */
    $manifest = app(Manifest::class);

    expect($manifest->version('ru'))->toBe($manifest->build('ru')['version']);
});
