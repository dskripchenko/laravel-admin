<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Резолвер локали admin-панели.
 *
 * Полная реализация на фазе P16: учитывать User->locale, query-param,
 * cookie, заголовок Accept-Language. Сейчас — заглушка с дефолтом из конфига.
 */
final class AdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) config('admin.ui.default_locale', 'ru');
        app()->setLocale($locale);

        return $next($request);
    }
}
