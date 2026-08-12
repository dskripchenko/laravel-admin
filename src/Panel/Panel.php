<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Panel;

use Dskripchenko\LaravelAdmin\Http\AdminApi;

/**
 * A panel — an independent surface of the admin, on a par with Filament's
 * panels: its own mount path, guard, API version, middleware stacks and set of
 * plugins, so that the resources, screens, menu and permissions are all scoped
 * to it.
 *
 * The default `admin` panel is synthesized from the legacy top-level
 * config('admin.*') keys, so a single-panel host changes no configuration at
 * all.
 */
final class Panel
{
    /**
     * @param  list<string>  $excludePrefixes
     * @param  array<string, mixed>  $auth
     * @param  array<string, list<mixed>>  $middleware
     * @param  list<class-string>  $plugins
     * @param  class-string  $apiClass
     */
    public function __construct(
        public readonly string $id,
        public readonly string $path,
        public readonly string $apiPath,
        public readonly string $guard,
        public readonly array $auth,
        public readonly array $middleware,
        public readonly array $plugins,
        public readonly string $apiClass,
        public readonly array $excludePrefixes = [],
    ) {}

    public static function default(): self
    {
        /** @var array<string, mixed> $auth */
        $auth = (array) config('admin.auth', []);

        return new self(
            id: 'admin',
            path: (string) config('admin.path', 'admin'),
            apiPath: (string) config('admin.api_path', 'api/admin'),
            guard: (string) ($auth['guard'] ?? 'admin'),
            auth: $auth,
            middleware: (array) config('admin.middleware', []),
            plugins: array_values(array_filter((array) config('admin.plugins', []), 'is_string')),
            apiClass: AdminApi::class,
            excludePrefixes: [],
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $id, array $config): self
    {
        /** @var array<string, mixed> $auth */
        $auth = (array) ($config['auth'] ?? []);

        /** @var class-string $apiClass */
        $apiClass = (string) ($config['api'] ?? AdminApi::class);

        return new self(
            id: $id,
            path: (string) ($config['path'] ?? $id),
            // laravel-api's version equals the panel's id: /api/{id}/{controller}/{action}.
            apiPath: (string) ($config['api_path'] ?? 'api/'.$id),
            guard: (string) ($auth['guard'] ?? $id),
            auth: $auth,
            middleware: (array) ($config['middleware'] ?? []),
            plugins: array_values(array_filter((array) ($config['plugins'] ?? []), 'is_string')),
            apiClass: $apiClass,
            excludePrefixes: array_values(array_filter((array) ($config['exclude_prefixes'] ?? []), 'is_string')),
        );
    }

    /**
     * @return list<mixed>
     */
    public function shellMiddleware(): array
    {
        return (array) ($this->middleware['shell'] ?? []);
    }

    /**
     * @return list<mixed>
     */
    public function apiMiddleware(): array
    {
        return (array) ($this->middleware['api'] ?? []);
    }

    public function isRoot(): bool
    {
        return $this->path === '';
    }

    public function authProvider(): string
    {
        if ($this->id === 'admin') {
            return (string) config('admin.auth.provider', 'admin_users');
        }

        return (string) ($this->auth['provider'] ?? $this->id.'_users');
    }

    public function passwordBroker(): string
    {
        if ($this->id === 'admin') {
            return (string) config('admin.auth.password_broker', 'admin_users');
        }

        return (string) ($this->auth['password_broker'] ?? $this->id.'_users');
    }

    /**
     * @return class-string|string
     */
    public function authModel(): string
    {
        if ($this->id === 'admin') {
            return (string) config('admin.auth.model', \Dskripchenko\LaravelAdmin\Models\AdminUser::class);
        }

        return (string) ($this->auth['model'] ?? '');
    }
}
