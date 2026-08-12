<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Plugin;

use Dskripchenko\LaravelAdmin\Admin;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use RuntimeException;

/**
 * The registry of the admin plugins.
 *
 * Its lifecycle has three steps:
 *   1. register($class) queues an FQCN.
 *   2. registerAll() instantiates them all and calls register(), early enough
 *      for bindings.
 *   3. bootAll($admin) calls boot($admin) on each of them.
 *
 * Duplicate name() values are forbidden: a repeat throws a RuntimeException.
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
     * Instantiates the plugins and calls register() on each of them.
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
     * Calls boot() on every plugin, passing the Admin manager in.
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
            // What boot() registers is tagged with the plugin's panel.
            $admin->setRegistrationPanel($this->instancePanels[$name] ?? 'admin');
            $instance->boot($admin);
        }
        $admin->setRegistrationPanel('admin');
        $this->booted = true;
    }

    /**
     * Without an argument: all of them; with a panel: that panel's plugins alone.
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
