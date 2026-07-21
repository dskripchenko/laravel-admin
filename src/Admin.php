<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin;

use Dskripchenko\LaravelAdmin\Menu\MenuRegistry;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Permission\PermissionRegistry;
use Dskripchenko\LaravelAdmin\Resource\Resource as ResourceBase;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Illuminate\Contracts\Foundation\Application;

/**
 * Manager — точка входа всех публичных API пакета.
 *
 * Доступен через фасад Admin:: либо app(Admin::class).
 * На фазе скаффолда содержит только заготовки методов для основных
 * регистрационных API. По мере реализации фаз методы наполняются.
 *
 * Примеры (после полной реализации):
 *   Admin::resources([UserResource::class]);
 *   Admin::screen('dashboard', DashboardScreen::class);
 *   Admin::permissions([...]);
 *   Admin::menu([...]);
 *   Admin::plugin(MyPlugin::class);
 *   Admin::widgets([...]);
 *   Admin::healthChecks([...]);
 */
final class Admin
{
    /** @var array<string, class-string[]> panel id => widget classes */
    private array $widgets = [];

    /** @var class-string[] */
    private array $plugins = [];

    /**
     * Панель, в которую пишут registration-методы. PluginRegistry ставит её
     * перед boot'ом плагинов каждой панели; для однопанельных хостов всегда
     * 'admin' (BC).
     */
    private string $registrationPanel = 'admin';

    public function __construct(
        private readonly Application $app,
        private readonly ScreenRegistry $screens,
        private readonly ResourceRegistry $resourceRegistry,
        private readonly PermissionRegistry $permissions,
    ) {}

    /**
     * Регистрирует группы permissions.
     *
     * @param  ItemPermission|list<ItemPermission>  $items
     */
    public function permissions(ItemPermission|array $items): self
    {
        if ($items instanceof ItemPermission) {
            $this->permissions->add($items, $this->registrationPanel);
        } else {
            $this->permissions->addMany($items, $this->registrationPanel);
        }

        return $this;
    }

    /**
     * Панельный контекст регистрации (v1.8 Panels). Все последующие
     * registration-вызовы (resources/screen/menu/widgets/permissions)
     * тегируются этой панелью.
     */
    public function setRegistrationPanel(string $panel): self
    {
        $this->registrationPanel = $panel;
        $this->app->make(MenuRegistry::class)->setActivePanel($panel);

        return $this;
    }

    public function registrationPanel(): string
    {
        return $this->registrationPanel;
    }

    public function getPermissionRegistry(): PermissionRegistry
    {
        return $this->permissions;
    }

    /**
     * Регистрирует Screen-класс. Можно передать массив — будут зарегистрированы все.
     *
     * @param  class-string<Screen>|list<class-string<Screen>>  $class
     */
    public function screen(string|array $class): self
    {
        if (is_array($class)) {
            $this->screens->addMany($class, $this->registrationPanel);
        } else {
            $this->screens->add($class, $this->registrationPanel);
        }

        return $this;
    }

    /**
     * @return array<string, class-string<Screen>>
     */
    public function getScreens(): array
    {
        return $this->screens->all();
    }

    /**
     * Resolve Screen-instance по slug'у через контейнер.
     */
    public function resolveScreen(string $slug): ?Screen
    {
        $class = $this->screens->get($slug);
        if ($class === null) {
            return null;
        }

        /** @var Screen $instance */
        $instance = $this->app->make($class);

        return $instance;
    }

    /**
     * Регистрирует список Resource-классов.
     *
     * @param  list<class-string<ResourceBase>>  $classes
     */
    public function resources(array $classes): self
    {
        $this->resourceRegistry->addMany($classes, $this->registrationPanel);

        return $this;
    }

    /**
     * @return array<string, class-string<ResourceBase>>
     */
    public function getResources(): array
    {
        return $this->resourceRegistry->all();
    }

    /**
     * Resolve Resource-instance по slug'у через контейнер.
     */
    public function resolveResource(string $slug): ?ResourceBase
    {
        return $this->resourceRegistry->resolve($slug);
    }

    /**
     * Регистрирует AdminPlugin.
     *
     * @param  class-string  $class
     */
    public function plugin(string $class): self
    {
        $this->plugins[] = $class;

        return $this;
    }

    /**
     * @return class-string[]
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * Регистрирует Widget-классы.
     *
     * @param  class-string[]  $classes
     */
    public function widgets(array $classes): self
    {
        $panel = $this->registrationPanel;
        $this->widgets[$panel] = array_unique([...($this->widgets[$panel] ?? []), ...$classes]);

        return $this;
    }

    /**
     * Без аргумента — виджеты всех панелей (BC); с панелью — только её.
     *
     * @return class-string[]
     */
    public function getWidgets(?string $panel = null): array
    {
        if ($panel !== null) {
            return $this->widgets[$panel] ?? [];
        }

        return array_values(array_unique(array_merge(...array_values($this->widgets) ?: [[]])));
    }

    public function version(): string
    {
        return '0.1.0-dev';
    }

    /**
     * Доступ к MenuRegistry — fluent API для построения иерархического меню.
     *
     *   $admin->menu()->add(MenuNode::make('shop', 'Магазин')->children([...]));
     *   $admin->menu()->under('shop', [MenuNode::resource('products')]);
     */
    public function menu(): MenuRegistry
    {
        return $this->app->make(MenuRegistry::class);
    }
}
