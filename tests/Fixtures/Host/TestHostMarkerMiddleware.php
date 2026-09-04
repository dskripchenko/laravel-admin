<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests\Fixtures\Host;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Leaves a trace on the response, so a test can tell it ran. */
final class TestHostMarkerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Host-Version-Middleware', 'ran');

        return $response;
    }
}
