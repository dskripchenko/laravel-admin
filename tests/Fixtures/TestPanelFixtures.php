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
 * The fixtures of the Panels feature (v1.8): a second panel, `client`, at the
 * site's root with a guard, a resource, a plugin and an API version of its own.
 *
 * @internal
 */
final class TestPanelClientUser extends AuthUser
{
    protected $table = 'test_client_panel_users';

    protected $guarded = [];

    protected $hidden = ['password'];

    // AdminAccess looks for a public hasAccess (the shared-strategy contract) —
    // the test client user has full access within their own panel.
    public function hasAccess(string $permission): bool
    {
        return true;
    }

    /**
     * The "the model closes the door itself" hook: here it goes by a flag in a
     * field, in real life it is the owner's state (a suspended client, an
     * expired subscription).
     */
    public function isDisabledForLogin(): bool
    {
        return (bool) $this->getAttribute('owner_suspended');
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
