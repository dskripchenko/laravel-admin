<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Widget\BpmnWidget;
use Dskripchenko\LaravelAdmin\Widget\CalendarWidget;
use Dskripchenko\LaravelAdmin\Widget\DashboardLayout;
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelAdmin\Widget\DiagramWidget;
use Dskripchenko\LaravelAdmin\Widget\KanbanWidget;

/**
 * The document widgets of a dashboard — DiagramWidget, BpmnWidget,
 * KanbanWidget and CalendarWidget: their data, the resource mode's
 * permission checks, and the user-added ones in the saved layout.
 */
beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestTaskResource::class);
    $rr->add(TestReadonlyTaskResource::class);
    $rr->add(TestUserResource::class);
    AdminApi::clearCache();

    app(ScreenRegistry::class)->clear();
    app(ScreenRegistry::class)->add(DocumentsDashboard::class);

    $this->actAs = function (array $permissions): AdminUser {
        $user = AdminUser::create([
            'name' => 'U', 'email' => 'u-'.uniqid().'@example.com', 'password' => 'secret',
        ]);
        $user->assignRole(Role::create(['name' => 'R', 'slug' => 'r-'.uniqid(), 'permissions' => $permissions]));
        $this->actingAs($user->refresh(), 'admin');

        return $user;
    };
});

/* ---------------------------------------------------------------- data */

it('describes a diagram: the source and the notation', function (): void {
    ($this->actAs)(['*']);
    $widget = DiagramWidget::make()->title('Flow')->content(fn () => "flowchart LR\nA-->B")->syntax('mermaid');

    expect($widget->toArray())->toMatchArray([
        'kind' => 'widget',
        'slug' => 'diagram',
        'type' => 'diagram',
        'title' => 'Flow',
        'rowSpan' => 3,
        'data' => ['content' => "flowchart LR\nA-->B", 'syntax' => 'mermaid'],
    ]);
    expect(DiagramWidget::make()->data())->toBe(['content' => '', 'syntax' => null]);
    expect(fn () => DiagramWidget::make()->syntax('visio'))->toThrow(InvalidArgumentException::class);
});

it('describes a BPMN or DMN model: inline XML or an address', function (): void {
    $bpmn = BpmnWidget::make()->xml('<definitions/>')->highlight(['Task_1'])->showDiagram('Process_1');
    expect($bpmn->widgetType())->toBe('bpmn')
        ->and($bpmn->data())->toBe([
            'notation' => 'bpmn',
            'content' => '<definitions/>',
            'url' => null,
            'highlight' => ['Task_1'],
            'decision' => null,
            'diagram' => 'Process_1',
        ]);

    $dmn = BpmnWidget::make()->dmn()->url('/files/rules.dmn')->decision('Decision_1');
    expect($dmn->data())->toMatchArray([
        'notation' => 'dmn',
        'content' => null,
        'url' => '/files/rules.dmn',
        'decision' => 'Decision_1',
    ]);

    expect(fn () => BpmnWidget::make()->notation('epc'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => BpmnWidget::make()->url('javascript:alert(1)'))->toThrow(InvalidArgumentException::class);
});

it('describes a kanban over a resource, editable only for a user who may update', function (): void {
    ($this->actAs)(['admin.test-tasks.view']);
    $widget = KanbanWidget::make()->title('Tasks')
        ->resource(TestTaskResource::class)
        ->filters(['priority' => 'high'])
        ->search('bug')
        ->editable();

    $array = $widget->toArray();
    expect($array['type'])->toBe('kanban')
        ->and($array['size'])->toBe(12)
        ->and($array['rowSpan'])->toBe(3)
        ->and($array['data'])->toMatchArray([
            'mode' => 'resource',
            'resource' => 'test-tasks',
            'search' => 'bug',
            'editable' => false,
            'content' => null,
        ])
        ->and((array) $array['data']['filters'])->toBe(['priority' => 'high']);

    ($this->actAs)(['admin.test-tasks.view', 'admin.test-tasks.update']);
    expect($widget->data()['editable'])->toBeTrue()
        ->and(KanbanWidget::make()->resource(TestTaskResource::class)->data()['editable'])->toBeFalse();
});

it('describes a kanban and a calendar written as markup', function (): void {
    expect(KanbanWidget::make()->markup(fn () => "## Now\n- A")->data())->toBe([
        'mode' => 'markup',
        'resource' => null,
        'content' => "## Now\n- A",
    ]);

    $calendar = CalendarWidget::make()->markup('every mon Planning')
        ->initialView('agenda')->date('2026-11-02')->views(['agenda', 'week']);
    expect($calendar->widgetType())->toBe('calendar')
        ->and($calendar->data())->toBe([
            'mode' => 'markup',
            'resource' => null,
            'content' => 'every mon Planning',
            'initialView' => 'agenda',
            'date' => '2026-11-02',
            'views' => ['agenda', 'week'],
        ]);

    expect(fn () => CalendarWidget::make()->initialView('fortnight'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CalendarWidget::make()->date('02.11.2026'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => KanbanWidget::make()->resource(stdClass::class))->toThrow(InvalidArgumentException::class);
});

it('describes a calendar over a resource with its first view', function (): void {
    ($this->actAs)(['*']);
    $data = CalendarWidget::make()->resource(TestTaskResource::class)->initialView('week')->data();

    expect($data)->toMatchArray([
        'mode' => 'resource',
        'resource' => 'test-tasks',
        'initialView' => 'week',
        'search' => null,
    ]);
});

/* ---------------------------------------------------------------- permissions */

it('hides a resource widget from a user who may not view the resource', function (): void {
    ($this->actAs)(['admin.test-tasks.view']);
    $slugs = fn (): array => array_column((new DocumentsDashboard)->compileWidgets(), 'slug');

    // The users resource is not visible, and has no kanban view either.
    expect($slugs())->toBe(['diagram', 'tasks-board', 'tasks-calendar', 'roadmap']);

    ($this->actAs)(['admin.other.view']);
    expect($slugs())->toBe(['diagram', 'roadmap']);

    // The manifest leaves them out too.
    $dashboards = $this->getJson('/api/admin/system/manifest')->assertOk()->json('payload.dashboards');
    $widgets = collect($dashboards)->firstWhere('slug', 'documents-dashboard')['widgets'];
    expect(array_column($widgets, 'slug'))->toBe(['diagram', 'roadmap']);
});

it('hides a resource widget whose resource lacks the view or is not registered', function (): void {
    ($this->actAs)(['*']);
    // TestUserResource declares neither a kanban nor a calendar.
    expect(KanbanWidget::make()->resource(TestUserResource::class)->isVisible())->toBeFalse()
        ->and(KanbanWidget::make()->resource(TestTaskResource::class)->isVisible())->toBeTrue();

    app(ResourceRegistry::class)->clear();
    expect(KanbanWidget::make()->resource(TestTaskResource::class)->isVisible())->toBeFalse()
        ->and(KanbanWidget::make()->markup('## A')->isVisible())->toBeTrue();
});

/* ---------------------------------------------------------------- user-added */

it('saves and reads a user-added widget of each new type', function (): void {
    ($this->actAs)(['*']);
    $items = [
        ['slug' => 'custom.diagram.1', 'size' => 6, 'position' => 0, 'type' => 'diagram',
            'config' => ['title' => 'Flow', 'syntax' => null, 'content' => 'graph TD; A-->B']],
        ['slug' => 'custom.bpmn.2', 'size' => 6, 'position' => 1, 'type' => 'bpmn',
            'config' => ['title' => 'Process', 'notation' => 'dmn', 'url' => '/files/rules.dmn']],
        ['slug' => 'custom.kanban.3', 'size' => 12, 'position' => 2, 'type' => 'kanban',
            'config' => ['title' => 'Tasks', 'mode' => 'resource', 'resource' => 'test-tasks', 'search' => null, 'editable' => true]],
        ['slug' => 'custom.kanban.4', 'size' => 12, 'position' => 3, 'type' => 'kanban',
            'config' => ['title' => 'Roadmap', 'mode' => 'markup', 'content' => "## Now\n- A"]],
        ['slug' => 'custom.calendar.5', 'size' => 6, 'position' => 4, 'type' => 'calendar',
            'config' => ['title' => 'Week', 'mode' => 'resource', 'resource' => 'test-tasks', 'search' => null, 'editable' => false, 'initialView' => 'agenda']],
    ];

    $this->postJson('/api/admin/dashboard/save', ['key' => 'documents-dashboard', 'widgets' => $items])
        ->assertOk()
        ->assertJsonPath('payload.widgets', $items);

    $this->getJson('/api/admin/dashboard/get?key=documents-dashboard')
        ->assertOk()
        ->assertJsonPath('payload.layout', $items);
});

/**
 * @return array<string, array<string, mixed>>
 */
function resourceWidgetItems(): array
{
    return [
        'allowed' => ['slug' => 'custom.kanban.1', 'type' => 'kanban', 'size' => 12,
            'config' => ['title' => 'Tasks', 'mode' => 'resource', 'resource' => 'test-tasks']],
        'readonly' => ['slug' => 'custom.calendar.2', 'type' => 'calendar', 'size' => 6,
            'config' => ['title' => 'Readonly', 'mode' => 'resource', 'resource' => 'test-readonly-tasks']],
        'noView' => ['slug' => 'custom.kanban.3', 'type' => 'kanban', 'size' => 6,
            'config' => ['title' => 'Users', 'mode' => 'resource', 'resource' => 'test-users']],
        'markup' => ['slug' => 'custom.kanban.4', 'type' => 'kanban', 'size' => 6,
            'config' => ['title' => 'Board', 'mode' => 'markup', 'content' => '## A']],
    ];
}

it('drops on save a user-added resource widget the user may not view', function (): void {
    ($this->actAs)(['admin.test-tasks.view', 'admin.test-users.view']);

    $this->postJson('/api/admin/dashboard/save', [
        'key' => 'documents-dashboard',
        'widgets' => array_values(resourceWidgetItems()),
    ])->assertOk();

    // test-readonly-tasks is not viewable; test-users has no kanban view.
    expect(array_column(DashboardLayout::query()->firstOrFail()->widgets, 'slug'))
        ->toBe(['custom.kanban.1', 'custom.kanban.4']);
});

it('drops on read a user-added resource widget the user may no longer view', function (): void {
    $user = ($this->actAs)(['admin.test-readonly-tasks.view']);
    // Saved before the permissions changed.
    DashboardLayout::create([
        'dashboard_key' => 'documents-dashboard',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => $user->id,
        'widgets' => array_values(resourceWidgetItems()),
    ]);

    $layout = $this->getJson('/api/admin/dashboard/get?key=documents-dashboard')->assertOk()->json('payload.layout');
    expect(array_column($layout, 'slug'))->toBe(['custom.calendar.2', 'custom.kanban.4']);
});

it('keeps the user-added widgets of a layout saved before the descriptions', function (): void {
    ($this->actAs)(['*']);
    // What the old WidgetConfigDialog saved: the same shapes the descriptions build.
    $legacy = [
        ['slug' => 'custom.markdown.1', 'size' => 6, 'position' => 0, 'type' => 'markdown', 'config' => ['title' => 'Notes', 'content' => '## Hi']],
        ['slug' => 'custom.stat.2', 'size' => 3, 'position' => 1, 'type' => 'stat', 'config' => ['title' => 'Users', 'stats' => [['label' => 'TOTAL', 'value' => '42']]]],
        ['slug' => 'custom.gauge.3', 'size' => 3, 'position' => 2, 'type' => 'gauge', 'config' => ['title' => 'Load', 'value' => 73, 'min' => 0, 'max' => 100]],
        ['slug' => 'custom.donut-chart.4', 'type' => 'donut-chart', 'size' => 6, 'position' => 3, 'config' => ['title' => 'Mix', 'labels' => ['A'], 'datasets' => [['label' => 'Mix', 'data' => [1]]], 'type' => 'doughnut']],
        ['slug' => 'custom.recent_list.5', 'type' => 'recent_list', 'size' => 6, 'position' => 4, 'config' => ['title' => 'Latest', 'resource' => 'articles', 'limit' => 5]],
    ];
    DashboardLayout::create([
        'dashboard_key' => 'documents-dashboard',
        'owner_type' => auth('admin')->user()->getMorphClass(),
        'owner_id' => auth('admin')->id(),
        'widgets' => $legacy,
    ]);

    $this->getJson('/api/admin/dashboard/get?key=documents-dashboard')
        ->assertOk()
        ->assertJsonPath('payload.layout', $legacy);
});

/**
 * @internal
 */
final class DocumentsDashboard extends DashboardScreen
{
    public function name(): string
    {
        return 'Documents';
    }

    public function widgets(): array
    {
        return [
            DiagramWidget::make()->content('graph TD; A-->B'),
            KanbanWidget::make()->withSlug('tasks-board')->resource(TestTaskResource::class),
            CalendarWidget::make()->withSlug('tasks-calendar')->resource(TestTaskResource::class)->initialView('week'),
            KanbanWidget::make()->withSlug('users-board')->resource(TestUserResource::class),
            KanbanWidget::make()->withSlug('roadmap')->markup("## Now\n- A"),
        ];
    }
}
