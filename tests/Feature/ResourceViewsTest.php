<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Resource\Views\CalendarView;
use Dskripchenko\LaravelAdmin\Resource\Views\KanbanView;

/**
 * The kanban and calendar views of a resource: Resource::kanban() and
 * Resource::calendar(), their meta, the board and calendar data and the moves.
 */
beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestTaskResource::class);
    $rr->add(TestReadonlyTaskResource::class);
    $rr->add(TestUserResource::class);
    AdminApi::clearCache();
    TestTaskModel::createSchema();
    TestTaskModel::$seen = [];

    $this->actAs = function (array $permissions): void {
        $user = AdminUser::create([
            'name' => 'U', 'email' => 'u-'.uniqid().'@example.com', 'password' => 'secret',
        ]);
        $role = Role::create(['name' => 'R', 'slug' => 'r-'.uniqid(), 'permissions' => $permissions]);
        $user->assignRole($role);
        $this->actingAs($user->refresh(), 'admin');
    };
    ($this->actAs)(['*']);
});

function task(array $attributes): TestTaskModel
{
    return TestTaskModel::create(['name' => 'Task', ...$attributes]);
}

/* ---------------------------------------------------------------- meta */

it('exports the views and their descriptions in the meta', function (): void {
    $meta = (new TestTaskResource)->meta();

    expect($meta['views'])->toBe(['list', 'kanban', 'calendar'])
        ->and($meta['view_mode'])->toBe('list')
        ->and($meta['kanban']['column'])->toBe('status')
        ->and($meta['kanban']['columns'])->toBe([
            ['value' => 'todo', 'label' => 'To do', 'wip' => null],
            ['value' => 'doing', 'label' => 'Doing', 'wip' => 2],
            ['value' => 'done', 'label' => 'Done', 'wip' => null],
        ])
        ->and($meta['kanban']['lanes'])->toBe('priority')
        ->and($meta['kanban']['limit'])->toBe(2)
        ->and($meta['kanban']['order_by'])->toBe('position')
        ->and($meta['kanban']['saves_position'])->toBeTrue()
        ->and($meta['kanban']['readonly'])->toBeFalse()
        ->and($meta['calendar'])->toMatchArray([
            'start' => 'starts_at',
            'end' => 'ends_at',
            'all_day' => 'all_day',
            'date_only' => false,
            'views' => ['month', 'week'],
            'initial_view' => 'week',
            'readonly' => false,
        ]);
});

it('offers only the list when no view is declared', function (): void {
    $meta = (new TestUserResource)->meta();

    expect($meta['views'])->toBe(['list'])
        ->and($meta['kanban'])->toBeNull()
        ->and($meta['calendar'])->toBeNull();
});

it('takes the board columns from an enum and rejects an unknown calendar view', function (): void {
    $view = KanbanView::make('status')->columns(TestTaskStatus::class);
    expect($view->toArray(new TestTaskResource)['columns'])->toBe([
        ['value' => 'todo', 'label' => 'Todo', 'wip' => null],
        ['value' => 'done', 'label' => 'Done', 'wip' => null],
    ]);

    expect(fn () => CalendarView::make('day')->views(['fortnight']))->toThrow(InvalidArgumentException::class);
    expect((new TestReadonlyTaskResource)->meta()['calendar']['date_only'])->toBeTrue();
});

it('registers the routes only for the declared views', function (): void {
    $this->postJson('/api/admin/test-users/board')->assertNotFound();
    $this->postJson('/api/admin/test-users/calendar', ['from' => '2026-10-01', 'to' => '2026-10-31'])->assertNotFound();
    // A read-only board has data and no moves.
    $this->postJson('/api/admin/test-readonly-tasks/board')->assertOk();
    $this->postJson('/api/admin/test-readonly-tasks/boardMove', ['id' => 1, 'value' => 'done'])->assertNotFound();
});

/* ---------------------------------------------------------------- board */

it('groups the cards by column with their totals, lanes and card fields', function (): void {
    $owner = TestTaskOwnerModel::create(['name' => 'Ann']);
    task(['name' => 'A', 'status' => 'todo', 'priority' => 'high', 'labels' => 'ui, api', 'owner_id' => $owner->id, 'due_at' => '2020-01-01', 'position' => 2]);
    task(['name' => 'B', 'status' => 'todo', 'priority' => 'low', 'position' => 1]);
    task(['name' => 'C', 'status' => 'todo', 'priority' => 'low', 'position' => 3]);
    task(['name' => 'D', 'status' => 'done', 'priority' => 'high', 'due_at' => '2999-01-01']);
    task(['name' => 'E', 'status' => null]);

    $response = $this->postJson('/api/admin/test-tasks/board')->assertOk();
    $columns = collect($response->json('payload.columns'))->keyBy('value');

    expect($columns->keys()->all())->toBe(['todo', 'doing', 'done'])
        ->and($columns['todo']['total'])->toBe(3)
        // limit(2), ordered by position.
        ->and(array_column($columns['todo']['cards'], 'title'))->toBe(['B', 'A'])
        ->and($columns['doing']['wip'])->toBe(2)
        ->and($columns['doing']['cards'])->toBe([])
        ->and($response->json('payload.lanes'))->toBe([
            ['value' => 'high', 'label' => 'High'],
            ['value' => 'low', 'label' => 'Low'],
        ]);

    $a = $columns['todo']['cards'][1];
    expect($a)->toMatchArray([
        'title' => 'A',
        'value' => 'todo',
        'lane' => 'high',
        'tags' => ['ui', 'api'],
        'assignee' => 'Ann',
        'due' => '2020-01-01',
        'overdue' => true,
        'locked' => false,
    ]);
    expect($columns['done']['cards'][0]['overdue'])->toBeFalse();
});

it('applies the list filters and search to the board', function (): void {
    task(['name' => 'Alpha', 'status' => 'todo', 'priority' => 'high']);
    task(['name' => 'Beta', 'status' => 'todo', 'priority' => 'low']);
    task(['name' => 'Alphonse', 'status' => 'done', 'priority' => 'low']);

    $filtered = $this->postJson('/api/admin/test-tasks/board', ['filters' => ['priority' => 'low']])->assertOk();
    $byValue = collect($filtered->json('payload.columns'))->keyBy('value');
    expect(array_column($byValue['todo']['cards'], 'title'))->toBe(['Beta'])
        ->and($byValue['done']['total'])->toBe(1);

    $searched = $this->postJson('/api/admin/test-tasks/board', ['q' => 'Alph'])->assertOk();
    $byValue = collect($searched->json('payload.columns'))->keyBy('value');
    expect(array_column($byValue['todo']['cards'], 'title'))->toBe(['Alpha'])
        ->and(array_column($byValue['done']['cards'], 'title'))->toBe(['Alphonse']);
});

it('pages one column with offset and limit', function (): void {
    foreach (range(1, 5) as $i) {
        task(['name' => "T{$i}", 'status' => 'todo', 'position' => $i]);
    }

    $more = $this->postJson('/api/admin/test-tasks/board', ['column' => 'todo', 'offset' => 2])->assertOk();

    expect($more->json('payload.columns'))->toHaveCount(1)
        ->and($more->json('payload.columns.0.offset'))->toBe(2)
        ->and($more->json('payload.columns.0.total'))->toBe(5)
        ->and(array_column($more->json('payload.columns.0.cards'), 'title'))->toBe(['T3', 'T4']);

    $this->postJson('/api/admin/test-tasks/board', ['column' => 'nope'])->assertStatus(422);
    $this->postJson('/api/admin/test-tasks/board', ['limit' => 1000])->assertStatus(422);
});

it('takes the columns from the records when none are declared', function (): void {
    $view = KanbanView::make('labels');
    $board = new Dskripchenko\LaravelAdmin\Resource\Views\KanbanBoard(new TestTaskResource, $view);
    task(['labels' => 'b']);
    task(['labels' => 'a']);
    task(['labels' => 'b']);

    expect($board->columns(TestTaskModel::query()))->toBe([
        ['value' => 'a', 'label' => 'a'],
        ['value' => 'b', 'label' => 'b'],
    ]);
});

/* ------------------------------------------------------------ boardMove */

it('moves a card to another column through the model', function (): void {
    $t = task(['status' => 'todo', 'priority' => 'low']);

    $response = $this->postJson('/api/admin/test-tasks/boardMove', ['id' => $t->id, 'value' => 'done'])->assertOk();

    expect($response->json('payload.card.value'))->toBe('done')
        ->and($t->fresh()->status)->toBe('done')
        ->and(TestTaskModel::$seen)->toBe(['updating:'.$t->id, 'updated:'.$t->id]);
});

it('moves a card to another lane, checking the lane', function (): void {
    $t = task(['status' => 'todo', 'priority' => 'low']);

    $this->postJson('/api/admin/test-tasks/boardMove', ['id' => $t->id, 'value' => 'doing', 'lane' => 'high'])->assertOk();
    expect($t->fresh()->only('status', 'priority'))->toBe(['status' => 'doing', 'priority' => 'high']);

    $this->postJson('/api/admin/test-tasks/boardMove', ['id' => $t->id, 'value' => 'doing', 'lane' => 'urgent'])
        ->assertStatus(422);
    expect($t->fresh()->priority)->toBe('high');
});

it('refuses a value that is not a column with 422', function (): void {
    $t = task(['status' => 'todo']);

    $response = $this->postJson('/api/admin/test-tasks/boardMove', ['id' => $t->id, 'value' => 'archived']);

    $response->assertStatus(422);
    expect($t->fresh()->status)->toBe('todo')
        ->and(TestTaskModel::$seen)->toBe([]);
});

it('refuses a move without the update permission with 403', function (): void {
    $t = task(['status' => 'todo']);
    ($this->actAs)(['admin.test-tasks.view']);

    $this->postJson('/api/admin/test-tasks/boardMove', ['id' => $t->id, 'value' => 'done'])->assertForbidden();
    $this->postJson('/api/admin/test-tasks/calendarMove', ['id' => $t->id, 'start' => '2026-10-01'])->assertForbidden();
    expect($t->fresh()->status)->toBe('todo');
});

it('refuses a move editableForRow forbids with 403 and marks the card locked', function (): void {
    $t = task(['status' => 'todo', 'locked' => true]);

    $board = $this->postJson('/api/admin/test-tasks/board')->assertOk();
    expect(collect($board->json('payload.columns'))->firstWhere('value', 'todo')['cards'][0]['locked'])->toBeTrue();

    $response = $this->postJson('/api/admin/test-tasks/boardMove', ['id' => $t->id, 'value' => 'done']);
    $response->assertForbidden();
    expect($response->json('payload.errorKey'))->toBe('forbidden')
        ->and($t->fresh()->status)->toBe('todo');
});

it('saves a drop position the way reorder does', function (): void {
    $a = task(['name' => 'A', 'status' => 'done', 'position' => 10]);
    $b = task(['name' => 'B', 'status' => 'done', 'position' => 20]);
    $c = task(['name' => 'C', 'status' => 'done', 'position' => 30]);
    $moved = task(['name' => 'M', 'status' => 'todo', 'position' => 5]);

    // Into "done", between A and B.
    $response = $this->postJson('/api/admin/test-tasks/boardMove', [
        'id' => $moved->id, 'value' => 'done', 'position' => 1,
    ])->assertOk();

    // The four records trade the positions they held: 5, 10, 20, 30.
    expect($moved->fresh()->only('status', 'position'))->toBe(['status' => 'done', 'position' => 10])
        ->and($a->fresh()->position)->toBe(5)
        ->and($b->fresh()->position)->toBe(20)
        ->and($c->fresh()->position)->toBe(30)
        ->and((array) $response->json('payload.positions'))->toHaveCount(4);

    $titles = array_column(collect($this->postJson('/api/admin/test-tasks/board', ['column' => 'done', 'limit' => 10])
        ->json('payload.columns'))->first()['cards'], 'title');
    expect($titles)->toBe(['A', 'M', 'B', 'C']);
});

it('ignores the position for a user who may not reorder', function (): void {
    $a = task(['name' => 'A', 'status' => 'done', 'position' => 1]);
    $moved = task(['name' => 'M', 'status' => 'todo', 'position' => 9]);
    ($this->actAs)(['admin.test-tasks.view', 'admin.test-tasks.update']);

    $this->postJson('/api/admin/test-tasks/boardMove', ['id' => $moved->id, 'value' => 'done', 'position' => 0])->assertOk();

    expect($moved->fresh()->only('status', 'position'))->toBe(['status' => 'done', 'position' => 9])
        ->and($a->fresh()->position)->toBe(1);
});

/* ------------------------------------------------------------- calendar */

it('returns the events that intersect the range, across its edges', function (): void {
    task(['name' => 'inside', 'starts_at' => '2026-10-10 09:00:00', 'ends_at' => '2026-10-10 10:30:00']);
    task(['name' => 'from before', 'starts_at' => '2026-09-28 09:00:00', 'ends_at' => '2026-10-02 18:00:00']);
    task(['name' => 'into after', 'starts_at' => '2026-10-31 22:00:00', 'ends_at' => '2026-11-03 10:00:00']);
    task(['name' => 'spanning', 'starts_at' => '2026-09-01 00:00:00', 'ends_at' => '2026-12-31 00:00:00', 'all_day' => true]);
    task(['name' => 'no end', 'starts_at' => '2026-10-01 00:00:00']);
    task(['name' => 'ended before', 'starts_at' => '2026-09-20 09:00:00', 'ends_at' => '2026-09-30 23:59:59']);
    task(['name' => 'starts after', 'starts_at' => '2026-11-01 00:00:00', 'ends_at' => '2026-11-02 00:00:00']);
    task(['name' => 'no end before', 'starts_at' => '2026-09-30 23:00:00']);

    $response = $this->postJson('/api/admin/test-tasks/calendar', ['from' => '2026-10-01', 'to' => '2026-10-31'])->assertOk();
    $titles = array_column($response->json('payload.events'), 'title');
    sort($titles);

    expect($titles)->toBe(['from before', 'inside', 'into after', 'no end', 'spanning']);

    $inside = collect($response->json('payload.events'))->firstWhere('title', 'inside');
    expect($inside)->toMatchArray([
        'start' => '2026-10-10 09:00',
        'end' => '2026-10-10 10:30',
        'all_day' => false,
        'color' => null,
        'editable' => true,
    ]);
    $spanning = collect($response->json('payload.events'))->firstWhere('title', 'spanning');
    expect($spanning)->toMatchArray(['start' => '2026-09-01', 'end' => '2026-12-31', 'all_day' => true]);
});

it('applies the list filters to the calendar and validates the range', function (): void {
    task(['name' => 'high', 'priority' => 'high', 'starts_at' => '2026-10-10 09:00:00']);
    task(['name' => 'low', 'priority' => 'low', 'starts_at' => '2026-10-11 09:00:00']);

    $response = $this->postJson('/api/admin/test-tasks/calendar', [
        'from' => '2026-10-01', 'to' => '2026-10-31', 'filters' => ['priority' => 'high'],
    ])->assertOk();
    expect(array_column($response->json('payload.events'), 'title'))->toBe(['high'])
        ->and($response->json('payload.events.0.color'))->toBe('danger');

    $this->postJson('/api/admin/test-tasks/calendar', ['from' => '2026-10-31', 'to' => '2026-10-01'])->assertStatus(422);
    $this->postJson('/api/admin/test-tasks/calendar', ['from' => 'soon', 'to' => '2026-10-01'])->assertStatus(422);
    $this->postJson('/api/admin/test-tasks/calendar', ['from' => '2020-01-01', 'to' => '2026-10-01'])->assertStatus(422);
});

it('reads a date column as all-day events', function (): void {
    task(['name' => 'D', 'day' => '2026-10-05']);

    $response = $this->postJson('/api/admin/test-readonly-tasks/calendar', ['from' => '2026-10-05', 'to' => '2026-10-05'])->assertOk();

    expect($response->json('payload.events.0'))->toMatchArray(['start' => '2026-10-05', 'end' => null, 'all_day' => true]);
});

/* --------------------------------------------------------- calendarMove */

it('moves and stretches an event through the model', function (): void {
    $t = task(['starts_at' => '2026-10-10 09:00:00', 'ends_at' => '2026-10-10 10:00:00']);

    $response = $this->postJson('/api/admin/test-tasks/calendarMove', [
        'id' => $t->id, 'start' => '2026-10-12 11:00', 'end' => '2026-10-12 13:30',
    ])->assertOk();

    $fresh = $t->fresh();
    expect($fresh->starts_at->format('Y-m-d H:i:s'))->toBe('2026-10-12 11:00:00')
        ->and($fresh->ends_at->format('Y-m-d H:i:s'))->toBe('2026-10-12 13:30:00')
        ->and($response->json('payload.event'))->toMatchArray(['start' => '2026-10-12 11:00', 'end' => '2026-10-12 13:30'])
        ->and(TestTaskModel::$seen)->toBe(['updating:'.$t->id, 'updated:'.$t->id]);
});

it('stores an all-day move as whole days and keeps an end left out', function (): void {
    $t = task(['starts_at' => '2026-10-10 00:00:00', 'ends_at' => '2026-10-11 23:59:59', 'all_day' => true]);

    $this->postJson('/api/admin/test-tasks/calendarMove', ['id' => $t->id, 'start' => '2026-10-20', 'end' => '2026-10-21'])
        ->assertOk()
        ->assertJsonPath('payload.event.start', '2026-10-20')
        ->assertJsonPath('payload.event.end', '2026-10-21');
    expect($t->fresh()->ends_at->format('Y-m-d H:i:s'))->toBe('2026-10-21 23:59:59');

    $this->postJson('/api/admin/test-tasks/calendarMove', ['id' => $t->id, 'start' => '2026-10-19'])->assertOk();
    expect($t->fresh()->starts_at->format('Y-m-d'))->toBe('2026-10-19')
        ->and($t->fresh()->ends_at->format('Y-m-d'))->toBe('2026-10-21');
});

it('refuses invalid dates and an end before the start with 422', function (): void {
    $t = task(['starts_at' => '2026-10-10 09:00:00', 'ends_at' => '2026-10-10 10:00:00']);

    $this->postJson('/api/admin/test-tasks/calendarMove', ['id' => $t->id, 'start' => 'tomorrow-ish'])->assertStatus(422);
    $this->postJson('/api/admin/test-tasks/calendarMove', [
        'id' => $t->id, 'start' => '2026-10-12 11:00', 'end' => '2026-10-12 10:00',
    ])->assertStatus(422);

    expect($t->fresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-10-10 09:00')
        ->and(TestTaskModel::$seen)->toBe([]);
});

it('refuses a calendar move editableForRow forbids with 403', function (): void {
    $t = task(['starts_at' => '2026-10-10 09:00:00', 'locked' => true]);

    $this->postJson('/api/admin/test-tasks/calendarMove', ['id' => $t->id, 'start' => '2026-10-12 11:00'])
        ->assertForbidden()
        ->assertJsonPath('payload.errorKey', 'forbidden');
    expect($t->fresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-10-10 09:00');
});

it('answers 404 for a record that does not exist', function (): void {
    $this->postJson('/api/admin/test-tasks/boardMove', ['id' => 999, 'value' => 'done'])->assertNotFound();
    $this->postJson('/api/admin/test-tasks/calendarMove', ['id' => 999, 'start' => '2026-10-01'])->assertNotFound();
});
