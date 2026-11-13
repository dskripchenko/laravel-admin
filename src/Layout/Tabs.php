<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Tabs, taking a map of `label => layout|fields[]`.
 *
 * An array value is wrapped into Rows automatically. A tab that is not open
 * is not rendered, so what it holds — a live list — loads only when opened.
 *
 *   Layout::tabs([
 *       'Договоры' => Layout::resourceIndex(ContractResource::class)->queryKey('contracts'),
 *       'Котировки' => Layout::resourceIndex(QuoteResource::class)->queryKey('quotes'),
 *   ])->persistInQuery('tab')->keys(['contracts', 'quotes']);
 *
 * `persistInQuery()` keeps the open tab in the address (`?tab=quotes`) so a
 * link leads to it; `vertical()` puts the tabs in a column on the left.
 */
final class Tabs extends Layout
{
    /** @var list<string> The tabs' names in the address, set by keys(). */
    private array $tabKeys = [];

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
     * Keeps the open tab in the address under `$key` (`?tab=quotes`): a link
     * opens that tab, back and forward follow it. The tabs are named by
     * keys() or, without them, by their labels made URL-safe.
     */
    public function persistInQuery(string $key = 'tab'): self
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $key) !== 1) {
            throw new InvalidArgumentException("Tabs::persistInQuery() expects letters, digits, _, . or -, starting with a letter, got '{$key}'");
        }
        $this->props['queryKey'] = $key;

        return $this;
    }

    /**
     * The tabs' names in the address, in their order: `['contracts', 'quotes']`.
     *
     * @param  list<string>  $keys
     */
    public function keys(array $keys): self
    {
        $this->tabKeys = array_map('strval', $keys);

        return $this;
    }

    /**
     * The tabs in a column on the left with the open one beside them — for a
     * screen of many small lists, such as dictionaries.
     */
    public function vertical(bool $vertical = true): self
    {
        if ($vertical) {
            $this->props['orientation'] = 'vertical';
        } else {
            unset($this->props['orientation']);
        }

        return $this;
    }

    /**
     * Each tab's name in the address: keys() first, then the label made
     * URL-safe, then `tab-N`; repeats get a suffix.
     *
     * @param  list<string>  $labels
     * @return list<string>
     */
    private function resolveKeys(array $labels, int $count): array
    {
        $keys = [];
        $seen = [];
        for ($i = 0; $i < $count; $i++) {
            $key = $this->tabKeys[$i] ?? Str::slug((string) ($labels[$i] ?? ''));
            if ($key === '') {
                $key = 'tab-'.($i + 1);
            }
            $base = $key;
            $n = 2;
            while (isset($seen[$key])) {
                $key = $base.'-'.$n++;
            }
            $seen[$key] = true;
            $keys[] = $key;
        }

        return $keys;
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

        // A tab whose content the user may not see (a list without the view
        // permission) is left out by Layout::toArray(); the labels and keys
        // follow the tabs that remain, not their positions.
        $positions = [];
        foreach ($this->children as $position => $child) {
            if ($child->isVisible()) {
                $positions[] = $position;
            }
        }

        // Named tabs only when asked for: an unnamed tab is known by its index,
        // as before.
        $keys = isset($this->props['queryKey']) || $this->tabKeys !== []
            ? $this->resolveKeys(array_map('strval', $labels), count($this->children))
            : null;

        $tabs = [];
        foreach ($childArrays as $idx => $child) {
            $position = $positions[$idx] ?? $idx;
            $tabs[] = [
                ...($keys !== null ? ['key' => $keys[$position]] : []),
                // Layout::toArray has already translated the labels in the
                // props, but we take them from the raw props, so we localize
                // them here.
                'label' => (string) \Dskripchenko\LaravelAdmin\I18n\Localize::string(
                    (string) ($labels[$position] ?? ('Tab '.($position + 1))),
                ),
                // The child has been serialized by Layout::toArray() already and holds its items.
                // A listener keeps its own node: flattening it would drop
                // the reactivity along with the wrapper.
                'items' => ($child['type'] ?? null) === 'listener'
                    ? [$child]
                    : ($child['items'] ?? ($child['children'] ?? [$child])),
            ];
        }

        $base['items'] = $tabs;

        return $base;
    }
}
