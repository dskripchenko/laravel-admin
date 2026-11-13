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

    /** @var array<string, mixed> tab key (or index) => a badge, a closure or ['value' =>, 'tone' =>] */
    private array $badges = [];

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
     * A search over the fields of every tab above them — a long settings
     * screen: it finds a field by its label, hint, placeholder or key; each
     * tab shows how many it finds, a tab without any is dimmed, the open tab
     * hides the fields it does not find and the blocks left empty. Nested
     * tab sets count their finds too.
     */
    public function searchable(?string $placeholder = null): self
    {
        $this->props['searchable'] = true;
        if ($placeholder !== null) {
            $this->props['searchPlaceholder'] = \Dskripchenko\LaravelAdmin\I18n\Localize::string($placeholder);
        }

        return $this;
    }

    /**
     * Badges on the tabs — a queue's count, say — by the tab's key (keys())
     * or index: a number, a text, a closure computed when the screen is sent
     * (and only for a tab the user sees), or `['value' => 81, 'tone' => 'danger']`.
     * A closure may return the same shapes, or null for no badge.
     *
     *   Layout::tabs([...])->keys(['pending', 'all'])->badges([
     *       'pending' => fn () => ['value' => ClaimResource::make()->countIn('pending'), 'tone' => 'danger'],
     *   ]);
     *
     * @param  array<int|string, mixed>  $badges
     */
    public function badges(array $badges): self
    {
        foreach ($badges as $key => $badge) {
            $this->badges[(string) $key] = $badge;
        }

        return $this;
    }

    /** One tab's badge — see badges(). */
    public function badge(string|int $tab, mixed $badge, ?string $tone = null): self
    {
        $this->badges[(string) $tab] = $tone === null ? $badge : ['value' => $badge, 'tone' => $tone];

        return $this;
    }

    /** The address key of the open tab (persistInQuery()); null when it is kept locally. */
    public function queryParameter(): ?string
    {
        $key = $this->props['queryKey'] ?? null;

        return is_string($key) ? $key : null;
    }

    /**
     * The tabs the user sees, with their address names and labels — for the
     * palette's search of the screens' tabs.
     *
     * @return list<array{key: string, label: string, content: Renderable}>
     */
    public function visibleTabs(): array
    {
        $labels = array_map('strval', (array) ($this->props['labels'] ?? []));
        $keys = $this->resolveKeys($labels, count($this->children));
        $out = [];
        foreach ($this->children as $position => $child) {
            if (! $child->isVisible()) {
                continue;
            }
            $out[] = [
                'key' => $keys[$position],
                'label' => (string) \Dskripchenko\LaravelAdmin\I18n\Localize::string($labels[$position] ?? ('Tab '.($position + 1))),
                'content' => $child,
            ];
        }

        return $out;
    }

    /**
     * A badge's value and tone, a closure computed. One that throws shows nothing.
     *
     * @return array{0: string|int|null, 1: string|null}
     */
    private function resolveBadge(mixed $badge): array
    {
        if ($badge instanceof \Closure) {
            try {
                $badge = $badge();
            } catch (\Throwable $e) {
                report($e);

                return [null, null];
            }
        }
        $tone = null;
        if (is_array($badge)) {
            $tone = isset($badge['tone']) && is_string($badge['tone']) ? $badge['tone'] : null;
            $badge = $badge['value'] ?? null;
        }
        if (is_float($badge)) {
            $badge = (int) $badge;
        }

        return is_int($badge) || is_string($badge) ? [$badge, $tone] : [null, null];
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

        $badgeKeys = $this->badges === [] ? null : $this->resolveKeys(array_map('strval', $labels), count($this->children));

        $tabs = [];
        foreach ($childArrays as $idx => $child) {
            $position = $positions[$idx] ?? $idx;
            $badge = null;
            $tone = null;
            if ($badgeKeys !== null) {
                $declared = $this->badges[$badgeKeys[$position]] ?? $this->badges[(string) $position] ?? null;
                if ($declared !== null) {
                    [$badge, $tone] = $this->resolveBadge($declared);
                }
            }
            $tabs[] = [
                ...($keys !== null ? ['key' => $keys[$position]] : []),
                ...($badge !== null ? ['badge' => $badge, 'badgeTone' => $tone] : []),
                // Layout::toArray has already translated the labels in the
                // props, but we take them from the raw props, so we localize
                // them here.
                'label' => (string) \Dskripchenko\LaravelAdmin\I18n\Localize::string(
                    (string) ($labels[$position] ?? ('Tab '.($position + 1))),
                ),
                // The child has been serialized by Layout::toArray() already.
                // Only a Rows wrapper — what make() builds from a list — is
                // unwrapped into its items; any other node is the tab's one
                // item. A resourceIndex, nested tabs or a block used to be
                // replaced by their (empty) list of children, which left the
                // tab blank; a listener would lose its reactivity.
                'items' => ($child['type'] ?? null) === 'rows'
                    ? ($child['items'] ?? ($child['children'] ?? []))
                    : [$child],
            ];
        }

        $base['items'] = $tabs;

        return $base;
    }
}
