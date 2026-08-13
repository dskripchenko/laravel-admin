<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Support\Manifest;

it('Manifest::build memoized per locale|panel within the instance', function (): void {
    $m = app(Manifest::class);
    $a = $m->build('ru', 'admin');
    $b = $m->build('ru', 'admin');
    expect($b)->toBe($a); // тот же массив из memo — сборка не повторялась

    // version() reuses the memo (the bootstrap no longer builds it twice)
    expect($m->version('ru', 'admin'))->toBe($a['version']);

    // a different locale means a separate build
    $en = $m->build('en', 'admin');
    expect($en['locale'])->toBe('en');
});

it('Manifest::flush drops the memo', function (): void {
    $m = app(Manifest::class);
    $m->build('ru', 'admin');
    $m->flush();
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    expect($m->build('ru', 'admin')['resources'])->toBe([]);
});

it('Manifest и BootstrapBuilder живут ровно запрос, а не сколько воркер', function (): void {
    // The memo was written for FPM, where a singleton IS "one request". Under
    // Octane a singleton would outlive the request and the deduplication would
    // turn into a cross-request cache keyed by something that does not describe
    // everything the content depends on (the permission filtering inside
    // Manifest itself is marked as upcoming). We check with exactly what Octane
    // separates requests by.
    $first = app(Manifest::class);
    $firstBoot = app(Dskripchenko\LaravelAdmin\Support\BootstrapBuilder::class);
    expect(app(Manifest::class))->toBe($first); // в пределах запроса — один

    app()->forgetScopedInstances(); // граница запроса под Octane

    expect(app(Manifest::class))->not->toBe($first, 'Manifest пережил границу запроса');
    expect(app(Dskripchenko\LaravelAdmin\Support\BootstrapBuilder::class))
        ->not->toBe($firstBoot, 'BootstrapBuilder держит Manifest — обязан жить столько же');
});
