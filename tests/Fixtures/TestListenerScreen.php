<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;
use Illuminate\Http\Request;

/**
 * A screen with two listeners: the cities of the chosen country, and an order
 * total recalculated by a screen method.
 *
 * @internal
 */
final class TestListenerScreen extends Screen
{
    /** @var array<string, array<string, string>> */
    public const CITIES = [
        'ru' => ['msk' => 'Moscow', 'spb' => 'Saint Petersburg'],
        'de' => ['ber' => 'Berlin', 'muc' => 'Munich'],
    ];

    public function permission(): array|string|null
    {
        return 'admin.listener-screen.view';
    }

    public function query(mixed ...$params): Repository|array
    {
        return ['country' => 'de', 'city' => null, 'price' => 10, 'quantity' => 2, 'total' => 20];
    }

    public function layout(): array
    {
        return [
            Layout::rows([
                Select::make('country')->options(['ru' => 'Russia', 'de' => 'Germany']),
                Layout::listener(fn (array $state): array => [
                    Select::make('city')->options(self::CITIES[$state['country'] ?? ''] ?? []),
                ])->listen('country')->withId('cities'),
                Layout::block('Order', [
                    Number::make('price'),
                    Number::make('quantity'),
                    Layout::listener([Number::make('total')->readonly()])
                        ->listen(['price', 'quantity'])
                        ->handler('recalculateTotal'),
                ]),
                Layout::listener([Input::make('nope')])->listen('x')->handler('query')->withId('reserved'),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function recalculateTotal(array $state, Request $request): array
    {
        return ['total' => (float) ($state['price'] ?? 0) * (int) ($state['quantity'] ?? 0)];
    }
}
