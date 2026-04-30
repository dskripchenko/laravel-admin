<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * Аккордеон — коллекция collapsible секций.
 *
 * Items хранятся как list<{title, children, defaultOpen?}>. По умолчанию все
 * закрыты; multi-mode позволяет открыть несколько одновременно (default: single).
 */
final class Accordion extends Layout
{
    /**
     * @param  array<string, Renderable|list<Renderable>>  $sections  title => content
     */
    public static function make(array $sections = []): self
    {
        $instance = new self;
        foreach ($sections as $title => $content) {
            $instance->section((string) $title, is_array($content) ? $content : [$content]);
        }

        return $instance;
    }

    public function type(): string
    {
        return 'accordion';
    }

    /**
     * @param  list<Renderable>  $children
     */
    public function section(string $title, array $children, bool $defaultOpen = false): self
    {
        $items = $this->props['sections'] ?? [];
        $items[] = [
            'title' => $title,
            'defaultOpen' => $defaultOpen,
            'children' => array_map(static fn (Renderable $r): array => $r->toArray(), $children),
        ];
        $this->props['sections'] = $items;

        return $this;
    }

    public function multi(bool $multi = true): self
    {
        $this->props['multi'] = $multi;

        return $this;
    }
}
