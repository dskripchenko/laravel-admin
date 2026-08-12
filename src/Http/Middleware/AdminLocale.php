<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Dskripchenko\LaravelAdmin\Theme\LocaleResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the admin panel's locale.
 *
 * It delegates to LocaleResolver and calls app()->setLocale() for the current
 * request. The order is: ?locale → X-Admin-Locale → user.locale → the
 * admin_locale cookie → Accept-Language → config('admin.ui.default_locale').
 */
final class AdminLocale
{
    /**
     * The sources of the locale a caching intermediary has to know about.
     *
     * `Cookie` here is no over-caution: two of the five sources are cookies —
     * the session, which user.locale comes from, and admin_locale.
     */
    private const VARY = ['Accept-Language', LocaleResolver::HEADER, 'Cookie'];

    public function __construct(private readonly LocaleResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolver->resolve($request);
        app()->setLocale($locale);

        $response = $next($request);

        // The response depends on the language: the shell carries an inline
        // bootstrap full of strings, and the manifest the labels of the
        // resources and the menu. Without a Vary, any reverse proxy or CDN in
        // front of the panel will hand one visitor's language to another — the
        // same class of mistake as a manifest cached under the wrong key, one
        // floor up. The service itself has no proxy, but a customer running
        // the boxed version or a cluster puts one in front.
        // One line rather than three headers: three values through
        // setVary(..., replace: false) is valid HTTP, but simple proxies and
        // some CDNs read only the first. Anything already in Vary is kept.
        $existing = array_filter(array_map(
            'trim',
            explode(',', (string) $response->headers->get('Vary', '')),
        ));
        $merged = array_values(array_unique([...$existing, ...self::VARY]));
        $response->headers->set('Vary', implode(', ', $merged));

        return $response;
    }
}
