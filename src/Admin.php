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
 * The manager — the entry point of every public API of the package.
 *
 * It is reached through the Admin:: facade or app(Admin::class).
 *
 * For example:
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
     * The panel the registration methods write into. PluginRegistry sets it
     * before booting each panel's plugins; for a single-panel host it is always
     * 'admin'.
     */
    private string $registrationPanel = 'admin';

    public function __construct(
        private readonly Application $app,
        private readonly ScreenRegistry $screens,
        private readonly ResourceRegistry $resourceRegistry,
        private readonly PermissionRegistry $permissions,
    ) {}

    /**
     * Registers groups of permissions.
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
     * Sets the panel context of the registration. Every registration call
     * that follows — resources, screen, menu, widgets, permissions — is tagged
     * with this panel.
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
     * Registers a screen class, or an array of them.
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
     * Resolves a screen instance by slug, through the container.
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
     * Registers a list of resource classes.
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
     * Resolves a resource instance by slug, through the container.
     */
    public function resolveResource(string $slug): ?ResourceBase
    {
        return $this->resourceRegistry->resolve($slug);
    }

    /**
     * Registers an AdminPlugin.
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
     * Registers widget classes.
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
     * Without an argument: the widgets of every panel; with one: only that panel's.
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
     * Access to MenuRegistry — the fluent API for building a hierarchical menu.
     *
     *   $admin->menu()->add(MenuNode::make('shop', 'Shop')->children([...]));
     *   $admin->menu()->under('shop', [MenuNode::resource('products')]);
     */
    public function menu(): MenuRegistry
    {
        return $this->app->make(MenuRegistry::class);
    }
}
