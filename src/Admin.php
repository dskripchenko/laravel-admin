<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin;

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
    /** @var class-string[] */
    private array $resources = [];

    /** @var class-string[] */
    private array $widgets = [];

    /** @var class-string[] */
    private array $plugins = [];

    public function __construct(
        private readonly Application $app,
        private readonly ScreenRegistry $screens,
    ) {}

    /**
     * Регистрирует Screen-класс. Можно передать массив — будут зарегистрированы все.
     *
     * @param  class-string<Screen>|list<class-string<Screen>>  $class
     */
    public function screen(string|array $class): self
    {
        if (is_array($class)) {
            $this->screens->addMany($class);
        } else {
            $this->screens->add($class);
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
     * @param  class-string[]  $classes
     */
    public function resources(array $classes): self
    {
        $this->resources = array_unique([...$this->resources, ...$classes]);

        return $this;
    }

    /**
     * @return class-string[]
     */
    public function getResources(): array
    {
        return $this->resources;
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
        $this->widgets = array_unique([...$this->widgets, ...$classes]);

        return $this;
    }

    /**
     * @return class-string[]
     */
    public function getWidgets(): array
    {
        return $this->widgets;
    }

    public function version(): string
    {
        return '0.1.0-dev';
    }
}
