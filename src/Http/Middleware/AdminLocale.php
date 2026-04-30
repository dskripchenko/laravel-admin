<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Dskripchenko\LaravelAdmin\Theme\LocaleResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Резолвер локали admin-панели.
 *
 * Делегирует к LocaleResolver, устанавливает app()->setLocale() для текущего
 * request'а. Приоритезация: query?locale → X-Admin-Locale → user.locale →
 * cookie admin_locale → Accept-Language → config('admin.ui.default_locale').
 */
final class AdminLocale
{
    public function __construct(private readonly LocaleResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolver->resolve($request);
        app()->setLocale($locale);

        return $next($request);
    }
}
