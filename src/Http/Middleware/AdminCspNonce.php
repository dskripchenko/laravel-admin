<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates the CSP nonce of the SPA shell's inline scripts.
 *
 * The inline strategy (config admin.bootstrap.strategy = 'inline') injects
 * window.__ADMIN_BOOTSTRAP__ into shell.blade. So that projects with a strict
 * CSP are not forced to allow 'unsafe-inline', we generate a nonce per request
 * and put it into the request's attributes.
 *
 * Adding that nonce to the Content-Security-Policy header is the host
 * project's business, through its own middleware or security-headers package.
 */
final class AdminCspNonce
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('admin.csp_nonce', $nonce);

        return $next($request);
    }
}
