<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;

/**
 * Demo Screen для unit/feature тестов.
 *
 * @internal
 */
final class TestDashboardScreen extends Screen
{
    public function name(): string
    {
        return 'Dashboard';
    }

    public function description(): ?string
    {
        return 'Test dashboard for unit tests';
    }

    public function permission(): array|string|null
    {
        return ['admin.dashboard.view'];
    }

    public function commandBar(): array
    {
        return [
            Button::make('Refresh')->method('refresh'),
        ];
    }

    public function query(mixed ...$params): Repository|array
    {
        return new Repository([
            'stats' => ['users' => 42, 'orders' => 17],
        ]);
    }

    public function layout(): array
    {
        return [
            Layout::rows([Input::make('search')->placeholder('Поиск...')]),
        ];
    }

    public function refresh(): string
    {
        return 'refreshed';
    }

    public function privateMethod(): string
    {
        return 'should not be callable';
    }
}
