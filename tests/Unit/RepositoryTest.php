<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Support\Repository;

it('reads and writes via dot-notation', function (): void {
    $repo = new Repository;
    $repo->set('user.address.city', 'Moscow');
    expect($repo->get('user.address.city'))->toBe('Moscow');
    expect($repo->has('user.address.city'))->toBeTrue();
});

it('returns default for missing key', function (): void {
    $repo = new Repository;
    expect($repo->get('missing', 'fallback'))->toBe('fallback');
});

it('forget removes value', function (): void {
    $repo = new Repository(['a' => ['b' => 1, 'c' => 2]]);
    $repo->forget('a.b');
    expect($repo->has('a.b'))->toBeFalse();
    expect($repo->get('a.c'))->toBe(2);
});

it('merge works deeply', function (): void {
    $repo = new Repository(['user' => ['name' => 'Ivan']]);
    $repo->merge(['user' => ['email' => 'ivan@example.com']]);
    expect($repo->get('user.name'))->toBe('Ivan');
    expect($repo->get('user.email'))->toBe('ivan@example.com');
});

it('merge accepts another Repository', function (): void {
    $a = new Repository(['x' => 1]);
    $b = new Repository(['y' => 2]);
    $a->merge($b);
    expect($a->get('x'))->toBe(1);
    expect($a->get('y'))->toBe(2);
});

it('only/except return new repository', function (): void {
    $repo = new Repository(['a' => 1, 'b' => 2, 'c' => 3]);

    $only = $repo->only(['a', 'c']);
    expect($only->toArray())->toBe(['a' => 1, 'c' => 3]);

    $except = $repo->except(['b']);
    expect($except->toArray())->toBe(['a' => 1, 'c' => 3]);
});

it('isEmpty', function (): void {
    expect((new Repository)->isEmpty())->toBeTrue();
    expect((new Repository(['x' => 1]))->isEmpty())->toBeFalse();
});

it('toJson serializes', function (): void {
    $repo = new Repository(['a' => 'один']);
    expect($repo->toJson())->toBe('{"a":"один"}');
});

it('ArrayAccess works', function (): void {
    $repo = new Repository;
    $repo['user.name'] = 'Ivan';
    expect(isset($repo['user.name']))->toBeTrue();
    expect($repo['user.name'])->toBe('Ivan');
    unset($repo['user.name']);
    expect(isset($repo['user.name']))->toBeFalse();
});
