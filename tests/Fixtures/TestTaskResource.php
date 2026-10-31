<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\DatePicker;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Filter\OptionsFilter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Resource\Views\CalendarView;
use Dskripchenko\LaravelAdmin\Resource\Views\KanbanView;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * @internal
 */
final class TestTaskOwnerModel extends Model
{
    protected $table = 'task_owners';

    protected $guarded = [];
}

/**
 * A task with a status, a priority, an owner, a due date, a position and an
 * interval — the record the kanban and calendar views are tested on.
 *
 * @internal
 */
final class TestTaskModel extends Model
{
    protected $table = 'tasks';

    protected $guarded = [];

    /** @var list<string> The model events seen, for the tests. */
    public static array $seen = [];

    protected function casts(): array
    {
        return [
            'due_at' => 'date',
            'day' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (self $task): void {
            self::$seen[] = 'updating:'.$task->getKey();
        });
        self::updated(static function (self $task): void {
            self::$seen[] = 'updated:'.$task->getKey();
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(TestTaskOwnerModel::class, 'owner_id');
    }

    public static function createSchema(): void
    {
        Schema::create('task_owners', static function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('tasks', static function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('status')->nullable();
            $t->string('priority')->nullable();
            $t->string('labels')->nullable();
            $t->foreignId('owner_id')->nullable();
            $t->date('due_at')->nullable();
            $t->integer('position')->default(0);
            $t->dateTime('starts_at')->nullable();
            $t->dateTime('ends_at')->nullable();
            $t->boolean('all_day')->default(false);
            $t->date('day')->nullable();
            $t->boolean('locked')->default(false);
            $t->timestamps();
        });
    }
}

/**
 * @internal
 */
class TestTaskResource extends Resource
{
    public static string $model = TestTaskModel::class;

    public function reorderable(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [
            Input::make('name')->required(),
            Select::make('status')->options(['todo' => 'To do', 'doing' => 'Doing', 'done' => 'Done']),
            Select::make('priority')->options(['high' => 'High', 'low' => 'Low']),
            DatePicker::make('starts_at')->withTime(),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id'),
            TableColumn::make('name')->search(),
            TableColumn::make('status'),
        ];
    }

    public function filters(): array
    {
        return [OptionsFilter::for('priority')->options(['high' => 'High', 'low' => 'Low'])];
    }

    public function editableForRow(Model $row, string $column): bool
    {
        return ! $row->getAttribute('locked');
    }

    public function kanban(): ?KanbanView
    {
        return KanbanView::make('status')
            ->title('name')
            ->tags('labels')
            ->assignee('owner.name')
            ->due('due_at')
            ->lanes('priority')
            ->wip(['doing' => 2])
            ->limit(2);
    }

    public function calendar(): ?CalendarView
    {
        return CalendarView::make('starts_at', 'ends_at')
            ->allDay('all_day')
            ->title('name')
            ->color('priority', ['high' => 'danger'])
            ->views(['month', 'week'])
            ->initialView('week');
    }
}

/**
 * The same tasks on a read-only board and a calendar by one date column.
 *
 * @internal
 */
final class TestReadonlyTaskResource extends TestTaskResource
{
    public function kanban(): ?KanbanView
    {
        return KanbanView::make('status')->columns(['todo' => 'To do', 'done' => 'Done'])->readonly();
    }

    public function calendar(): ?CalendarView
    {
        return CalendarView::make('day');
    }
}

/**
 * @internal
 */
enum TestTaskStatus: string
{
    case Todo = 'todo';
    case Done = 'done';
}
