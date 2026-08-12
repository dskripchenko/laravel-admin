<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * Tabs, taking a map of `label => layout|fields[]`.
 *
 * An array value is wrapped into Rows automatically.
 */
final class Tabs extends Layout
{
    /**
     * @param  array<string, Renderable|list<Renderable>>  $tabs
     */
    public static function make(array $tabs = []): self
    {
        $instance = new self;
        $instance->props['labels'] = [];

        foreach ($tabs as $label => $content) {
            $instance->props['labels'][] = $label;
            $instance->children[] = $content instanceof Renderable
                ? $content
                : Rows::make($content);
        }

        return $instance;
    }

    public function type(): string
    {
        return 'tabs';
    }

    public function defaultTab(int $index): self
    {
        $this->props['default'] = $index;

        return $this;
    }

    /**
     * The frontend's TabsLayout expects the items as `[{label, items}]`, so we
     * pair props.labels with the children one to one.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $base = parent::toArray();

        $labels = (array) ($this->props['labels'] ?? []);
        $childArrays = $base['children'] ?? [];

        $tabs = [];
        foreach ($childArrays as $idx => $child) {
            $tabs[] = [
                // Layout::toArray has already translated the labels in the
                // props, but we take them from the raw props, so we localize
                // them here.
                'label' => (string) \Dskripchenko\LaravelAdmin\I18n\Localize::string(
                    (string) ($labels[$idx] ?? ('Tab '.($idx + 1))),
                ),
                // The child has been serialized by Layout::toArray() already and holds its items.
                'items' => $child['items'] ?? ($child['children'] ?? [$child]),
            ];
        }

        $base['items'] = $tabs;

        return $base;
    }
}
