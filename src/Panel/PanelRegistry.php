<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Panel;

use Dskripchenko\LaravelApi\Facades\ApiRequest;

/**
 * The registry of the panels, and the resolution of a request's "current" one.
 *
 * The current panel is decided, in order of precedence, by:
 *   1. an explicit setCurrent(), in tests and console scenarios;
 *   2. the request attribute `admin.panel`, set by the panel's shell route;
 *   3. the laravel-api version of the current request, which equals the
 *      panel's id;
 *   4. the default `admin` panel.
 */
final class PanelRegistry
{
    /** @var array<string, Panel>|null */
    private ?array $panels = null;

    private ?string $current = null;

    /**
     * @return array<string, Panel>
     */
    public function all(): array
    {
        if ($this->panels === null) {
            $panels = ['admin' => Panel::default()];
            /** @var array<array-key, mixed> $configured */
            $configured = (array) config('admin.panels', []);
            foreach ($configured as $id => $config) {
                if (! is_string($id) || $id === 'admin' || ! is_array($config)) {
                    continue;
                }
                $panels[$id] = Panel::fromConfig($id, $config);
            }
            $this->panels = $panels;
        }

        return $this->panels;
    }

    public function get(string $id): ?Panel
    {
        return $this->all()[$id] ?? null;
    }

    public function default(): Panel
    {
        return $this->all()['admin'];
    }

    public function has(string $id): bool
    {
        return isset($this->all()[$id]);
    }

    public function setCurrent(?string $id): void
    {
        $this->current = $id;
    }

    public function current(): Panel
    {
        if ($this->current !== null) {
            return $this->get($this->current) ?? $this->default();
        }

        // In the console the request is a stand-in built from the globals:
        // the attributes, the route and the API version are all empty, and the
        // resolution honestly falls through to the default. No separate
        // console branch is needed — and in HTTP tests runningInConsole() is
        // true, so such a check would break them.
        if (app()->bound('request')) {
            $request = request();

            $attr = $request->attributes->get('admin.panel');
            if (is_string($attr) && $this->has($attr)) {
                return $this->all()[$attr];
            }

            $route = $request->route();
            if ($route !== null) {
                $param = $route->parameter('adminPanel');
                if (is_string($param) && $this->has($param)) {
                    return $this->all()[$param];
                }
            }

            // laravel-api's facade annotates a string but can return null, so
            // we normalize to '' — no panel is ever named by an empty string.
            $version = (string) ApiRequest::getApiVersion();
            if ($version !== '' && $this->has($version)) {
                return $this->all()[$version];
            }
        }

        return $this->default();
    }

    /**
     * Clears the cached panels, for when the config changed during a test.
     */
    public function flush(): void
    {
        $this->panels = null;
        $this->current = null;
    }
}
