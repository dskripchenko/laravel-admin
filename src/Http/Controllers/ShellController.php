<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

use Dskripchenko\LaravelAdmin\Support\BootstrapBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Single Page Application shell.
 *
 * Возвращает один и тот же Blade на любой URL под /admin/* (кроме API).
 * Vue-router на клиенте сам обрабатывает routing.
 *
 * Стратегия 'inline' (default): bootstrap-payload инжектится в shell.blade
 * через `<script>`-тег с CSP-nonce. Стратегия 'xhr': SPA сама запрашивает
 * `/api/admin/system/bootstrap`. Контракт payload'а — единый, через
 * BootstrapBuilder.
 */
final class ShellController
{
    public function __invoke(Request $request, BootstrapBuilder $builder): View
    {
        $strategy = (string) config('admin.bootstrap.strategy', 'inline');

        $bootstrap = $strategy === 'inline'
            ? $builder->build($request)
            : ['strategy' => 'xhr'];

        /** @var view-string $view */
        $view = 'admin::shell';

        return view($view, [
            'bootstrap' => $bootstrap,
            'strategy' => $strategy,
            'cspNonce' => $request->attributes->get('admin.csp_nonce'),
            'brand' => (array) config('admin.brand', []),
            'assets' => $this->resolveAssets(),
        ]);
    }

    /**
     * Заготовка резолвера ассетов. Vite-manifest подключение появится в
     * полноценной SPA-сборке (P19/P20).
     *
     * @return array{css: list<string>, js: list<string>}
     */
    private function resolveAssets(): array
    {
        return [
            'css' => [],
            'js' => [],
        ];
    }
}
