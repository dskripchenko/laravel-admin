<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Menu;

/**
 * Реестр узлов меню. Singleton, биндится в DI как Menu\MenuRegistry.
 *
 * Поддерживает:
 *   - add(MenuNode) — корневой узел
 *   - under($parentKey, MenuNode|MenuNode[]) — добавить child'ов в существующий
 *     родительский узел (рекурсивный поиск по key)
 *   - withAuto(true) — после рендера host-меню добавить недостающие auto-items
 *     (Resources/Screens, не упомянутые ни как key, ни через resource()/screen())
 *
 * Если в registry ничего не зарегистрировано — SystemController::menu()
 * fallback'ается на старую auto-логику. Это даёт backward-compatible default.
 */
final class MenuRegistry
{
    /** @var array<string, list<MenuNode>> panel id => корневые узлы */
    private array $roots = [];

    /** @var array<string, bool> */
    private array $autoFill = [];

    /** @var array<string, list<string>> */
    private array $autoHidden = [];

    /**
     * Панель, в которую пишут registration-методы (ставится Admin/PluginRegistry
     * на время boot'а плагинов панели). Read-методы без аргумента читают её же —
     * для однопанельных хостов это неизменно 'admin' (BC).
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
     * Добавить child'ов в существующий узел (поиск по key, рекурсивно).
     *
     * @param  list<MenuNode>|MenuNode  $children
     */
    public function under(string $parentKey, array|MenuNode $children): self
    {
        $list = is_array($children) ? $children : [$children];
        $parent = self::findByKey($this->roots[$this->activePanel] ?? [], $parentKey);
        if ($parent === null) {
            // Создаём stub-родителя (chain-friendly) — host может потом дополнить.
            $parent = MenuNode::make($parentKey, $parentKey);
            $this->roots[$this->activePanel][] = $parent;
        }
        foreach ($list as $child) {
            $parent->add($child);
        }

        return $this;
    }

    /**
     * Если true (default), SystemController::menu() добавит недостающие
     * auto-items (Resources + custom Screens) после кастомного дерева.
     * Установите false если хотите контролировать всё меню вручную.
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
     * Исключить конкретный resource/screen slug из auto-fill (если withAuto = true).
     * Используется, когда Resource зарегистрирован для API/CRUD, но не должен
     * показываться в sidebar (например, ребёнок встраивается в parent'а).
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
     * Рекурсивный поиск узла по key.
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
