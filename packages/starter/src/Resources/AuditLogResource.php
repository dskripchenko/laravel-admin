<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminStarter\Resources;

use Dskripchenko\LaravelAdmin\Audit\AuditLog;
use Dskripchenko\LaravelAdmin\Filter\DateRangeFilter;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Filter\OptionsFilter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * AuditLogResource — view-only для `admin_audit_logs`.
 *
 * Read-only: list + view; нет create/update/delete (audit-history
 * immutable). UI рендерит inline diff old vs new (через core's
 * AuditTrail layout, не дублируется здесь).
 *
 * Permissions: admin.system.audit.view.
 */
final class AuditLogResource extends Resource
{
    public static string $model = AuditLog::class;

    public static string $icon = 'history';

    public static ?string $group = 'Системные';

    public static function slug(): string
    {
        return 'system-audit';
    }

    public static function permission(): string
    {
        return 'admin.system.audit';
    }

    public static function label(): string
    {
        return 'Журнал аудита';
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort()->width('60px'),
            TableColumn::make('event')->sort()->asBadge([
                'created' => 'success',
                'updated' => 'info',
                'deleted' => 'danger',
                'restored' => 'warning',
                'login' => 'info',
                'logout' => 'default',
                'login_failed' => 'danger',
            ]),
            TableColumn::make('actor_type')->search()->copyable(),
            TableColumn::make('actor_id')->align('right'),
            TableColumn::make('subject_type')->search()->copyable(),
            TableColumn::make('subject_id')->align('right'),
            TableColumn::make('ip')->copyable(),
            TableColumn::make('created_at')->sort()->asDateTime(),
        ];
    }

    public function filters(): array
    {
        return [
            OptionsFilter::for('event')->label('Событие')->options([
                'created' => 'Создание',
                'updated' => 'Изменение',
                'deleted' => 'Удаление',
                'restored' => 'Восстановление',
                'login' => 'Вход',
                'logout' => 'Выход',
                'login_failed' => 'Неудачный вход',
            ]),
            InputFilter::for('actor_type')->label('Actor type'),
            InputFilter::for('subject_type')->label('Subject type'),
            DateRangeFilter::for('created_at')->label('Период'),
            InputFilter::for('ip')->label('IP'),
        ];
    }

    public function indexQuery(): Builder
    {
        return $this->modelQuery()->orderByDesc('created_at');
    }
}
