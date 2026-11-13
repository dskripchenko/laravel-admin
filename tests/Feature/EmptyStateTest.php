<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Link;
use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Support\EmptyState;
use Illuminate\Support\Facades\Route;

/**
 * The states of 1.74: what an empty list says, the key a 403 names, the code
 * of a request.
 */
function emptyStateActingAs(array $permissions): void
{
    $user = AdminUser::create(['name' => 'U', 'email' => 'u-'.uniqid().'@example.com', 'password' => 'secret']);
    $user->assignRole(Role::create(['name' => 'R', 'slug' => 'r-'.uniqid(), 'permissions' => $permissions]));
    test()->actingAs($user->refresh(), 'admin');
}

it('serializes an empty state with a create caption, an action or none', function (): void {
    expect(EmptyState::make('Городов пока нет', 'Города используются в карточках офисов.', 'Добавить город')->toArray())
        ->toMatchArray([
            'title' => 'Городов пока нет',
            'description' => 'Города используются в карточках офисов.',
            'action' => ['create' => 'Добавить город'],
        ]);
    expect(EmptyState::make('Пусто')->toArray()['action'])->toBeNull();
    expect(EmptyState::make('Пусто', null, false)->toArray()['action'])->toBeFalse();

    $link = EmptyState::make('Новых звонков нет', null, Link::make('Открыть «В работе»')->withName('open-work')->href('/admin/x'))->toArray();
    expect($link['action'])->toMatchArray(['name' => 'open-work', 'type' => 'link'])
        ->and($link['action']['attributes']['href'])->toBe('/admin/x');

    expect(EmptyState::make()->noResults('Ничего', 'Сбросьте фильтры')->toArray())
        ->toMatchArray(['noResultsTitle' => 'Ничего', 'noResultsDescription' => 'Сбросьте фильтры']);
});

it('drops an action the user may not run', function (): void {
    emptyStateActingAs(['admin.other.view']);
    $state = EmptyState::make('x', null, Link::make('Go')->href('/x')->permission('admin.secret.view'))->toArray();

    expect($state['action'])->toBeFalse();
});

it('Layout::resourceIndex()->emptyState() carries the state to the list', function (): void {
    $arr = Layout::resourceIndex(TestTreeNodeResource::class)
        ->emptyState('Новых звонков нет', 'Счётчик в меню обнулён.', false)
        ->toArray();

    expect($arr['emptyState'])->toMatchArray([
        'title' => 'Новых звонков нет',
        'description' => 'Счётчик в меню обнулён.',
        'action' => false,
    ]);

    $own = EmptyState::make('A');
    expect(Layout::resourceIndex(TestTreeNodeResource::class)->emptyState($own)->toArray()['emptyState']['title'])->toBe('A');
});

it('sends Resource::emptyState() in the resource manifest', function (): void {
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(EmptyStateCitiesResource::class);
    AdminApi::clearCache();
    emptyStateActingAs(['*']);

    $payload = $this->getJson('/api/admin/empty-state-cities/meta')->assertOk()->json('payload');

    expect($payload['empty_state'])->toMatchArray([
        'title' => 'Городов пока нет',
        'action' => ['create' => 'Добавить город'],
    ]);
});

it('names the missing permission in a 403', function (): void {
    Route::middleware(['web'])->get('/test/empty-state-forbidden', fn () => response()->json(['ok' => true]))
        ->middleware(AdminAccess::class.':admin.audit.view');
    emptyStateActingAs(['admin.other.view']);

    $response = $this->getJson('/test/empty-state-forbidden')->assertStatus(403);

    expect($response->json('payload.permission'))->toBe('admin.audit.view');
});

it('answers with the request code the panel sent, or a new one', function (): void {
    emptyStateActingAs(['*']);

    $this->withHeaders(['X-Request-Id' => '7f3a-91c2'])->getJson('/api/admin/system/menu')
        ->assertHeader('X-Request-Id', '7f3a-91c2');

    $generated = $this->withHeaders(['X-Request-Id' => 'bad id <script>'])->getJson('/api/admin/system/menu')
        ->headers->get('X-Request-Id');
    expect($generated)->toMatch('/^[a-z0-9]{4}-[a-z0-9]{4}$/');
});

final class EmptyStateCitiesResource extends Dskripchenko\LaravelAdmin\Resource\Resource
{
    public static string $model = TestResourceUserModel::class;

    public static function slug(): string
    {
        return 'empty-state-cities';
    }

    public static function permission(): string
    {
        return 'admin.empty-state-cities';
    }

    public function fields(): array
    {
        return [Dskripchenko\LaravelAdmin\Field\Input::make('name')];
    }

    public function emptyState(): ?EmptyState
    {
        return EmptyState::make('Городов пока нет', 'Города используются в карточках офисов.', 'Добавить город');
    }
}
