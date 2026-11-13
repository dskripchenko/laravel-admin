<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fixtures of the 1.73 navigation tests: a journal with named scopes and a
 * masked column, a screen of queue tabs, a screen embedded into it, a
 * dashboard with the new periods.
 *
 * @internal
 */
final class TestQueueResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public static function slug(): string
    {
        return 'test-queue';
    }

    public static function permission(): string
    {
        return 'admin.queue';
    }

    public function fields(): array
    {
        return [Input::make('name')];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id'),
            TableColumn::make('name')->search(),
            TableColumn::make('email')->masked('email'),
            TableColumn::make('status'),
        ];
    }

    public function filters(): array
    {
        return [InputFilter::for('name')];
    }

    public function scopes(): array
    {
        return [
            'pending' => fn (Builder $q) => $q->whereNull('status'),
            'sent' => fn (Builder $q) => $q->whereNotNull('status'),
        ];
    }
}

/** @internal */
final class TestSecretScreen extends Screen
{
    public static function slug(): string
    {
        return 'test-secret';
    }

    public function name(): string
    {
        return 'Secret';
    }

    public function permission(): array|string|null
    {
        return 'admin.secret';
    }

    public function query(mixed ...$params): Repository|array
    {
        return ['mode' => $params[0] ?? null];
    }

    public function layout(): array
    {
        return [Layout::rows([Input::make('mode')])];
    }
}

/** @internal */
final class TestQueueTabsScreen extends Screen
{
    public static int $badgeCalls = 0;

    public static function slug(): string
    {
        return 'test-queues';
    }

    public function name(): string
    {
        return 'Queues';
    }

    public function permission(): array|string|null
    {
        return null;
    }

    public function query(mixed ...$params): Repository|array
    {
        return [];
    }

    public function layout(): array
    {
        return [
            Layout::tabs([
                'Pending' => Layout::resourceIndex(TestQueueResource::class)->queryKey('pending')->scope('pending')
                    ->defaultFilters(['name' => 'Ann']),
                'All' => Layout::tabs([
                    'Recent' => Layout::resourceIndex(TestQueueResource::class)->queryKey('recent'),
                    'Secret' => Layout::screen(TestSecretScreen::class, ['mode' => 'embedded']),
                ])->persistInQuery('all-tab')->keys(['recent', 'secret']),
            ])->persistInQuery('tab')->keys(['pending', 'all'])->badges([
                'pending' => function (): array {
                    self::$badgeCalls++;

                    return ['value' => app(TestQueueResource::class)->countIn('pending'), 'tone' => 'danger'];
                },
                'all' => 120,
            ]),
        ];
    }
}

/** @internal */
final class TestPeriodDashboard extends DashboardScreen
{
    public static function slug(): string
    {
        return 'test-periods';
    }

    public function name(): string
    {
        return 'Periods';
    }

    public function periods(): ?array
    {
        return ['today', '7d', 'month', 'quarter'];
    }

    public function customRange(): bool
    {
        return true;
    }

    public function comparable(): bool
    {
        return true;
    }

    public function defaultPeriod(): string
    {
        return '7d';
    }

    public function widgets(): array
    {
        $ctx = $this->dashboardContext();

        return [
            StatsOverviewWidget::make()
                ->stat('Orders', 10, url: $ctx->listUrl('/r/test-queue', 'created_at'))
                ->previous($ctx->comparing() !== null ? 7 : null)
                ->link('/r/test-queue'),
        ];
    }
}
