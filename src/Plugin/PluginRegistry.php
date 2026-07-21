<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Plugin;

use Dskripchenko\LaravelAdmin\Admin;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use RuntimeException;

/**
 * Registry admin-плагинов.
 *
 * Двухфазный lifecycle:
 *   1. register($class) — добавляет FQCN в очередь.
 *   2. registerAll() — инстанцирует все, вызывает register() (раннее
 *      биндинг-время).
 *   3. bootAll($admin) — вызывает boot($admin) для каждого плагина.
 *
 * Дубликаты по name() запрещены — RuntimeException на повторе.
 */
final class PluginRegistry
{
    /** @var list<class-string<AdminPlugin>> */
    private array $classes = [];

    /** @var array<class-string<AdminPlugin>, string> class => panel id */
    private array $classPanels = [];

    /** @var array<string, AdminPlugin> name => instance */
    private array $instances = [];

    /** @var array<string, string> name => panel id */
    private array $instancePanels = [];

    private bool $registered = false;

    private bool $booted = false;

    public function __construct(private readonly Application $app) {}

    /**
     * @param  class-string<AdminPlugin>  $class
     */
    public function add(string $class, string $panel = 'admin'): void
    {
        if (! is_subclass_of($class, AdminPlugin::class)) {
            throw new InvalidArgumentException(
                "{$class} must implement ".AdminPlugin::class,
            );
        }
        if (! in_array($class, $this->classes, true)) {
            $this->classes[] = $class;
            $this->classPanels[$class] = $panel;
        }
    }

    /**
     * @param  list<class-string<AdminPlugin>>  $classes
     */
    public function addMany(array $classes, string $panel = 'admin'): void
    {
        foreach ($classes as $class) {
            $this->add($class, $panel);
        }
    }

    /**
     * Инстанцирует плагины и вызывает register() в каждом.
     */
    public function registerAll(): void
    {
        if ($this->registered) {
            return;
        }

        foreach ($this->classes as $class) {
            /** @var AdminPlugin $instance */
            $instance = $this->app->make($class);
            $name = $instance->name();
            if (isset($this->instances[$name])) {
                $existing = $this->instances[$name]::class;
                throw new RuntimeException(
                    "Plugin name `{$name}` collision: {$existing} vs {$class}",
                );
            }
            $this->instances[$name] = $instance;
            $this->instancePanels[$name] = $this->classPanels[$class] ?? 'admin';
            $instance->register();
        }
        $this->registered = true;
    }

    /**
     * Вызывает boot() в каждом плагине, прокидывая Admin manager.
     */
    public function bootAll(Admin $admin): void
    {
        if ($this->booted) {
            return;
        }
        if (! $this->registered) {
            $this->registerAll();
        }

        foreach ($this->instances as $name => $instance) {
            // Регистрации из boot'а тегируются панелью плагина (v1.8 Panels).
            $admin->setRegistrationPanel($this->instancePanels[$name] ?? 'admin');
            $instance->boot($admin);
        }
        $admin->setRegistrationPanel('admin');
        $this->booted = true;
    }

    /**
     * Без аргумента — все (BC); с панелью — только её плагины.
     *
     * @return array<string, AdminPlugin>
     */
    public function instances(?string $panel = null): array
    {
        if ($panel === null) {
            return $this->instances;
        }

        return array_filter(
            $this->instances,
            fn (string $name): bool => ($this->instancePanels[$name] ?? 'admin') === $panel,
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @return list<array{name: string, version: string}>
     */
    public function describe(?string $panel = null): array
    {
        return array_values(array_map(
            static fn (AdminPlugin $p): array => [
                'name' => $p->name(),
                'version' => $p->version(),
            ],
            $this->instances($panel),
        ));
    }

    public function clear(): void
    {
        $this->classes = [];
        $this->classPanels = [];
        $this->instances = [];
        $this->instancePanels = [];
        $this->registered = false;
        $this->booted = false;
    }
}
