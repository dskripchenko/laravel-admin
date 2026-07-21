<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Menu\MenuNode;
use Dskripchenko\LaravelAdmin\Panel\PanelApi;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Фикстуры фичи Panels (v1.8): вторая панель `client` на корне сайта
 * со своим guard'ом, ресурсом, плагином и API-версией.
 *
 * @internal
 */
final class TestPanelClientUser extends AuthUser
{
    protected $table = 'test_client_panel_users';

    protected $guarded = [];

    protected $hidden = ['password'];

    // AdminAccess ищет публичный hasAccess (shared-strategy контракт) —
    // тестовый клиентский пользователь имеет полный доступ в своей панели.
    public function hasAccess(string $permission): bool
    {
        return true;
    }
}

/**
 * @internal
 */
final class TestPanelProjectModel extends Model
{
    protected $table = 'test_panel_projects';

    protected $guarded = [];
}

/**
 * @internal
 */
final class TestPanelProjectResource extends Resource
{
    public static string $model = TestPanelProjectModel::class;

    public function fields(): array
    {
        return [
            Input::make('name')->required()->title('Название'),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id'),
            TableColumn::make('name'),
        ];
    }
}

/**
 * @internal
 */
final class TestPanelClientPlugin implements AdminPlugin
{
    public function name(): string
    {
        return 'test-client-panel';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function register(): void {}

    public function boot(Admin $admin): void
    {
        $admin->resources([TestPanelProjectResource::class]);
        $admin->permissions(ItemPermission::group('client.projects')
            ->addPermission('client.projects.view', 'Просмотр проектов'));
        $admin->menu()->add(MenuNode::resource('test-panel-projects')->label('Проекты'));
    }
}

/**
 * @internal
 */
final class TestPanelClientApi extends PanelApi {}
