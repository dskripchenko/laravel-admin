<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Screen;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Support\Repository;
use Illuminate\Support\Str;

/**
 * The abstract screen — a controller, a view model and a command bar in one
 * class.
 *
 * The lifecycle of one request:
 *   1. `query(...$params)` assembles the state as a Repository.
 *   2. `name()`, `description()`, `permission()` and `commandBar()` give the meta.
 *   3. `layout()` returns the Layout objects describing the page.
 *   4. the command methods — any public method of the class, under any name
 *      but the reserved ones — are called through the controller's `runMethod`
 *      action, see docs/api/screens.md.
 *
 * Subclasses override the abstract `query()` and `layout()`. The reserved
 * names, which cannot serve as command methods, are: query, layout, name,
 * description, permission, commandBar, compile and slug.
 */
abstract class Screen
{
    /** @var list<string> The method names that cannot be used as commands. */
    private const RESERVED_METHODS = [
        'query',
        'layout',
        'name',
        'description',
        'permission',
        'commandBar',
        'compile',
        'slug',
        'reservedMethods',
        'isCallableMethod',
    ];

    /**
     * The screen's unique slug. By default it is the kebab-case of the class
     * basename without the `Screen` suffix. It doubles as the controller's
     * name in the admin API, where the URL is `/api/admin/{slug}/{action}`.
     */
    public static function slug(): string
    {
        $base = class_basename(static::class);
        if (str_ends_with($base, 'Screen')) {
            $base = substr($base, 0, -strlen('Screen'));
        }

        return Str::kebab($base);
    }

    public function name(): string
    {
        return class_basename(static::class);
    }

    public function description(): ?string
    {
        return null;
    }

    /**
     * The permission keys. null means authentication alone is enough; an
     * array means every listed permission is required.
     *
     * @return list<string>|string|null
     */
    public function permission(): array|string|null
    {
        return null;
    }

    /**
     * The buttons and links in the screen's header; empty by default.
     *
     * @return list<Action>
     */
    public function commandBar(): array
    {
        return [];
    }

    /**
     * Describes the screen's state — the data every layout and field sees.
     *
     * @return Repository|array<string, mixed>
     */
    abstract public function query(mixed ...$params): Repository|array;

    /**
     * Describes the page's structure.
     *
     * @return list<Layout>
     */
    abstract public function layout(): array;

    /**
     * Tells whether a public method may be called as a command, through the
     * runMethod action.
     *
     * It guards against calling query, layout, permission and the rest through
     * the API by accident.
     */
    final public function isCallableMethod(string $method): bool
    {
        if (in_array($method, self::RESERVED_METHODS, true)) {
            return false;
        }

        if (! method_exists($this, $method)) {
            return false;
        }

        $reflection = new \ReflectionMethod($this, $method);

        return $reflection->isPublic() && ! $reflection->isStatic();
    }

    /**
     * @return list<string>
     */
    public static function reservedMethods(): array
    {
        return self::RESERVED_METHODS;
    }

    /**
     * Compiles the screen into the snapshot the `state` action returns.
     * See docs/api/screens.md → `{slug}.state`.
     *
     * @return array{
     *     state: array<string, mixed>,
     *     name: string,
     *     description: ?string,
     *     layout: list<array<string, mixed>>,
     *     command_bar: list<array<string, mixed>>,
     *     permissions: list<string>,
     *     etag: string
     * }
     */
    public function compile(mixed ...$params): array
    {
        $stateRepo = $this->query(...$params);
        $state = $stateRepo instanceof Repository ? $stateRepo->toArray() : $stateRepo;

        $payload = [
            'state' => $state,
            'name' => \Dskripchenko\LaravelAdmin\I18n\Localize::string($this->name()),
            'description' => \Dskripchenko\LaravelAdmin\I18n\Localize::string($this->description()),
            'layout' => array_map(
                static fn (Layout $l): array => $l->toArray(),
                array_values(array_filter($this->layout(), static fn (Layout $l): bool => $l->isVisible())),
            ),
            'command_bar' => array_map(
                static fn (Action $a): array => $a->toArray(),
                array_values(array_filter($this->commandBar(), static fn (Action $a): bool => $a->isVisible())),
            ),
            'permissions' => self::normalizePermissions($this->permission()),
        ];

        $payload['etag'] = self::buildEtag([
            'state' => $payload['state'],
            'name' => $payload['name'],
            'description' => $payload['description'],
            'permissions' => $payload['permissions'],
        ]);

        return $payload;
    }

    /**
     * Computes the etag from the state-level data alone: state, name,
     * description and permissions.
     *
     * The layout and the command bar are NOT part of the etag — they contain
     * non-deterministic ids (random_bytes), and they only change when the
     * screen's code does, which Manifest::version() already tracks. On the
     * client the etag answers one question: has the state changed, should this
     * be refetched.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function buildEtag(array $payload): string
    {
        $serialized = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);

        return substr(hash('sha256', $serialized), 0, 16);
    }

    /**
     * @param  list<string>|string|null  $permission
     * @return list<string>
     */
    private static function normalizePermissions(array|string|null $permission): array
    {
        if ($permission === null) {
            return [];
        }
        if (is_string($permission)) {
            return [$permission];
        }

        return $permission;
    }
}
