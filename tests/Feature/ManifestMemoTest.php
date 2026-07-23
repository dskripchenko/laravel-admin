<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Support\Manifest;

it('Manifest::build memoized per locale|panel within the instance', function (): void {
    $m = app(Manifest::class);
    $a = $m->build('ru', 'admin');
    $b = $m->build('ru', 'admin');
    expect($b)->toBe($a); // тот же массив из memo — сборка не повторялась

    // version() переиспользует memo (bootstrap больше не строит дважды)
    expect($m->version('ru', 'admin'))->toBe($a['version']);

    // другая локаль — отдельная сборка
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
