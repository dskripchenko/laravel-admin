<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;

/**
 * Screens for the permissionMode() and Button::parameters() tests.
 *
 * @internal
 */
final class TestAnyPermissionScreen extends Screen
{
    /** @var list<array{0: string, 1: list<mixed>}> */
    public static array $calls = [];

    public static function slug(): string
    {
        return 'test-any-section';
    }

    public function name(): string
    {
        return 'Section';
    }

    public function permission(): array|string|null
    {
        return ['admin.section.orders', 'admin.section.quotes'];
    }

    public function permissionMode(): string
    {
        return self::PERMISSION_ANY;
    }

    public function query(mixed ...$params): Repository|array
    {
        return ['title' => 'x'];
    }

    public function layout(): array
    {
        return [
            Rows::make([
                Button::make('Sync marks')->method('sync')->parameters(['dictionary' => 'vehicle_marks']),
                Button::make('Sync models')->method('sync')->params(['dictionary' => 'vehicle_models'])
                    ->permission('admin.dictionaries.models'),
            ]),
        ];
    }

    public function commandBar(): array
    {
        return [
            Button::make('Ping')->method('ping')->parameters(['source' => 'bar']),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $parameters
     */
    public function sync(array $state, array $parameters = []): array
    {
        self::$calls[] = ['sync', [$state, $parameters]];

        return ['message' => 'synced '.($parameters['dictionary'] ?? '-')];
    }

    /** A one-argument method: the parameters do not reach it. */
    public function ping(array $state = []): void
    {
        self::$calls[] = ['ping', func_get_args()];
    }
}

/**
 * @internal
 */
final class TestAllPermissionScreen extends Screen
{
    public static function slug(): string
    {
        return 'test-all-section';
    }

    public function name(): string
    {
        return 'Strict section';
    }

    public function permission(): array|string|null
    {
        return ['admin.section.orders', 'admin.section.quotes'];
    }

    public function query(mixed ...$params): Repository|array
    {
        return [];
    }

    public function layout(): array
    {
        return [];
    }
}

/**
 * @internal
 */
final class TestAnyPermissionDashboard extends DashboardScreen
{
    public static function slug(): string
    {
        return 'test-any-dash';
    }

    public function permission(): array|string|null
    {
        return ['admin.section.orders', 'admin.section.quotes'];
    }

    public function permissionMode(): string
    {
        return self::PERMISSION_ANY;
    }

    public function widgets(): array
    {
        return [];
    }
}
