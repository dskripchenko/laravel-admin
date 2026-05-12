<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * Табы. Принимает map `label => layout|fields[]`.
 *
 * Если значение — массив, оборачивается в Rows автоматически.
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
     * Frontend TabsLayout ожидает items в форме `[{label, items}]` —
     * собираем из props.labels + children один-в-один.
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
                'label' => (string) ($labels[$idx] ?? ('Tab '.($idx + 1))),
                // child уже сериализован Layout::toArray() — внутри есть items.
                'items' => $child['items'] ?? ($child['children'] ?? [$child]),
            ];
        }

        $base['items'] = $tabs;

        return $base;
    }
}
