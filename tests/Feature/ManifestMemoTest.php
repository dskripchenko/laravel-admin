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

it('Manifest и BootstrapBuilder живут ровно запрос, а не сколько воркер', function (): void {
    // Memo писался под FPM, где singleton и есть «один запрос». Под Octane
    // singleton пережил бы запрос, и дедупликация превратилась бы в
    // межзапросный кэш по ключу, который не описывает всё, от чего зависит
    // содержимое (фильтрация по правам в самом Manifest помечена как
    // предстоящая). Проверяем ровно тем, чем Octane разделяет запросы.
    $first = app(Manifest::class);
    $firstBoot = app(Dskripchenko\LaravelAdmin\Support\BootstrapBuilder::class);
    expect(app(Manifest::class))->toBe($first); // в пределах запроса — один

    app()->forgetScopedInstances(); // граница запроса под Octane

    expect(app(Manifest::class))->not->toBe($first, 'Manifest пережил границу запроса');
    expect(app(Dskripchenko\LaravelAdmin\Support\BootstrapBuilder::class))
        ->not->toBe($firstBoot, 'BootstrapBuilder держит Manifest — обязан жить столько же');
});
