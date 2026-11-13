<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Impersonation\ImpersonationManager;
use Dskripchenko\LaravelAdmin\Menu\MenuRegistry;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Dskripchenko\LaravelAdmin\Permission\PermissionRegistry;
use Dskripchenko\LaravelAdmin\Permission\RolePreview;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedScreen;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Screen\ScreenAccess;
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

        $impersonator = $impersonation->describeImpersonator();
        $preview = app(RolePreview::class);

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
            'locale' => $user->getAttribute('locale') ?? app(\Dskripchenko\LaravelAdmin\Theme\LocaleResolver::class)->default(),
            'theme' => $user->getAttribute('theme') ?? config('admin.ui.default_theme', 'light'),
            'twoFactorEnabled' => method_exists($user, 'hasTwoFactorEnabled')
                ? $user->hasTwoFactorEnabled()
                : false,
            'twoFactorRequired' => \Dskripchenko\LaravelAdmin\Auth\TwoFactor\TwoFactorPolicy::requiredFor($user),
            'impersonator' => $impersonator,
            'rolePreview' => $preview->describe($user),
            'canPreviewRoles' => $preview->allowedFor($user) && $impersonator === null,
            'unread_notifications_count' => $unreadNotifications,
        ]);
    }

    /**
     * The sidebar menu tree, the user's folded groups and the "+ Create" items.
     *
     * @output object $payload
     * @output array  $payload.items
     * @output object $payload.groups
     * @output array  $payload.create
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {MenuResponse}
     */
    public function menu(Request $request): JsonResponse
    {
        $panel = \Dskripchenko\LaravelAdmin\Panel\Panels::current()->id;
        $user = Auth::guard(\Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard())->user();

        $create = [];
        foreach ($this->menuRegistry->quickCreateItems($panel) as $item) {
            try {
                $serialized = $item->toArray($this->resources, $panel);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            if ($serialized !== null) {
                $create[] = $serialized;
            }
        }

        return $this->success([
            'items' => $this->menuItems($panel),
            // The user's own folding of the groups (NavigationController::group).
            'groups' => (object) \Dskripchenko\LaravelAdmin\Navigation\NavigationState::menuGroupsOf($user instanceof Model ? $user : null, $panel),
            // The top bar's "+ Create" menu (MenuRegistry::quickCreate()).
            'create' => $create,
        ]);
    }

    /**
     * The menu's computed counters — the badges set through a closure
     * (MenuNode::badge(fn …)) — keyed by the node's key, for the panel's
     * polling: the tree, the labels and the permissions stay as they were
     * loaded, only the numbers are asked again. A node the user may not see
     * is not computed.
     *
     * @output object $payload
     * @output object $payload.badges
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {MenuCountersResponse}
     */
    public function menuCounters(Request $request): JsonResponse
    {
        $panel = \Dskripchenko\LaravelAdmin\Panel\Panels::current()->id;
        $badges = [];
        foreach ($this->menuRegistry->roots($panel) as $node) {
            $badges += $node->lazyBadges($this->resources, $this->screens);
        }

        return $this->success(['badges' => (object) $badges]);
    }

    /**
     * The tabs of the screens the user's menu leads to — what the ⌘K palette
     * finds besides the menu's own items: «Заявки › CRM» opens
     * `/screens/applications?tab=crm`. Only the tabs kept in the address
     * (Tabs::persistInQuery()) and visible to the user; nested ones carry
     * their parents' keys too.
     *
     * @output object $payload
     * @output array  $payload.items
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {MenuTabsResponse}
     */
    public function menuTabs(Request $request): JsonResponse
    {
        $panel = \Dskripchenko\LaravelAdmin\Panel\Panels::current()->id;
        $screens = [];
        $collect = static function (array $node, array $parents) use (&$collect, &$screens): void {
            $url = $node['url'] ?? null;
            if (is_string($url) && preg_match('~^/screens/([^/?#]+)$~', $url, $m) === 1) {
                $screens[$m[1]] = ['label' => (string) ($node['label'] ?? $m[1]), 'parents' => $parents];
            }
            foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
                if (is_array($child)) {
                    $collect($child, ($node['kind'] ?? null) === 'section' ? $parents : [...$parents, (string) ($node['label'] ?? '')]);
                }
            }
        };
        foreach ($this->menuItems($panel) as $item) {
            $collect($item, []);
        }

        $items = [];
        foreach ($screens as $slug => $info) {
            $class = $this->screens->all($panel)[$slug] ?? null;
            if ($class === null) {
                continue;
            }
            try {
                $screen = app($class);
                if (! $screen instanceof Screen) {
                    continue;
                }
                foreach (\Dskripchenko\LaravelAdmin\Navigation\ScreenTabs::of($screen) as $tab) {
                    $items[] = [
                        'key' => 'screen.'.$slug.'.'.implode('.', array_values($tab['query'])),
                        'label' => $tab['label'],
                        'screen' => $info['label'],
                        'path' => [...$info['parents'], $info['label'], ...$tab['trail']],
                        'url' => '/screens/'.$slug.'?'.http_build_query($tab['query']),
                        'keywords' => array_values($tab['query']),
                    ];
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->success(['items' => $items]);
    }

    /**
     * The user's menu: the host's tree and the auto-filled items, each pruned
     * to what the user may open.
     *
     * @return list<array<string, mixed>>
     */
    private function menuItems(string $panel): array
    {
        // The custom hierarchical menu, if the host registered one through
        // Admin::menu()->add(...). Nodes may nest arbitrarily and may hold
        // MenuNode::resource()/screen(), which auto-resolve the label, the url
        // and the permissions.
        $custom = [];
        $usedKeys = [];
        foreach ($this->menuRegistry->roots($panel) as $node) {
            $serialized = $this->withoutForbiddenScreens($node->toArray($this->resources, $this->screens), $panel);
            $serialized = $serialized === null ? null : self::prunedForUser($serialized);
            if ($serialized === null) {
                continue;
            }
            $custom[] = $serialized;
            self::collectUsedSlugs($serialized, $usedKeys);
        }

        // Auto-fill: add the resources and custom screens that the custom
        // tree does not mention yet. On by default, so that nobody has to
        // repeat every resource in menu()->add().
        $auto = [];
        if ($this->menuRegistry->autoFillEnabled($panel)) {
            $auto = array_values(array_filter(array_map(
                fn (array $item): ?array => ($kept = $this->withoutForbiddenScreens($item, $panel)) === null
                    ? null
                    : self::prunedForUser($kept),
                $this->buildAutoItems($usedKeys, $panel),
            )));
        }

        return array_merge($custom, $auto);
    }

    /**
     * Drops, recursively, the nodes leading to a dashboard or a screen the
     * user may not open. The SPA filters the menu by the `permissions` of each
     * node with ANY semantics, but a screen's own rule — every key of
     * permission(), or one of them with permissionMode() 'any' — is applied
     * here, where it is defined: an item never leads to a 403.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function withoutForbiddenScreens(array $node, string $panel): ?array
    {
        $url = $node['url'] ?? null;
        if (is_string($url) && str_starts_with($url, '/dashboard/')) {
            $slug = substr($url, strlen('/dashboard/'));
            $class = $this->screens->all($panel)[$slug] ?? null;
            if ($class !== null && is_subclass_of($class, DashboardScreen::class)) {
                $screen = app($class);
                if ($screen instanceof DashboardScreen && ! $screen->canAccess()) {
                    return null;
                }
            }
        } elseif (is_string($url) && str_starts_with($url, '/screens/')) {
            $slug = substr($url, strlen('/screens/'));
            $class = $this->screens->all($panel)[$slug] ?? null;
            if ($class !== null && ! is_subclass_of($class, GeneratedScreen::class)) {
                try {
                    $screen = app($class);
                } catch (\Throwable) {
                    $screen = null;
                }
                if ($screen instanceof Screen && ! ScreenAccess::allows($screen)) {
                    return null;
                }
            }
        }

        if (is_array($node['children'] ?? null)) {
            $children = [];
            foreach ($node['children'] as $child) {
                $kept = is_array($child) ? $this->withoutForbiddenScreens($child, $panel) : $child;
                if ($kept !== null) {
                    $children[] = $kept;
                }
            }
            $node['children'] = $children;
        }

        return $node;
    }

    /**
     * The node as the signed-in user sees it, or null when there is nothing
     * to see. Recursively:
     *  - a node whose permissions the user holds none of goes, with its
     *    subtree — the same rule the SPA applies, now also on the server, so
     *    the menu does not describe sections the user cannot open;
     *  - a node with neither a url nor a route and no visible children goes
     *    too: a parent whose children were all filtered out would stay as a
     *    dead item that neither opens nor expands, and an ancestor left with
     *    nothing but such parents disappears in turn.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private static function prunedForUser(array $node): ?array
    {
        $permissions = is_array($node['permissions'] ?? null) ? array_values($node['permissions']) : [];
        if (! PermissionCheck::allowsAny($permissions)) {
            return null;
        }

        $children = [];
        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            $kept = is_array($child) ? self::prunedForUser($child) : null;
            if ($kept !== null) {
                $children[] = $kept;
            }
        }
        $node['children'] = $children;

        $hasTarget = (is_string($node['url'] ?? null) && $node['url'] !== '')
            || (is_string($node['routeName'] ?? null) && $node['routeName'] !== '');

        return $hasTarget || $children !== [] ? $node : null;
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
            // A resource that throws is left out of the menu, as it is of
            // the manifest (which logs it), instead of failing the shell.
            try {
                $resource = $this->resources->resolve($slug);
                if ($resource === null) {
                    continue;
                }

                $items[] = [
                    'key' => $slug,
                    'label' => $resource::label(),
                    'icon' => $resource::$icon,
                    'url' => '/r/'.$slug,
                    'routeName' => 'admin.resource.'.$slug.'.index',
                    'group' => $resource::$group === null ? null : (string) __($resource::$group),
                    'badge' => null,
                    'order' => 0,
                    'permissions' => [$resource::permission().'.view'],
                    'children' => [],
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        foreach ($this->screens->all($panel) as $slug => $class) {
            // hideAuto() takes a screen by its slug or as `screen.{slug}`.
            if (isset($used['screen.'.$slug]) || isset($hidden[$slug]) || isset($hidden['screen.'.$slug])) {
                continue;
            }
            if (is_subclass_of($class, GeneratedScreen::class)) {
                continue;
            }
            if (is_subclass_of($class, DashboardScreen::class)) {
                continue;
            }
            try {
                $screen = $this->admin->resolveScreen($slug);
                if ($screen === null) {
                    continue;
                }
                $permissions = ScreenAccess::keys($screen);
                $label = $screen->name();
            } catch (\Throwable) {
                continue;
            }
            $items[] = [
                'key' => 'screen.'.$slug,
                'label' => $label,
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

        // The panel keeps the server's sequence for equal orders, so the
        // auto-filled items come sorted: by order, then alphabetically.
        usort($items, static fn (array $a, array $b): int => [$a['order'], (string) $a['label']] <=> [$b['order'], (string) $b['label']]);

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
     * A query shorter than two characters returns no groups.
     *
     * @input string ?$q The text to look for
     *
     * @output object $payload
     * @output string $payload.query
     * @output array  $payload.groups
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {GlobalSearchResponse}
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
