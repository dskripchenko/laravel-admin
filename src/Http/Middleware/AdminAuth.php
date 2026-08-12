<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Checks that the request is authenticated on the admin guard.
 *
 * The middleware applies globally, through `config('admin.middleware.api')`,
 * but honours the `exclude-middleware` declarations of
 * `AdminApi::getMethods()`: when a controller or a particular action lists
 * `AdminAuth::class` there, the request passes through. That is what makes the
 * public endpoints — auth/login, auth/forgotPassword, auth/resetPassword —
 * work.
 */
final class AdminAuth
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if ($this->isExcludedForCurrentAction()) {
            /** @var SymfonyResponse $response */
            $response = $next($request);

            return $response;
        }

        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();

        if (! Auth::guard($guard)->check()) {
            return response()->json([
                'success' => false,
                'payload' => ['errorKey' => 'unauthenticated', 'message' => 'Unauthenticated'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = Auth::guard($guard)->user();

        // A switched-off account goes out on the very next request, not only
        // at the login; what counts as switched off is AccountState's business.
        if ($user !== null && \Dskripchenko\LaravelAdmin\Auth\AccountState::isDisabled($user)) {
            Auth::guard($guard)->logout();

            return response()->json([
                'success' => false,
                'payload' => ['errorKey' => 'account_inactive', 'message' => __('Учётная запись отключена')],
            ], Response::HTTP_FORBIDDEN);
        }

        // Changing the password invalidates the other sessions — Laravel's
        // AuthenticateSession mechanics, done JSON-first: the session stores
        // the hash of the password as it was at the login, and a mismatch
        // means the session belongs to someone else, or to an earlier
        // password.
        if ($user !== null && $request->hasSession()) {
            $key = 'password_hash_'.$guard;
            $hash = (string) $user->getAuthPassword();
            $stored = $request->session()->get($key);
            if (is_string($stored) && $stored !== $hash) {
                Auth::guard($guard)->logout();
                $request->session()->invalidate();

                return response()->json([
                    'success' => false,
                    'payload' => ['errorKey' => 'session_expired', 'message' => __('Пароль был изменён — войдите заново')],
                ], Response::HTTP_UNAUTHORIZED);
            }
            if ($stored === null) {
                $request->session()->put($key, $hash);
            }
        }

        /** @var SymfonyResponse $response */
        $response = $next($request);

        return $response;
    }

    /**
     * Tells whether AdminAuth::class is listed in the exclude-middleware of
     * the request's current `controller` or `action`.
     *
     * That is what lets the public endpoints such as `auth/login` work without
     * authentication even while AdminAuth is part of the global api group.
     *
     * When a host project has stitched the admin API together with other
     * versions — external-v1, say — in one laravel-api module, the exclusion
     * is read from the API version of the actual request, through ApiModule,
     * rather than from a fixed AdminApi.
     */
    private function isExcludedForCurrentAction(): bool
    {
        /** @var string|null $controllerKey */
        $controllerKey = ApiRequest::getApiControllerKey();
        /** @var string|null $actionKey */
        $actionKey = ApiRequest::getApiActionKey();

        if ($controllerKey === null || $actionKey === null) {
            return false;
        }

        /** @var class-string<BaseApi> $apiClass */
        $apiClass = ApiModule::getApi() ?? AdminApi::class;
        $methods = $apiClass::getPreparedMethods();

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

        return in_array(self::class, $excludeController, true)
            || in_array(self::class, $excludeAction, true);
    }
}
