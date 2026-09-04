<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Dskripchenko\LaravelAdmin\Panel\PanelRegistry;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the middleware stack that belongs to the CURRENT API version.
 *
 * laravel-api registers its middleware group once, at boot, from whatever
 * `getApiMiddleware()` returns at that moment. A host module that stitches
 * its own versions together with the admin (`AppApiModule extends
 * AdminApiModule`, versions `v1` and `internal` next to the panels) used to
 * get the admin's stack — sessions, CSRF, AdminAuth — on every one of them:
 * a public API with a bearer token was refused with 401 before its own
 * middleware ever ran.
 *
 * Choosing the stack at boot by the request's version would fix that under
 * FPM and silently not under Octane, where the worker boots once and the
 * group would be whatever the first request said. So the group stays
 * version-agnostic and the choice is made here, per request:
 *
 *   - a panel version (`admin`, or an id from PanelRegistry) runs the panel
 *     stack, `config('admin.middleware.api')`;
 *   - any other version runs laravel-api's own contract — the global,
 *     controller and action middleware of its BaseApi class — through
 *     RunActionMiddleware, and nothing of the admin's.
 *
 * The route's `withoutMiddleware` — what `exclude-middleware` in
 * getMethods() becomes — is honoured inside the nested stack too, so
 * `auth/login` still passes AdminAuth by. Terminable middleware inside the
 * nested stack is not terminated by the kernel; none of Laravel's `web`
 * group is terminable, and a host that adds one to the panel stack should
 * put it on the route instead.
 */
final class RunVersionMiddleware
{
    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly PanelRegistry $panels,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var string|null $version */
        $version = ApiRequest::getApiVersion();

        $stack = $this->isPanelVersion($version)
            ? $this->panelStack()
            : [RunActionMiddleware::class];

        /** @var array<int, string> $excluded */
        $excluded = $request->route()?->excludedMiddleware() ?? [];
        /** @var array<int, string> $resolved */
        $resolved = $this->router->resolveMiddleware($stack, $excluded);

        if ($resolved === []) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        /** @var Response $response */
        $response = (new Pipeline($this->container))
            ->send($request)
            ->through($resolved)
            ->then(static fn (Request $req): Response => $next($req));

        return $response;
    }

    /**
     * Before the URL is parsed there is no version; that is the admin, as
     * everywhere else in the module.
     */
    private function isPanelVersion(?string $version): bool
    {
        return $version === null || $version === '' || $this->panels->has($version);
    }

    /**
     * The panel stack from the config, without the two that are already in
     * the group: CaptureApiRequest ran before us, and we are running.
     *
     * @return array<int, string>
     */
    private function panelStack(): array
    {
        /** @var array<int, string> $stack */
        $stack = (array) config('admin.middleware.api', []);

        return array_values(array_filter(
            $stack,
            static fn ($mw): bool => $mw !== CaptureApiRequest::class && $mw !== self::class,
        ));
    }
}
