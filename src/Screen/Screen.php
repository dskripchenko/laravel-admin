<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Screen;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Support\Repository;
use Illuminate\Support\Str;

/**
 * Абстрактный Screen — controller + view-model + commandBar в одном классе.
 *
 * Lifecycle одного запроса:
 *   1. `query(...$params)` — собирает state как Repository.
 *   2. `name()` / `description()` / `permission()` / `commandBar()` — meta.
 *   3. `layout()` — массив Layout-объектов, описывающих страницу.
 *   4. command-методы (любые public-методы class'а с любыми именами,
 *      кроме зарезервированных) — вызываются через `runMethod` action
 *      контроллера (см. docs/api/screens.md).
 *
 * Подклассы переопределяют abstract `query()` и `layout()`. Зарезервированные
 * имена (нельзя использовать как command-method): query, layout, name,
 * description, permission, commandBar, compile, slug.
 */
abstract class Screen
{
    /** @var list<string> Имена методов, недоступных как command. */
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
     * Уникальный slug Screen'а. По умолчанию kebab-case от class-basename
     * без суффикса `Screen`. Используется как имя controller'а в admin-API
     * (URL: `/api/admin/{slug}/{action}`).
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
     * Список permission-ключей. null = только аутентификация. Массив = все
     * перечисленные permissions требуются для доступа.
     *
     * @return list<string>|string|null
     */
    public function permission(): array|string|null
    {
        return null;
    }

    /**
     * Кнопки/ссылки в шапке Screen'а. По умолчанию пусто.
     *
     * @return list<Action>
     */
    public function commandBar(): array
    {
        return [];
    }

    /**
     * Описывает state Screen'а — данные, которые видны во всех Layout/Field.
     *
     * @return Repository|array<string, mixed>
     */
    abstract public function query(mixed ...$params): Repository|array;

    /**
     * Описывает структуру страницы.
     *
     * @return list<Layout>
     */
    abstract public function layout(): array;

    /**
     * Можно ли вызывать публичный метод как command (через runMethod action).
     *
     * Защищает от случайного вызова query/layout/permission/etc. через API.
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
     * Скомпилировать Screen в snapshot для отдачи через `state` action.
     * См. docs/api/screens.md → `{slug}.state`.
     *
     * @return array{
     *     state: array<string, mixed>,
     *     name: string,
     *     description: ?string,
     *     layout: list<array<string, mixed>>,
     *     command_bar: list<array<string, mixed>>,
     *     permissions: list<string>
     * }
     */
    public function compile(mixed ...$params): array
    {
        $stateRepo = $this->query(...$params);
        $state = $stateRepo instanceof Repository ? $stateRepo->toArray() : $stateRepo;

        return [
            'state' => $state,
            'name' => $this->name(),
            'description' => $this->description(),
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
