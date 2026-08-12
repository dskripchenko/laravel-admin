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
 * Runs the per-action middleware declared in `AdminApi::getMethods()`.
 *
 * laravel-api does not apply `actions.{action}.middleware` in the route
 * pipeline itself — only the global ones, through the middleware group. This
 * middleware closes the gap: on every admin API request it reads the action's
 * middleware out of preparedMethods and runs them through a Pipeline before
 * the action itself.
 *
 * It is what gives the resource actions their
 * AdminAccess::class.':admin.{slug}.{action}' middleware automatically.
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

        // The methods are read from the API class of the CURRENT version —
        // the current panel — rather than from AdminApi outright: otherwise a
        // panel's per-action middleware (the AdminAccess of its resources)
        // would never apply.
        /** @var class-string<\Dskripchenko\LaravelApi\Components\BaseApi> $apiClass */
        $apiClass = \Dskripchenko\LaravelApi\Facades\ApiModule::getApi() ?? AdminApi::class;
        $methods = $apiClass::getPreparedMethods();
        // The API class's global middleware — a panel's additions: activating
        // its layer, throttling — must run even when the request matched
        // laravel-api's generic route `api/{version}/{controller}/{action}`.
        // That route carries the base group alone, and the panel's layer was
        // quietly lost, so the client panel worked past the client's schema.
        // Duplicates coming from the specific routes are cut out by the
        // route-stack filter below.
        $globalMiddleware = (array) Arr::get($methods, 'middleware', []);
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
            array_merge($globalMiddleware, $controllerMiddleware, $actionMiddleware),
            array_merge($excludeController, $excludeAction),
        );

        // A current laravel-api attaches the per-action middleware to the
        // route itself, through getMiddlewareForAction at registration time,
        // and running them a second time is not allowed: a stateful middleware
        // such as ThrottleRequests would fire twice per request. So anything
        // already in the route stack is skipped, and the Pipeline stays as the
        // safety net for contexts with no route registration.
        $routeMiddleware = $request->route()?->gatherMiddleware() ?? [];
        $stack = array_filter(
            $stack,
            static fn ($mw): bool => ! in_array($mw, $routeMiddleware, true),
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
