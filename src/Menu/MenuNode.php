<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Menu;

use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;

/**
 * Value-object для одного узла sidebar-меню.
 *
 * Поддерживает произвольную вложенность через `children()`. Конкретные
 * URL/label/permissions можно задать вручную (`make()`) либо подставить
 * из ResourceRegistry/ScreenRegistry (`resource()`/`screen()`).
 *
 * Пример:
 *
 *   $admin->menu()->add(
 *     MenuNode::make('shop', 'Магазин')->icon('store')->children([
 *       MenuNode::resource('products'),
 *       MenuNode::resource('orders'),
 *       MenuNode::make('analytics', 'Аналитика')->children([
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

    /** Маркер «резолвить через registry» — используется resource()/screen(). */
    private ?string $autoResolve = null;

    private ?string $autoSlug = null;

    private function __construct(string $key)
    {
        $this->key = $key;
    }

    /** Произвольный узел (group / link / external). */
    public static function make(string $key, string $label = ''): self
    {
        $instance = new self($key);
        $instance->label = $label !== '' ? $label : $key;

        return $instance;
    }

    /** Узел, ссылающийся на ResourceRegistry by slug. */
    public static function resource(string $slug): self
    {
        $instance = new self('resource.'.$slug);
        $instance->autoResolve = 'resource';
        $instance->autoSlug = $slug;

        return $instance;
    }

    /** Узел, ссылающийся на ScreenRegistry by slug. */
    public static function screen(string $slug): self
    {
        $instance = new self('screen.'.$slug);
        $instance->autoResolve = 'screen';
        $instance->autoSlug = $slug;

        return $instance;
    }

    /**
     * Узел, ссылающийся на DashboardScreen by slug. URL — /dashboard/{slug},
     * а не /screens/{slug} (у dashboard'ов отдельный controller/path).
     *
     * Если хост передал в screen() слаг DashboardScreen — auto-resolve
     * детектирует это и тоже сгенерит /dashboard/{slug}; этот helper
     * существует для явности.
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
     * Сериализация в формат, который ожидает frontend useMenuStore.
     *
     * Если node — auto-resolve, подтягиваем label/url/permissions из
     * registry'ов. Manual-overrides (label/icon/url/...) перебивают auto.
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
            // Лейбл резолвится через переводчик ПРИ СЕРИАЛИЗАЦИИ (per-request,
            // после AdminLocale middleware) — иначе `->label('Клиенты')` брался
            // бы в локали boot'а плагина (i18n меню, BL-11). Строки без перевода
            // возвращаются как есть (ключ = fallback); resource-лейблы уже
            // локализованы Resource::label() и __() к ним идемпотентен.
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
     * Подставляет автоматические значения из registry'ов, если node создан
     * через resource()/screen(). Не перезаписывает уже заданные поля.
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

            // DashboardScreen имеет отдельный URL /dashboard/{slug} (см.
            // router/builder.ts buildDashboardRoute). Custom Screens живут
            // под /screens/{slug}. Auto-detect.
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
