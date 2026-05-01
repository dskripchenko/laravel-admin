<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs\Resources;

use Dskripchenko\LaravelAdmin\Action\BulkAction;
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Filter\DateRangeFilter;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Filter\OptionsFilter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdminJobs\Models\FailedJob;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resource для просмотра / retry / forget failed-jobs.
 *
 * Read-only форма (нет fields()) — изменять failed_job не имеет смысла,
 * только retry либо forget. Поэтому view + actions, без create/update.
 *
 * Permissions:
 *   - admin.system.jobs.failed.view   — list + view
 *   - admin.system.jobs.failed.retry  — retry-action (single + bulk)
 *   - admin.system.jobs.failed.forget — forget-action (single + bulk)
 */
final class FailedJobResource extends Resource
{
    public static string $model = FailedJob::class;

    public static string $icon = 'alert-octagon';

    public static ?string $group = 'Системные';

    public static function slug(): string
    {
        return 'system-failed-jobs';
    }

    public static function permission(): string
    {
        return 'admin.system.jobs.failed';
    }

    public static function label(): string
    {
        return 'Failed jobs';
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort()->width('60px'),
            TableColumn::make('uuid')->copyable()->width('260px'),
            TableColumn::make('connection')->sort()->search(),
            TableColumn::make('queue')->sort()->search()->asBadge([
                'default' => 'default',
                'high' => 'warning',
                'low' => 'info',
            ]),
            TableColumn::make('exception_class')
                ->label('Exception')
                ->search()
                ->width('260px'),
            TableColumn::make('exception_message')
                ->label('Сообщение')
                ->search(),
            TableColumn::make('failed_at')->sort()->asDateTime(),
        ];
    }

    public function filters(): array
    {
        return [
            InputFilter::for('connection')->label('Connection'),
            InputFilter::for('queue')->label('Queue'),
            InputFilter::for('exception')
                ->label('Exception (substring)'),
            DateRangeFilter::for('failed_at')->label('Период падений'),
            OptionsFilter::for('exception_class_group')
                ->label('Группа exception'),
        ];
    }

    public function actions(): array
    {
        return [
            Button::make('Retry')
                ->method('retry')
                ->permission('admin.system.jobs.failed.retry')
                ->confirm('Перезапустить упавший job?'),

            Button::make('Forget')
                ->method('forget')
                ->permission('admin.system.jobs.failed.forget')
                ->confirm('Удалить запись из failed_jobs? Job не будет перезапущен.'),

            BulkAction::make('Retry batch')
                ->method('retryBatch')
                ->permission('admin.system.jobs.failed.retry')
                ->requiresAtLeast(1),

            BulkAction::make('Forget batch')
                ->method('forgetBatch')
                ->permission('admin.system.jobs.failed.forget')
                ->requiresAtLeast(1),
        ];
    }

    public function searchableFields(): array
    {
        // Поиск через подстроку — exception хранится как строка blob'а,
        // делаем LIKE по нему + queue/connection.
        return ['exception', 'queue', 'connection', 'uuid'];
    }

    public function indexQuery(): Builder
    {
        return $this->modelQuery()->orderByDesc('failed_at');
    }
}
