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
 * Проверка аутентификации на admin-guard.
 *
 * Middleware применяется глобально через `config('admin.middleware.api')`,
 * но уважает `exclude-middleware` декларации в `AdminApi::getMethods()`:
 * если controller или конкретный action декларирует `AdminAuth::class` в
 * `exclude-middleware`, middleware пропускает запрос (для public-эндпоинтов
 * типа auth/login, auth/forgotPassword, auth/resetPassword).
 *
 * Sanctum/Bearer-tokens и 2FA-challenge поддержка появятся в P2.3+.
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

        $guard = (string) config('admin.auth.guard', 'admin');

        if (! Auth::guard($guard)->check()) {
            return response()->json([
                'success' => false,
                'payload' => ['errorKey' => 'unauthenticated', 'message' => 'Unauthenticated'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        /** @var SymfonyResponse $response */
        $response = $next($request);

        return $response;
    }

    /**
     * Проверяет, объявлен ли AdminAuth::class в exclude-middleware для текущего
     * `controller`/`action` запроса.
     *
     * Используется для public-эндпоинтов типа `auth/login`, чтобы они работали
     * без аутентификации даже когда AdminAuth — часть глобальной api-группы.
     *
     * Если host-проект сшил admin API c другими версиями (например external-v1)
     * в одном laravel-api модуле, exclude читается у фактической API-версии
     * текущего запроса (через ApiModule), а не у фиксированного AdminApi.
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
