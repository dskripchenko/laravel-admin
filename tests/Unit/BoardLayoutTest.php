<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Layout\Board;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Widget\BoardWidget;

it('serializes the board contract props', function (): void {
    $array = Layout::board()
        ->endpoint('/boards/42/')
        ->user(['id' => 7, 'name' => 'Anna', 'color' => '#ec4899'])
        ->realtime(['ticketUrl' => '/realtime/ticket', 'channel' => 'admin-board.42'])
        ->pollInterval(1000)
        ->readonly()
        ->title('Sprint')
        ->height(500)
        ->toArray();

    expect($array)->toMatchArray([
        'type' => 'board',
        'endpoint' => '/boards/42',
        'readonly' => true,
        'user' => ['id' => '7', 'name' => 'Anna', 'color' => '#ec4899'],
        'realtime' => ['ticketUrl' => '/realtime/ticket', 'channel' => 'admin-board.42'],
        'pollInterval' => 1000,
        'title' => 'Sprint',
        'height' => '500px',
    ]);
});

it('defaults to polling every 1.5 s, editable, without a user', function (): void {
    $array = Layout::board()->endpoint('/boards/1')->toArray();

    expect($array)->toMatchArray(['readonly' => false, 'user' => null, 'realtime' => null, 'pollInterval' => 1500])
        ->and(Layout::board())->toBeInstanceOf(Board::class);
});

it('drops empty user fields', function (): void {
    expect(Layout::board()->endpoint('/b')->user(['id' => 3, 'name' => ''])->toArray()['user'])
        ->toBe(['id' => '3', 'name' => null, 'color' => null]);
});

it('refuses an endpoint that is not a relative path', function (string $endpoint): void {
    Layout::board()->endpoint($endpoint);
})->with(['https://example.com/boards/1', '//example.com/b', 'boards/1', '/boards 1'])
    ->throws(InvalidArgumentException::class);

it('needs an endpoint', function (): void {
    Layout::board()->toArray();
})->throws(InvalidArgumentException::class, 'endpoint');

it('refuses a poll interval under 250 ms and an incomplete realtime', function (): void {
    expect(fn () => Layout::board()->pollInterval(100))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::board()->realtime(['channel' => 'x']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::board()->height(0))->toThrow(InvalidArgumentException::class);
    expect(Layout::board()->endpoint('/b')->realtime(null)->toArray()['realtime'])->toBeNull();
});

it('builds a board widget with the same props', function (): void {
    $array = BoardWidget::make()->title('Board')->size(12)
        ->endpoint('/boards/9')->user(['id' => '1', 'name' => 'Ann'])->readonly()
        ->realtime(['ticketUrl' => '/t', 'channel' => 'admin-board.9'])->pollInterval(2000)
        ->toArray();

    expect($array['type'])->toBe('board')
        ->and($array['rowSpan'] ?? $array['row_span'] ?? null)->toBe(4)
        ->and($array['data'])->toBe([
            'endpoint' => '/boards/9',
            'readonly' => true,
            'user' => ['id' => '1', 'name' => 'Ann', 'color' => null],
            'realtime' => ['ticketUrl' => '/t', 'channel' => 'admin-board.9'],
            'pollInterval' => 2000,
        ]);
    expect(fn () => BoardWidget::make()->toArray())->toThrow(InvalidArgumentException::class);
});
