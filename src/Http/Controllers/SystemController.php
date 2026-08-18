<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Impersonation\ImpersonationManager;
use Dskripchenko\LaravelAdmin\Menu\MenuRegistry;
use Dskripchenko\LaravelAdmin\Permission\PermissionRegistry;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedScreen;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Support\Manifest;
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `system` controller — the actions behind bootstrap, manifest, me, menu,
 * locales, permissions and plugins.
 *
 * See docs/api/system.md and docs/api/registration.md.
 */
final class SystemController extends ApiController
{
    public function __construct(
        private readonly Admin $admin,
        private readonly Manifest $manifest,
        private readonly ResourceRegistry $resources,
        private readonly ScreenRegistry $screens,
        private readonly MenuRegistry $menuRegistry,
        private readonly PermissionRegistry $permissions,
    ) {}

    /**
     * Returns the SPA's bootstrap data, for the xhr strategy.
     *
     * With the default `inline` strategy the data arrives through the
     * `<script>` tag in shell.blade.php and this action is never called.
     *
     * @output object  $payload
     * @output string  $payload.csrf
     * @output string  $payload.baseUrl
     * @output string  $payload.apiUrl
     * @output string  $payload.locale
     * @output array   $payload.availableLocales
     * @output string  $payload.theme
     * @output object  $payload.brand
     * @output object  ?$payload.user
     * @output array   $payload.permissions
     * @output string  $payload.manifestVersion
     * @output object  $payload.config
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {BootstrapResponse}
     * @response 401 {UnauthenticatedErrorResponse}
     */
    public function bootstrap(Request $request, \Dskripchenko\LaravelAdmin\Support\BootstrapBuilder $builder): JsonResponse
    {
        return $this->success($builder->build($request));
    }

    /**
     * Returns the full admin JSON manifest.
     *
     * @header string ?$If-None-Match The ETag of the previous response.
     *
     * @output object $payload The manifest.
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {ManifestResponse}
     * @response 304 {NotModifiedResponse}
     */
    public function manifest(Request $request): JsonResponse
    {
        // The memo key must match what the content was actually translated
        // with. The manifest strings go through Localize::string() → __(),
        // that is through app()->getLocale(), which AdminLocale set from the
        // whole chain (query → header → user.locale → cookie →
        // Accept-Language). The header is only one link of that chain: when it
        // was empty and the locale came from user.locale, the key diverged
        // from the content and an English manifest was filed under the key
        // `ru`. Under Octane the Manifest instance outlives the request, so
        // the next Russian-speaking user got someone else's English manifest
        // out of the memo — with a matching ETag to go with it.
        $locale = app()->getLocale();
        $panel = \Dskripchenko\LaravelAdmin\Panel\Panels::current();
        $payload = $this->manifest->build($locale, $panel->id);
        $etag = '"'.$payload['version'].'"';

        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch === $etag) {
            return new JsonResponse(null, Response::HTTP_NOT_MODIFIED, ['ETag' => $etag]);
        }

        return $this->success($payload)->header('ETag', $etag);
    }

    /**
     * The current administrator.
     *
     * @output object ?$payload An AdminUserSummary, or null.
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {AdminUserSummaryResponse}
     */
    public function me(Request $request, ImpersonationManager $impersonation): JsonResponse
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();

        if (! $user instanceof Model) {
            return $this->success([]);
        }

        $impersonator = null;
        if ($impersonation->isActive()) {
            $provider = Auth::createUserProvider(
                \Dskripchenko\LaravelAdmin\Panel\Panels::currentProvider(),
            );
            $original = $provider?->retrieveById($impersonation->impersonatorId());
            if ($original instanceof Model) {
                $impersonator = [
                    'id' => $original->getKey(),
                    'name' => $original->getAttribute('name'),
                ];
            }
        }

        // The notifications table may be missing in the host project (the
        // default Laravel migration was never run) — fall back to 0 so that
        // the shell does not fall over.
        $unreadNotifications = \Illuminate\Support\Facades\Schema::hasTable('notifications')
            ? \Illuminate\Notifications\DatabaseNotification::query()
                ->where('notifiable_type', $user->getMorphClass())
                ->where('notifiable_id', $user->getKey())
                ->whereNull('read_at')
                ->count()
            : 0;

        return $this->success([
            'id' => $user->getKey(),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'locale' => $user->getAttribute('locale') ?? config('admin.ui.default_locale', 'ru'),
            'theme' => $user->getAttribute('theme') ?? config('admin.ui.default_theme', 'light'),
            'twoFactorEnabled' => method_exists($user, 'hasTwoFactorEnabled')
                ? $user->hasTwoFactorEnabled()
                : false,
            'impersonator' => $impersonator,
            'unread_notifications_count' => $unreadNotifications,
        ]);
    }

    /**
     * The sidebar menu tree.
     *
     * @output object $payload
     * @output array  $payload.items
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {MenuResponse}
     */
    public function menu(Request $request): JsonResponse
    {
        // The custom hierarchical menu, if the host registered one through
        // Admin::menu()->add(...). Nodes may nest arbitrarily and may hold
        // MenuNode::resource()/screen(), which auto-resolve the label, the url
        // and the permissions.
        $panel = \Dskripchenko\LaravelAdmin\Panel\Panels::current()->id;

        $custom = [];
        $usedKeys = [];
        foreach ($this->menuRegistry->roots($panel) as $node) {
            $serialized = $node->toArray($this->resources, $this->screens);
            $custom[] = $serialized;
            self::collectUsedSlugs($serialized, $usedKeys);
        }

        // Auto-fill: add the resources and custom screens that the custom
        // tree does not mention yet. On by default, so that nobody has to
        // repeat every resource in menu()->add().
        $auto = [];
        if ($this->menuRegistry->autoFillEnabled($panel)) {
            $auto = $this->buildAutoItems($usedKeys, $panel);
        }

        return $this->success(['items' => array_merge($custom, $auto)]);
    }

    /**
     * Collects, recursively, every resource and screen slug mentioned in the
     * tree — so that auto-fill does not duplicate the custom nodes.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, true>  &$used
     */
    private static function collectUsedSlugs(array $node, array &$used): void
    {
        $key = $node['key'] ?? null;
        if (is_string($key)) {
            $used[$key] = true;
            // MenuNode::resource('users') also produces the automatic key
            // 'resource.users' and the url '/r/users' — mark the slug
            // separately so that it matches the auto-added resources.
            if (str_starts_with($key, 'resource.')) {
                $used[substr($key, strlen('resource.'))] = true;
            } elseif (str_starts_with($key, 'screen.')) {
                $used['screen.'.substr($key, strlen('screen.'))] = true;
            }
        }
        if (is_array($node['children'] ?? null)) {
            foreach ($node['children'] as $child) {
                if (is_array($child)) {
                    self::collectUsedSlugs($child, $used);
                }
            }
        }
    }

    /**
     * The older automatic logic: builds flat items for every resource and
     * screen the custom menu did not mention, preserving the default behaviour.
     *
     * @param  array<string, true>  $used
     * @return list<array<string, mixed>>
     */
    private function buildAutoItems(array $used, string $panel = 'admin'): array
    {
        $items = [];
        $hidden = array_fill_keys($this->menuRegistry->autoHiddenSlugs($panel), true);

        foreach ($this->resources->all($panel) as $slug => $class) {
            if (isset($used[$slug]) || isset($hidden[$slug])) {
                continue;
            }
            $resource = $this->resources->resolve($slug);
            if ($resource === null) {
                continue;
            }
            $viewPermission = $resource::permission().'.view';

            $items[] = [
                'key' => $slug,
                'label' => $resource::label(),
                'icon' => $resource::$icon,
                'url' => '/r/'.$slug,
                'routeName' => 'admin.resource.'.$slug.'.index',
                'group' => $resource::$group === null ? null : (string) __($resource::$group),
                'badge' => null,
                'order' => 0,
                'permissions' => [$viewPermission],
                'children' => [],
            ];
        }

        foreach ($this->screens->all($panel) as $slug => $class) {
            if (isset($used['screen.'.$slug])) {
                continue;
            }
            if (is_subclass_of($class, GeneratedScreen::class)) {
                continue;
            }
            if (is_subclass_of($class, DashboardScreen::class)) {
                continue;
            }
            $screen = $this->admin->resolveScreen($slug);
            if ($screen === null) {
                continue;
            }
            $permission = $screen->permission();
            $permissions = match (true) {
                $permission === null => [],
                is_string($permission) => [$permission],
                default => $permission,
            };
            $items[] = [
                'key' => 'screen.'.$slug,
                'label' => $screen->name(),
                'icon' => null,
                'url' => '/screens/'.$slug,
                'routeName' => 'admin.screen.'.$slug,
                'group' => (string) __('Инструменты'),
                'badge' => null,
                'order' => 100,
                'permissions' => $permissions,
                'children' => [],
            ];
        }

        return $items;
    }

    /**
     * The admin's available locales.
     *
     * @output object $payload
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {LocalesResponse}
     */
    public function locales(Request $request, \Dskripchenko\LaravelAdmin\Theme\LocaleResolver $resolver): JsonResponse
    {
        return $this->success([
            'available' => $resolver->available(),
            'current' => $resolver->resolve($request),
            'default' => $resolver->default(),
            'fallback' => (string) config('admin.ui.fallback_locale', 'en'),
        ]);
    }

    /**
     * Sets the locale, in user.locale and in a cookie.
     *
     * @input string $locale
     *
     * @output object $payload
     *
     * @response 200 {LocaleUpdatedResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function setLocale(Request $request, \Dskripchenko\LaravelAdmin\Theme\LocaleResolver $resolver): JsonResponse
    {
        $data = $request->validate(['locale' => ['required', 'string']]);

        if (! $resolver->isAvailable($data['locale'])) {
            return $this->error([
                'errorKey' => 'unsupported_locale',
                'message' => 'Locale `'.$data['locale'].'` is not in available list',
            ], 422);
        }

        $cookie = $resolver->persist($data['locale']);
        app()->setLocale($data['locale']);

        $response = $this->success(['locale' => $data['locale']]);
        $response->withCookie($cookie);

        return $response;
    }

    /**
     * The permission groups, for the role matrix in the UI.
     *
     * @output object $payload
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {PermissionsResponse}
     */
    public function permissions(Request $request): JsonResponse
    {
        $panel = \Dskripchenko\LaravelAdmin\Panel\Panels::current()->id;
        $groups = array_map(
            static fn ($g): array => $g->toArray(),
            $this->permissions->groups($panel),
        );

        return $this->success(['groups' => $groups]);
    }

    /**
     * Global search across every resource of the panel — the ⌘K palette.
     *
     * @output object $payload
     * @output string $payload.query
     * @output array  $payload.groups
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {SuccessResponse}
     */
    public function search(Request $request, \Dskripchenko\LaravelAdmin\Support\GlobalSearch $search): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $panel = \Dskripchenko\LaravelAdmin\Panel\Panels::current()->id;
        $user = Auth::guard(\Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard())->user();

        $groups = mb_strlen(trim($query)) < 2
            ? []
            : $search->search($query, $user, $panel);

        return $this->success([
            'query' => $query,
            'groups' => $groups,
        ]);
    }

    /**
     * The list of registered AdminPlugins.
     *
     * @output object $payload
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {PluginsResponse}
     */
    /**
     * The state of the top bar's status indicators.
     *
     * A separate action rather than a field of the manifest on purpose: the
     * manifest is cached by ETag and is meant to be stale-tolerant — the shape
     * of the panel changes on deploy. Status is the opposite kind of data: it
     * changes on its own, without anyone deploying anything, and a health check
     * one has to hard-refresh to trust is worse than no health check.
     *
     * An indicator that throws is dropped, not raised: these are diagnostics,
     * and a broken diagnostic must not take the header down with it.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {StatusResponse}
     */
    public function status(Request $request): JsonResponse
    {
        $panel = \Dskripchenko\LaravelAdmin\Panel\Panels::current()->id;
        $indicators = [];

        foreach ($this->admin->getStatusIndicators($panel) as $class) {
            try {
                /** @var \Dskripchenko\LaravelAdmin\Status\StatusIndicator $indicator */
                $indicator = app($class);

                // Deliberately widened: the declared shape is a promise from a
                // third-party class, and the checks below exist precisely for
                // the implementation that does not keep it.
                /** @var array<string, mixed> $state */
                $state = $indicator->state();

                $indicators[] = [
                    'key' => $indicator->key(),
                    'status' => in_array($state['status'] ?? null, ['ok', 'warning', 'error', 'unknown'], true)
                        ? $state['status']
                        : 'unknown',
                    'label' => (string) ($state['label'] ?? ''),
                    'detail' => isset($state['detail']) ? (string) $state['detail'] : null,
                    'url' => isset($state['url']) ? (string) $state['url'] : null,
                ];
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->success(['indicators' => $indicators]);
    }

    public function plugins(Request $request): JsonResponse
    {
        $plugins = [];
        foreach ($this->admin->getPlugins() as $class) {
            $plugins[] = [
                'id' => $class,
                'version' => '0.0.0-dev',
                'requires' => [],
            ];
        }

        return $this->success(['plugins' => $plugins]);
    }

    /**
     * Returns the current theme and the list of available ones.
     *
     * @output object $payload
     *
     * @response 200 {ThemeStateResponse}
     */
    public function theme(Request $request, \Dskripchenko\LaravelAdmin\Theme\ThemeManager $themes): JsonResponse
    {
        return $this->success([
            'current' => $themes->current($request),
            'default' => $themes->default(),
            'available' => $themes->available(),
        ]);
    }

    /**
     * Sets the theme: a cookie for anonymous visitors, user.theme for those logged in.
     *
     * @input string $theme
     *
     * @output object $payload
     *
     * @response 200 {ThemeUpdatedResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function setTheme(Request $request, \Dskripchenko\LaravelAdmin\Theme\ThemeManager $themes): JsonResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'string'],
        ]);

        if (! $themes->isAvailable($data['theme'])) {
            return $this->error([
                'errorKey' => 'unsupported_theme',
                'message' => 'Theme `'.$data['theme'].'` is not in available list',
            ], 422);
        }

        $cookie = $themes->persist($data['theme']);

        $response = $this->success([
            'theme' => $data['theme'],
        ]);
        $response->withCookie($cookie);

        return $response;
    }
}
