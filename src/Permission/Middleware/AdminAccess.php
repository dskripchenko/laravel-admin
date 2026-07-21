<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission\Middleware;

use Closure;
use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware-стражник по permissions.
 *
 *     'middleware' => [AdminAccess::class.':admin.users.view']
 *
 * Поддерживает несколько permissions через `;`:
 *
 *     AdminAccess::class.':admin.users.view;admin.systems.audit.view'
 *
 * Семантика — «требуется ВСЕ перечисленные» (AND). Для OR используется
 * отдельный middleware AdminAccessAny (P3+, если понадобится).
 */
final class AdminAccess
{
    public function handle(Request $request, Closure $next, string $permissions = ''): Response
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

        // Поддерживаем как HasAdminAccess (наша модель), так и любой объект
        // с публичным `hasAccess` методом (для shared-strategy host User).
        foreach ($required as $permission) {
            if (! method_exists($user, 'hasAccess') || ! $user->hasAccess($permission)) {
                return ApiResponseHelper::sayError([
                    'errorKey' => 'forbidden',
                    'message' => 'Доступ запрещён: '.$permission,
                ], Response::HTTP_FORBIDDEN);
            }
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
