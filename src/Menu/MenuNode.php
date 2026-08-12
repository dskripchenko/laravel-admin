<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Menu;

use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;

/**
 * The value object of a single sidebar menu node.
 *
 * Nodes nest arbitrarily through `children()`. The url, the label and the
 * permissions can be set by hand (`make()`) or pulled from ResourceRegistry
 * and ScreenRegistry (`resource()` and `screen()`).
 *
 * For example:
 *
 *   $admin->menu()->add(
 *     MenuNode::make('shop', 'Shop')->icon('store')->children([
 *       MenuNode::resource('products'),
 *       MenuNode::resource('orders'),
 *       MenuNode::make('analytics', 'Analytics')->children([
 *         MenuNode::screen('content'),
 *       ]),
 *     ]),
 *   );
 */
final class MenuNode
{
    private string $key;

    private string $label = '';

    private ?string $icon = null;

    private ?string $url = null;

    private ?string $routeName = null;

    private string|int|null $badge = null;

    private ?string $group = null;

    private int $order = 0;

    /** @var list<string> */
    private array $permissions = [];

    /** @var list<MenuNode> */
    private array $children = [];

    /** The "resolve through the registry" marker, used by resource() and screen(). */
    private ?string $autoResolve = null;

    private ?string $autoSlug = null;

    private function __construct(string $key)
    {
        $this->key = $key;
    }

    /** An arbitrary node: a group, a link or an external address. */
    public static function make(string $key, string $label = ''): self
    {
        $instance = new self($key);
        $instance->label = $label !== '' ? $label : $key;

        return $instance;
    }

    /** A node pointing at ResourceRegistry by slug. */
    public static function resource(string $slug): self
    {
        $instance = new self('resource.'.$slug);
        $instance->autoResolve = 'resource';
        $instance->autoSlug = $slug;

        return $instance;
    }

    /** A node pointing at ScreenRegistry by slug. */
    public static function screen(string $slug): self
    {
        $instance = new self('screen.'.$slug);
        $instance->autoResolve = 'screen';
        $instance->autoSlug = $slug;

        return $instance;
    }

    /**
     * A node pointing at a DashboardScreen by slug. Its url is
     * /dashboard/{slug} rather than /screens/{slug}, since dashboards have
     * their own controller and path.
     *
     * When a host passes a DashboardScreen's slug to screen(), the
     * auto-resolution detects it and produces /dashboard/{slug} too; this
     * helper exists for the sake of being explicit.
     */
    public static function dashboard(string $slug): self
    {
        $instance = new self('dashboard.'.$slug);
        $instance->autoResolve = 'dashboard';
        $instance->autoSlug = $slug;

        return $instance;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function url(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function routeName(?string $routeName): self
    {
        $this->routeName = $routeName;

        return $this;
    }

    public function badge(string|int|null $badge): self
    {
        $this->badge = $badge;

        return $this;
    }

    public function group(?string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function order(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @param  list<string>|string|null  $permissions
     */
    public function permissions(array|string|null $permissions): self
    {
        if ($permissions === null) {
            $this->permissions = [];
        } elseif (is_string($permissions)) {
            $this->permissions = [$permissions];
        } else {
            $this->permissions = array_values(array_filter($permissions));
        }

        return $this;
    }

    /**
     * @param  list<MenuNode>  $children
     */
    public function children(array $children): self
    {
        $this->children = $children;

        return $this;
    }

    public function add(MenuNode $child): self
    {
        $this->children[] = $child;

        return $this;
    }

    /** @return list<MenuNode> */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function autoType(): ?string
    {
        return $this->autoResolve;
    }

    public function autoSlug(): ?string
    {
        return $this->autoSlug;
    }

    /**
     * Serializes into the shape the frontend's useMenuStore expects.
     *
     * For an auto-resolving node the label, the url and the permissions are
     * pulled from the registries; anything set by hand wins over them.
     *
     * @return array<string, mixed>
     */
    public function toArray(ResourceRegistry $resources, ScreenRegistry $screens): array
    {
        $this->resolveAuto($resources, $screens);

        $children = array_map(
            fn (MenuNode $c): array => $c->toArray($resources, $screens),
            $this->children,
        );

        return [
            'key' => $this->key,
            // The label goes through the translator AT SERIALIZATION TIME —
            // per request, after the AdminLocale middleware — otherwise
            // `->label('Clients')` would be resolved in the locale of the
            // plugin's boot. Strings with no translation come back as they
            // are, the key being the fallback; resource labels have already
            // been localized by Resource::label(), and __() is idempotent on
            // them.
            'label' => $this->label === '' ? '' : (string) __($this->label),
            'icon' => $this->icon,
            'url' => $this->url,
            'routeName' => $this->routeName,
            'badge' => $this->badge,
            'group' => $this->group === null ? null : (string) __($this->group),
            'order' => $this->order,
            'permissions' => $this->permissions,
            'children' => $children,
        ];
    }

    /**
     * Fills in the automatic values from the registries when the node was
     * created through resource() or screen(), without overwriting anything
     * already set.
     */
    private function resolveAuto(ResourceRegistry $resources, ScreenRegistry $screens): void
    {
        if ($this->autoResolve === null || $this->autoSlug === null) {
            return;
        }

        if ($this->autoResolve === 'resource') {
            $resource = $resources->resolve($this->autoSlug);
            if ($resource === null) {
                return;
            }
            $class = $resource::class;
            if ($this->label === '' || $this->label === $this->key) {
                $this->label = $class::label();
            }
            $this->icon ??= $class::$icon ?? null;
            $this->url ??= '/r/'.$this->autoSlug;
            $this->routeName ??= 'admin.resource.'.$this->autoSlug.'.index';
            $this->group ??= $class::$group ?? null;
            if ($this->permissions === []) {
                $base = $class::permission();
                if ($base !== '') {
                    $this->permissions = [$base.'.view'];
                }
            }

            return;
        }

        if ($this->autoResolve === 'screen' || $this->autoResolve === 'dashboard') {
            $class = $screens->get($this->autoSlug);
            if ($class === null) {
                return;
            }
            $instance = app($class);

            // A DashboardScreen has its own url, /dashboard/{slug} (see
            // buildDashboardRoute in router/builder.ts), while custom screens
            // live under /screens/{slug}. Detected automatically.
            $isDashboard = $this->autoResolve === 'dashboard'
                || is_subclass_of($class, \Dskripchenko\LaravelAdmin\Widget\DashboardScreen::class);

            if ($this->label === '' || $this->label === $this->key) {
                $this->label = $instance->name();
            }
            if ($isDashboard) {
                $this->url ??= '/dashboard/'.$this->autoSlug;
                $this->routeName ??= 'admin.dashboard.'.$this->autoSlug;
            } else {
                $this->url ??= '/screens/'.$this->autoSlug;
                $this->routeName ??= 'admin.screen.'.$this->autoSlug;
            }
            if ($this->permissions === []) {
                $perm = $instance->permission();
                if (is_string($perm)) {
                    $this->permissions = [$perm];
                } elseif (is_array($perm)) {
                    $this->permissions = array_values($perm);
                }
            }
        }
    }
}
