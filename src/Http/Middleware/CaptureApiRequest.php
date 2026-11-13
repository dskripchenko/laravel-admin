<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Dskripchenko\LaravelApi\Requests\BaseApiRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
 *
 * It also gives the request its code: the panel's `X-Request-Id` (or a new
 * one) goes into the log context as `admin_request_id` and back in the
 * response's header — the code an error state shows is the one to look for
 * in the log.
 */
final class CaptureApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        BaseApiRequest::createFromBase($request);

        $id = self::requestId($request);
        Log::withContext(['admin_request_id' => $id]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }

    /** The panel's code when it is a sane one, else a new one. */
    public static function requestId(Request $request): string
    {
        $given = (string) $request->headers->get('X-Request-Id', '');

        return preg_match('/^[A-Za-z0-9._-]{1,64}$/', $given) === 1
            ? $given
            : Str::lower(Str::random(4).'-'.Str::random(4));
    }
}
