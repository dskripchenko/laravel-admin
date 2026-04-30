<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Проверка аутентификации на admin-guard.
 *
 * Полная имплементация (с поддержкой Sanctum-токенов, 2FA-challenge,
 * impersonation) появится на фазе P2. Сейчас — минимальная заглушка
 * для возможности рендера shell без падений.
 */
final class AdminAuth
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $guard = (string) config('admin.auth.guard', 'admin');

        if (! Auth::guard($guard)->check()) {
            return response()->json([
                'success' => false,
                'payload' => ['errorKey' => 'unauthenticated', 'message' => 'Unauthenticated'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
