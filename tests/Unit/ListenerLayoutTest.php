<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Layout\Listener;
use Dskripchenko\LaravelAdmin\Support\Repository;
use Illuminate\Http\Request;

it('derives a stable id from the watched fields and the handler', function (): void {
    $a = Layout::listener([])->listen(['a', 'b'])->handler('calc');
    $b = Layout::listener([])->listen(['a', 'b'])->handler('calc');
    $c = Layout::listener([])->listen(['a'])->handler('calc');

    expect($a->id())->toBe($b->id())
        ->and($a->id())->not->toBe($c->id())
        ->and(Layout::listener([])->withId('x')->id())->toBe('x');
});

it('renders a closure against an empty state until primed', function (): void {
    $listener = Layout::listener(fn (array $s): array => [Input::make('x')->title((string) ($s['x'] ?? 'empty'))])
        ->listen('x');

    expect($listener->toArray()['items'][0]['label'])->toBe('empty')
        ->and($listener->toArray()['primed'])->toBeFalse();

    $listener->prime(['x' => 'full']);
    expect($listener->toArray()['items'][0]['label'])->toBe('full')
        ->and($listener->toArray()['primed'])->toBeTrue();
});

it('finds listeners nested in tabs and accordions', function (): void {
    $inTabs = Layout::listener([])->listen('a')->withId('in-tabs');
    $inAccordion = Layout::listener([])->listen('b')->withId('in-accordion');

    $tree = [
        Layout::tabs(['One' => [Layout::block('B', [$inTabs])]]),
        Layout::accordion(['Section' => [$inAccordion]]),
    ];

    expect(Listener::find($tree, 'in-tabs'))->toBe($inTabs)
        ->and(Listener::find($tree, 'in-accordion'))->toBe($inAccordion)
        ->and(Listener::find($tree, 'missing'))->toBeNull()
        ->and(Listener::all($tree))->toHaveCount(2);
});

it('keeps a listener node when it is the content of a tab', function (): void {
    $tabs = Layout::tabs(['One' => Layout::listener([Input::make('x')])->listen('y')->withId('l')])->toArray();

    expect($tabs['items'][0]['items'][0]['type'])->toBe('listener')
        ->and($tabs['items'][0]['items'][0]['id'])->toBe('l');
});

it('accepts a closure handler returning a Repository', function (): void {
    $listener = Layout::listener(fn (array $s): array => [Input::make('total')->title((string) $s['total'])])
        ->listen(['price'])
        ->handler(fn (array $s, Request $r): Repository => Repository::make(['total' => $s['price'] * 2]));

    $result = $listener->respond(new stdClass, ['price' => 3], Request::create('/'));

    expect($result['state'])->toBe(['total' => 6])
        ->and($result['layouts'][0]['label'])->toBe('6');
});
