<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelAdmin\Widget\MarkdownWidget;
use Dskripchenko\LaravelAdmin\Widget\Widget;

/**
 * Тестовый Dashboard для unit/feature-тестов.
 *
 * @internal
 */
final class TestDashboard extends DashboardScreen
{
    public function name(): string
    {
        return 'Test Dashboard';
    }

    public function widgets(): array
    {
        return [
            new TestStatsAWidget,
            new TestStatsBWidget,
            MarkdownWidget::make()->title('Markdown')->size(4)->content('# Hi'),
        ];
    }
}

/**
 * @internal
 */
final class TestStatsAWidget extends Widget
{
    public function widgetType(): string
    {
        return 'stats';
    }

    public function data(): array
    {
        return ['stats' => [['label' => 'A', 'value' => 1]]];
    }
}

/**
 * @internal
 */
final class TestStatsBWidget extends Widget
{
    public function widgetType(): string
    {
        return 'stats';
    }

    public function data(): array
    {
        return ['stats' => [['label' => 'B', 'value' => 2]]];
    }
}
