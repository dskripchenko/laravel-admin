<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

/**
 * Запускает per-action middleware, объявленные в `AdminApi::getMethods()`.
 *
 * laravel-api сам не применяет `actions.{action}.middleware` в route-pipeline'е
 * (только глобальные через middlewareGroup). Этот middleware закрывает разрыв:
 * на каждый admin-API запрос читает список action-middleware из preparedMethods
 * и прогоняет их через Pipeline до основного экшена.
 *
 * Используется, например, чтобы Resource-actions автоматически получали
 * AdminAccess::class.':admin.{slug}.{action}' middleware.
 */
final class RunActionMiddleware
{
    public function __construct(private readonly Container $container) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var string|null $controllerKey */
        $controllerKey = ApiRequest::getApiControllerKey();
        /** @var string|null $actionKey */
        $actionKey = ApiRequest::getApiActionKey();

        if ($controllerKey === null || $actionKey === null) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $methods = AdminApi::getPreparedMethods();
        $controllerMiddleware = (array) Arr::get(
            $methods,
            "controllers.{$controllerKey}.middleware",
            [],
        );
        $actionMiddleware = (array) Arr::get(
            $methods,
            "controllers.{$controllerKey}.actions.{$actionKey}.middleware",
            [],
        );

        $excludeController = (array) Arr::get(
            $methods,
            "controllers.{$controllerKey}.exclude-middleware",
            [],
        );
        $excludeAction = (array) Arr::get(
            $methods,
            "controllers.{$controllerKey}.actions.{$actionKey}.exclude-middleware",
            [],
        );

        $stack = array_diff(
            array_merge($controllerMiddleware, $actionMiddleware),
            array_merge($excludeController, $excludeAction),
        );

        if ($stack === []) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        /** @var Response $response */
        $response = (new Pipeline($this->container))
            ->send($request)
            ->through(array_values($stack))
            ->then(static fn (Request $req): Response => $next($req));

        return $response;
    }
}
