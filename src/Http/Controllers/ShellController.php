<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Single Page Application shell.
 *
 * Возвращает один и тот же Blade на любой URL под /admin/* (кроме API).
 * Vue-router на клиенте сам обрабатывает routing.
 *
 * На фазе P0 отдаёт минимальный bootstrap. Полное наполнение
 * (manifest, user, permissions, menu, locales) — фазы P3 / P15.
 */
final class ShellController
{
    public function __invoke(Request $request): View
    {
        $strategy = (string) config('admin.bootstrap.strategy', 'inline');

        $bootstrap = $strategy === 'inline'
            ? $this->buildBootstrap($request)
            : ['strategy' => 'xhr'];

        return view('admin::shell', [
            'bootstrap' => $bootstrap,
            'strategy'  => $strategy,
            'cspNonce'  => $request->attributes->get('admin.csp_nonce'),
            'brand'     => (array) config('admin.brand', []),
            'assets'    => $this->resolveAssets(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBootstrap(Request $request): array
    {
        return [
            'csrf'          => csrf_token(),
            'baseUrl'       => url((string) config('admin.path')),
            'apiUrl'        => url((string) config('admin.api_path')),
            'locale'        => app()->getLocale(),
            'theme'         => 'light',
            'brand'         => (array) config('admin.brand', []),
            'manifestVersion' => null,
            'user'          => null,
        ];
    }

    /**
     * Заготовка резолвера ассетов. На фазе P0 возвращает пустые массивы;
     * далее заменим на чтение Vite-manifest через AssetsService.
     *
     * @return array{css: array<int,string>, js: array<int,string>}
     */
    private function resolveAssets(): array
    {
        return [
            'css' => [],
            'js'  => [],
        ];
    }
}
