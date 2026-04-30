<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Dskripchenko\LaravelApi\Requests\BaseApiRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Инициализирует BaseApiRequest singleton под текущий HTTP-запрос.
 *
 * laravel-api полагается на `BaseApiRequest::$_instance` (singleton) для
 * резолвинга {version}/{controller}/{action} из URL. В runtime он
 * заполняется через `Request::capture()` из PHP-глобалов. В тестах и при
 * нестандартных kernel-pipelines глобалы могут быть пусты, поэтому мы
 * явно вызываем `createFromBase($request)` на каждом запросе — это
 * гарантирует, что `ApiRequest::getApiVersion()` etc. вернут корректные
 * значения.
 *
 * Middleware дешёвый — `createFromBase` просто пересоздаёт singleton.
 * Подключается в `config/admin.php → middleware.api`.
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
