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
    /** @var list<MenuNode> */
    private array $roots = [];

    private bool $autoFill = true;

    /** @var list<string> */
    private array $autoHidden = [];

    public function add(MenuNode $node): self
    {
        $this->roots[] = $node;

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
        $parent = self::findByKey($this->roots, $parentKey);
        if ($parent === null) {
            // Создаём stub-родителя (chain-friendly) — host может потом дополнить.
            $parent = MenuNode::make($parentKey, $parentKey);
            $this->roots[] = $parent;
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
        $this->autoFill = $enabled;

        return $this;
    }

    public function autoFillEnabled(): bool
    {
        return $this->autoFill;
    }

    /**
     * Исключить конкретный resource/screen slug из auto-fill (если withAuto = true).
     * Используется, когда Resource зарегистрирован для API/CRUD, но не должен
     * показываться в sidebar (например, ребёнок встраивается в parent'а).
     */
    public function hideAuto(string $slug): self
    {
        if (! in_array($slug, $this->autoHidden, true)) {
            $this->autoHidden[] = $slug;
        }

        return $this;
    }

    /** @return list<string> */
    public function autoHiddenSlugs(): array
    {
        return $this->autoHidden;
    }

    /** @return list<MenuNode> */
    public function roots(): array
    {
        return $this->roots;
    }

    public function isEmpty(): bool
    {
        return $this->roots === [];
    }

    public function clear(): self
    {
        $this->roots = [];
        $this->autoFill = true;
        $this->autoHidden = [];

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
