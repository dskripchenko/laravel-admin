<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Генерирует CSP-nonce для inline-скриптов SPA-shell.
 *
 * Стратегия inline (config admin.bootstrap.strategy = 'inline') инжектит
 * window.__ADMIN_BOOTSTRAP__ в shell.blade. Чтобы strict-CSP-проекты
 * не были обязаны включать 'unsafe-inline', мы генерируем nonce
 * на каждый запрос и кладём его в request attributes.
 *
 * Host-проект отвечает за добавление nonce в Content-Security-Policy
 * заголовок (через свой middleware/security headers пакет).
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
