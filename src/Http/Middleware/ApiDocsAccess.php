<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Middleware;

use Closure;
use Dskripchenko\LaravelAdmin\Auth\AccountState;
use Dskripchenko\LaravelAdmin\Auth\PanelAccess;
use Dskripchenko\LaravelAdmin\Panel\Panel;
use Dskripchenko\LaravelAdmin\Panel\PanelRegistry;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the panels' API documentation to everyone but the panel's own users
 * with `admin.api_docs.permission`.
 *
 * The spec of a panel lists every resource, field, filter, screen and method:
 * a map of the back office nobody outside it should read. The middleware
 * guards three places:
 *   - the panel's reference page (`{api}/admin/doc`) and its spec files —
 *     404 unless allowed;
 *   - laravel-api's `/{prefix}/doc/{version}` for a panel's version — 404
 *     unless allowed (AdminApiModule adds the middleware to the doc routes);
 *   - laravel-api's `/{prefix}/doc` index — the panel versions the reader may
 *     not see are hidden from it, the host's own versions stay.
 *
 * `admin.api_docs.enabled`: null (default) — on everywhere but production;
 * false — off; true — on. laravel-api's doc routes carry no session unless
 * `laravel-api.doc_middleware` adds one (e.g. ['web']), so without it the
 * panel versions are not served there at all.
 */
final class ApiDocsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $panels = self::panelIds();
        $version = $request->route('version');
        $routeName = $request->route()?->getName();

        // The panel's own page and spec files: the panel's readers only.
        if (in_array($routeName, ['admin.api-doc', 'admin.api-doc-source'], true) && ! self::allows('admin')) {
            abort(404);
        }

        if (is_string($version)) {
            if (in_array($version, $panels, true) && ! self::allows($version)) {
                abort(404);
            }
        } elseif ($routeName !== 'admin.api-doc') {
            $hidden = (array) config('laravel-api.hidden_versions', []);
            foreach ($panels as $panel) {
                if (! self::allows($panel)) {
                    $hidden[] = $panel;
                }
            }
            config()->set('laravel-api.hidden_versions', array_values(array_unique($hidden)));
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    /**
     * Whether the documentation of the panel is on and the reader is signed
     * in to that panel, may use it, and holds `admin.api_docs.permission`.
     */
    public static function allows(string $panelId): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $panel = app(PanelRegistry::class)->get($panelId);
        if (! $panel instanceof Panel) {
            return false;
        }

        try {
            $user = Auth::guard($panel->guard)->user();
        } catch (\Throwable) {
            return false;
        }
        if ($user === null || ! PanelAccess::allows($user, $panel) || AccountState::isDisabled($user)) {
            return false;
        }

        $permission = config('admin.api_docs.permission', 'admin.systems.api-docs');

        return ! is_string($permission) || $permission === '' || PermissionCheck::allows($permission, $user);
    }

    public static function enabled(): bool
    {
        $enabled = config('admin.api_docs.enabled');
        if ($enabled === null || $enabled === '') {
            return ! app()->environment('production');
        }

        return filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return list<string>
     */
    private static function panelIds(): array
    {
        $ids = ['admin'];
        foreach (app(PanelRegistry::class)->all() as $panel) {
            $ids[] = $panel->id;
        }

        return array_values(array_unique($ids));
    }
}
