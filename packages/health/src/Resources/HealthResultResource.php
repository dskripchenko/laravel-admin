<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Resources;

use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Filter\OptionsFilter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdminHealth\Models\HealthResultRecord;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resource для просмотра истории health-check'ов.
 *
 * Read-only: list + view, без create/update. Latest first.
 *
 * Permissions:
 *   - admin.system.health.view
 */
final class HealthResultResource extends Resource
{
    public static string $model = HealthResultRecord::class;

    public static string $icon = 'activity';

    public static ?string $group = 'Системные';

    public static function slug(): string
    {
        return 'system-health-results';
    }

    public static function permission(): string
    {
        return 'admin.system.health';
    }

    public static function label(): string
    {
        return 'Health-checks';
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort()->width('60px'),
            TableColumn::make('check_id')->sort()->search()->copyable(),
            TableColumn::make('status')->sort()->asBadge([
                'ok' => 'success',
                'warning' => 'warning',
                'failing' => 'danger',
            ]),
            TableColumn::make('message')->search(),
            TableColumn::make('duration_ms')
                ->label('Длит. (ms)')
                ->align('right')
                ->sort(),
            TableColumn::make('ran_at')->sort()->asDateTime(),
        ];
    }

    public function filters(): array
    {
        return [
            InputFilter::for('check_id')->label('Check ID'),
            OptionsFilter::for('status')->label('Статус')->options([
                'ok' => 'OK',
                'warning' => 'Warning',
                'failing' => 'Failing',
            ]),
        ];
    }

    public function indexQuery(): Builder
    {
        return $this->modelQuery()->orderByDesc('ran_at');
    }
}
