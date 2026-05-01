<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs\Resources;

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdminJobs\Models\JobBatch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resource для просмотра Bus::batch().
 *
 * Permissions:
 *   - admin.system.jobs.batches.view
 *   - admin.system.jobs.batches.manage  (cancel + retry-failed)
 */
final class JobBatchResource extends Resource
{
    public static string $model = JobBatch::class;

    public static string $icon = 'layers';

    public static ?string $group = 'Системные';

    public static function slug(): string
    {
        return 'system-job-batches';
    }

    public static function permission(): string
    {
        return 'admin.system.jobs.batches';
    }

    public static function label(): string
    {
        return 'Batch jobs';
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->copyable()->width('260px'),
            TableColumn::make('name')->search()->sort(),
            TableColumn::make('total_jobs')->sort()->align('right'),
            TableColumn::make('pending_jobs')->sort()->align('right'),
            TableColumn::make('failed_jobs')->sort()->align('right'),
            TableColumn::make('progress_pct')
                ->label('Progress')
                ->align('right'),
            TableColumn::make('created_at')->sort()->asDateTime(),
            TableColumn::make('finished_at')->sort()->asDateTime(),
            TableColumn::make('cancelled_at')->asDateTime()->defaultHidden(),
        ];
    }

    public function filters(): array
    {
        return [
            InputFilter::for('name')->label('Имя batch'),
        ];
    }

    public function actions(): array
    {
        return [
            Button::make('Cancel batch')
                ->method('cancel')
                ->permission('admin.system.jobs.batches.manage')
                ->confirm('Отменить batch? Pending-jobs не будут выполнены.'),

            Button::make('Retry failed')
                ->method('retryFailed')
                ->permission('admin.system.jobs.batches.manage')
                ->confirm('Перезапустить упавшие job\'ы внутри batch?'),
        ];
    }

    public function indexQuery(): Builder
    {
        return $this->modelQuery()->orderByDesc('created_at');
    }
}
