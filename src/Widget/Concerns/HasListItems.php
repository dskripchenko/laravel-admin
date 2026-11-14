<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget\Concerns;

use Dskripchenko\LaravelAdmin\I18n\Localize;

/**
 * The rows of a list without a table header — ListWidget, and the list under
 * the numbers of a StatsOverviewWidget.
 *
 * A row is a caption, an optional secondary text, an optional badge with its
 * tone and an optional link:
 *
 *     ->item('Обращения и претензии', 'старейшее 26 ч', 12, 'danger', '/screens/queue?tab=claims')
 *
 * The tone is a name from the panel's one vocabulary: neutral, info,
 * success, warning, danger (and primary, or a colour word — green, amber,
 * red, gray…). A panel path opens in place, an external address in a new tab.
 */
trait HasListItems
{
    /** @var list<array<string, mixed>> */
    private array $listItems = [];

    private ?string $listEmptyText = null;

    /**
     * Adds a row. `$key` names it for autotests (`list-item-{key}`).
     */
    public function item(
        string $label,
        ?string $secondary = null,
        string|int|float|null $badge = null,
        ?string $tone = null,
        ?string $url = null,
        ?string $key = null,
    ): static {
        $this->listItems[] = array_filter([
            'label' => $label,
            'secondary' => $secondary,
            'badge' => $badge,
            'tone' => $tone,
            'url' => $url,
            'key' => $key,
        ], static fn (mixed $v): bool => $v !== null);

        return $this;
    }

    /**
     * Adds several rows: each `['label' => …, 'secondary' => …, 'badge' => …,
     * 'tone' => …, 'url' => …, 'key' => …]`, everything but the label
     * optional; see item().
     *
     * @param  iterable<array{label: string, secondary?: string|null, badge?: string|int|float|null, tone?: string|null, url?: string|null, key?: string|null}>  $items
     */
    public function items(iterable $items): static
    {
        foreach ($items as $item) {
            $this->item(
                (string) $item['label'],
                $item['secondary'] ?? null,
                $item['badge'] ?? null,
                $item['tone'] ?? null,
                $item['url'] ?? null,
                $item['key'] ?? null,
            );
        }

        return $this;
    }

    /**
     * The text of an empty list; «Нет данных» by default.
     */
    public function emptyText(string $text): static
    {
        $this->listEmptyText = $text;

        return $this;
    }

    /**
     * The rows as the SPA reads them, captions translated per request.
     *
     * @return list<array<string, mixed>>
     */
    protected function listItemsPayload(): array
    {
        return array_map(static function (array $item): array {
            foreach (['label', 'secondary', 'badge'] as $key) {
                if (is_string($item[$key] ?? null)) {
                    $item[$key] = Localize::string($item[$key]);
                }
            }

            return $item;
        }, $this->listItems);
    }

    protected function listEmptyTextPayload(): ?string
    {
        return $this->listEmptyText === null ? null : Localize::string($this->listEmptyText);
    }

    protected function hasListItems(): bool
    {
        return $this->listItems !== [];
    }
}
