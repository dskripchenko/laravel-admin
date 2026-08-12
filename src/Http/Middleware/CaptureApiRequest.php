<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Dskripchenko\LaravelApi\Requests\BaseApiRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initializes the BaseApiRequest singleton for the current HTTP request.
 *
 * laravel-api relies on the `BaseApiRequest::$_instance` singleton to resolve
 * {version}/{controller}/{action} out of the URL. At runtime it is filled by
 * `Request::capture()` from PHP's globals — but in tests, and with an unusual
 * kernel pipeline, those globals may be empty. So we call
 * `createFromBase($request)` explicitly on every request, which guarantees
 * that `ApiRequest::getApiVersion()` and its kin return the right values.
 *
 * The middleware is cheap: `createFromBase` merely recreates the singleton. It
 * is wired in through `config/admin.php → middleware.api`.
 */
final class CaptureApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        BaseApiRequest::createFromBase($request);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
