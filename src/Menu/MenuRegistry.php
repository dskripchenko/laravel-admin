<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Menu;

/**
 * The registry of menu nodes. A singleton, bound in the container as
 * Menu\MenuRegistry.
 *
 * It supports:
 *   - add(MenuNode) for a root node
 *   - under($parentKey, MenuNode|MenuNode[]) to add children to an existing
 *     parent, found recursively by key
 *   - withAuto(true) to append, after the host's menu, the auto items that are
 *     missing — the resources and screens mentioned neither by key nor through
 *     resource() or screen()
 *
 * When nothing is registered at all, SystemController::menu() falls back to the
 * older automatic logic, which keeps the default backward-compatible.
 */
final class MenuRegistry
{
    /** @var array<string, list<MenuNode>> panel id => its root nodes */
    private array $roots = [];

    /** @var array<string, bool> */
    private array $autoFill = [];

    /** @var array<string, list<string>> */
    private array $autoHidden = [];

    /**
     * The panel the registration methods write into; Admin and PluginRegistry
     * set it while booting that panel's plugins. The read methods without an
     * argument read the same one, which for a single-panel host is always
     * 'admin'.
     */
    private string $activePanel = 'admin';

    public function setActivePanel(string $panel): self
    {
        $this->activePanel = $panel;

        return $this;
    }

    public function activePanel(): string
    {
        return $this->activePanel;
    }

    public function add(MenuNode $node): self
    {
        $this->roots[$this->activePanel][] = $node;

        return $this;
    }

    /**
     * Adds children to an existing node, found by key, recursively.
     *
     * @param  list<MenuNode>|MenuNode  $children
     */
    public function under(string $parentKey, array|MenuNode $children): self
    {
        $list = is_array($children) ? $children : [$children];
        $parent = self::findByKey($this->roots[$this->activePanel] ?? [], $parentKey);
        if ($parent === null) {
            // Create a stub parent, chain-friendly, which the host can fill in later.
            $parent = MenuNode::make($parentKey, $parentKey);
            $this->roots[$this->activePanel][] = $parent;
        }
        foreach ($list as $child) {
            $parent->add($child);
        }

        return $this;
    }

    /**
     * When true, the default, SystemController::menu() appends the missing
     * auto items — the resources and the custom screens — after the custom
     * tree. Set it to false to control the whole menu by hand.
     */
    public function withAuto(bool $enabled = true): self
    {
        $this->autoFill[$this->activePanel] = $enabled;

        return $this;
    }

    public function autoFillEnabled(?string $panel = null): bool
    {
        return $this->autoFill[$panel ?? $this->activePanel] ?? true;
    }

    /**
     * Excludes a particular resource or screen slug from the auto-fill, when
     * withAuto is true. Useful when a resource is registered for the API and
     * CRUD but should not appear in the sidebar — a child embedded into its
     * parent, for one.
     */
    public function hideAuto(string $slug): self
    {
        $panel = $this->activePanel;
        if (! in_array($slug, $this->autoHidden[$panel] ?? [], true)) {
            $this->autoHidden[$panel][] = $slug;
        }

        return $this;
    }

    /** @return list<string> */
    public function autoHiddenSlugs(?string $panel = null): array
    {
        return $this->autoHidden[$panel ?? $this->activePanel] ?? [];
    }

    /** @return list<MenuNode> */
    public function roots(?string $panel = null): array
    {
        return $this->roots[$panel ?? $this->activePanel] ?? [];
    }

    public function isEmpty(?string $panel = null): bool
    {
        return $this->roots($panel) === [];
    }

    public function clear(): self
    {
        $this->roots = [];
        $this->autoFill = [];
        $this->autoHidden = [];
        $this->activePanel = 'admin';

        return $this;
    }

    /**
     * Finds a node by key, recursively.
     *
     * @param  list<MenuNode>  $nodes
     */
    private static function findByKey(array $nodes, string $key): ?MenuNode
    {
        foreach ($nodes as $node) {
            if ($node->key() === $key) {
                return $node;
            }
            $found = self::findByKey($node->getChildren(), $key);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
