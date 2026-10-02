<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;

/**
 * A test screen that echoes the query parameters it was opened with.
 *
 * @internal
 */
final class TestQueryScreen extends Screen
{
    public function name(): string
    {
        return 'Query';
    }

    public function permission(): array|string|null
    {
        return null;
    }

    public function query(mixed ...$params): Repository|array
    {
        return [
            'params' => $params,
            'tab' => request()->query('tab', 'overview'),
        ];
    }

    public function layout(): array
    {
        return [];
    }
}
