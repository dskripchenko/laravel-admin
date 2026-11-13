<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission\Middleware;

use Closure;
use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The middleware guarding by permission.
 *
 *     'middleware' => [AdminAccess::class.':admin.users.view']
 *
 * Several permissions are separated by `;`:
 *
 *     AdminAccess::class.':admin.users.view;admin.systems.audit.view'
 *
 * The semantics are AND — every one of them is required. A second parameter,
 * `any`, turns them into OR — one of them is enough:
 *
 *     AdminAccess::class.':admin.orders.view;admin.quotes.view,any'
 */
final class AdminAccess
{
    /** The second parameter that makes one of the listed permissions enough. */
    public const MODE_ANY = 'any';

    public function handle(Request $request, Closure $next, string $permissions = '', string $mode = 'all'): Response
    {
        $required = array_filter(array_map('trim', explode(';', $permissions)));

        if ($required === []) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();

        if ($user === null) {
            return ApiResponseHelper::sayError([
                'errorKey' => 'unauthenticated',
                'message' => 'Unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Both HasAdminAccess — our own model — and any object with a public
        // `hasAccess` method are supported, the latter for the host's User in
        // the shared strategy.
        if (trim($mode) === self::MODE_ANY) {
            foreach ($required as $permission) {
                if (method_exists($user, 'hasAccess') && $user->hasAccess($permission)) {
                    /** @var Response $response */
                    $response = $next($request);

                    return $response;
                }
            }

            return ApiResponseHelper::sayError([
                'errorKey' => 'forbidden',
                'message' => __('Доступ запрещён: :permission', ['permission' => implode(' | ', $required)]),
                // The key the "no access" state names and offers to copy.
                'permission' => implode(' | ', $required),
            ], Response::HTTP_FORBIDDEN);
        }

        foreach ($required as $permission) {
            if (! method_exists($user, 'hasAccess') || ! $user->hasAccess($permission)) {
                return ApiResponseHelper::sayError([
                    'errorKey' => 'forbidden',
                    'message' => __('Доступ запрещён: :permission', ['permission' => $permission]),
                    'permission' => $permission,
                ], Response::HTTP_FORBIDDEN);
            }
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
