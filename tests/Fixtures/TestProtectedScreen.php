<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;

/**
 * Тестовый Screen с permission gate.
 *
 * @internal
 */
final class TestProtectedScreen extends Screen
{
    public function permission(): array|string|null
    {
        return 'admin.protected.view';
    }

    public function query(mixed ...$params): Repository|array
    {
        return [];
    }

    public function layout(): array
    {
        return [Rows::make([])];
    }
}
