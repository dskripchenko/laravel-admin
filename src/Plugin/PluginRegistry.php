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

    /** @var array<string, AdminPlugin> name => instance */
    private array $instances = [];

    private bool $registered = false;

    private bool $booted = false;

    public function __construct(private readonly Application $app) {}

    /**
     * @param  class-string<AdminPlugin>  $class
     */
    public function add(string $class): void
    {
        if (! is_subclass_of($class, AdminPlugin::class)) {
            throw new InvalidArgumentException(
                "{$class} must implement ".AdminPlugin::class,
            );
        }
        if (! in_array($class, $this->classes, true)) {
            $this->classes[] = $class;
        }
    }

    /**
     * @param  list<class-string<AdminPlugin>>  $classes
     */
    public function addMany(array $classes): void
    {
        foreach ($classes as $class) {
            $this->add($class);
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

        foreach ($this->instances as $instance) {
            $instance->boot($admin);
        }
        $this->booted = true;
    }

    /**
     * @return array<string, AdminPlugin>
     */
    public function instances(): array
    {
        return $this->instances;
    }

    /**
     * @return list<array{name: string, version: string}>
     */
    public function describe(): array
    {
        return array_values(array_map(
            static fn (AdminPlugin $p): array => [
                'name' => $p->name(),
                'version' => $p->version(),
            ],
            $this->instances,
        ));
    }

    public function clear(): void
    {
        $this->classes = [];
        $this->instances = [];
        $this->registered = false;
        $this->booted = false;
    }
}
