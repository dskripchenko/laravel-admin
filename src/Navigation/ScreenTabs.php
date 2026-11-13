<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Navigation;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Layout\Tabs;
use Dskripchenko\LaravelAdmin\Screen\Screen;

/**
 * The tabs of a screen that the address can open — Tabs::persistInQuery() —
 * for the ⌘K palette: «Заявки › CRM» is `?tab=crm`, a nested one carries its
 * parent's key as well (`?tab=crm&crm-tab=pending`).
 *
 * The screen's layout() is built without its query(): a layout that reads the
 * state may throw, and then the screen simply has no tabs to offer.
 */
final class ScreenTabs
{
    /**
     * @return list<array{label: string, trail: list<string>, query: array<string, string>}>
     */
    public static function of(Screen $screen): array
    {
        $out = [];
        foreach ($screen->layout() as $layout) {
            if ($layout->isVisible()) {
                self::walk($layout, [], [], $out);
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $trail
     * @param  array<string, string>  $query
     * @param  list<array{label: string, trail: list<string>, query: array<string, string>}>  $out
     */
    private static function walk(Renderable $node, array $trail, array $query, array &$out): void
    {
        if ($node instanceof Tabs && $node->queryParameter() !== null) {
            foreach ($node->visibleTabs() as $tab) {
                $q = [...$query, $node->queryParameter() => $tab['key']];
                $t = [...$trail, $tab['label']];
                $out[] = ['label' => $tab['label'], 'trail' => $t, 'query' => $q];
                self::walk($tab['content'], $t, $q, $out);
            }

            return;
        }
        if ($node instanceof Layout) {
            foreach ($node->childRenderables() as $child) {
                if ($child->isVisible()) {
                    self::walk($child, $trail, $query, $out);
                }
            }
        }
    }
}
