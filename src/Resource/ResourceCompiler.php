<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource;

/**
 * Компилирует ResourceRegistry в массив `controllers` для AdminApi::getMethods().
 *
 * Каждый зарегистрированный Resource превращается в запись:
 *
 *     {slug} => [
 *         'controller' => ResourceController::class,
 *         'actions' => [
 *             'meta'   => ['method' => ['get']],
 *             'search' => ['method' => ['post']],
 *             'read'   => ['method' => ['get']],
 *             'create' => ['method' => ['post']],
 *             'update' => ['method' => ['post']],
 *             'delete' => ['method' => ['post']],
 *         ],
 *     ]
 *
 * Все Resource'ы используют ОДИН и тот же FQCN ResourceController. Внутри
 * controller через `ApiRequest::getApiControllerKey()` определяет, какой
 * именно Resource обслужить.
 *
 * Используется в AdminApi::getMethods() — на каждый запрос вызывается
 * заново (laravel-api кеширует через preparedMethods, так что overhead
 * минимальный после первого вызова).
 */
final class ResourceCompiler
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function compile(ResourceRegistry $registry): array
    {
        $controllers = [];
        foreach ($registry->all() as $slug => $class) {
            $controllers[$slug] = self::buildControllerEntry();
        }

        return $controllers;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildControllerEntry(): array
    {
        return [
            'controller' => ResourceController::class,
            'actions' => [
                'meta' => ['method' => ['get']],
                'search' => ['method' => ['post']],
                'read' => ['method' => ['get']],
                'create' => ['method' => ['post']],
                'update' => ['method' => ['post']],
                'delete' => ['method' => ['post']],
            ],
        ];
    }
}
