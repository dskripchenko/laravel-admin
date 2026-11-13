<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Layout\Timeline;

it('serializes a state-key feed with its kinds', function (): void {
    $arr = Layout::timeline('events')
        ->types(['mail' => ['label' => 'Письма', 'icon' => 'mail', 'tone' => 'info'], 'kias' => 'КИАС'])
        ->title('События')
        ->limit(20)
        ->toArray();

    expect($arr['type'])->toBe('timeline')
        ->and($arr['source'])->toBe('events')
        ->and($arr['limit'])->toBe(20)
        ->and($arr['types'])->toBe([
            ['key' => 'mail', 'label' => 'Письма', 'icon' => 'mail', 'tone' => 'info'],
            ['key' => 'kias', 'label' => 'КИАС', 'icon' => null, 'tone' => null],
        ]);
});

it('resolves a closure of events only when serialized', function (): void {
    $calls = 0;
    $layout = Timeline::make(function () use (&$calls): array {
        $calls++;

        return [['type' => 'mail', 'title' => 'Письмо', 'time' => '2026-11-13T14:15:00Z']];
    })->withoutFilters();
    expect($calls)->toBe(0);

    $arr = $layout->toArray();
    expect($calls)->toBe(1)
        ->and($arr['events'][0]['title'])->toBe('Письмо')
        ->and($arr['filters'])->toBeFalse()
        ->and($arr)->not->toHaveKey('source');
});

it('refuses a limit below one', function (): void {
    Layout::timeline([])->limit(0);
})->throws(InvalidArgumentException::class);
