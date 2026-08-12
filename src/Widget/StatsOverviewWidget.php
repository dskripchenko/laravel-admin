<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

/**
 * A set of KPI cards: a title, a value and an optional descriptor — an icon, a
 * trend, a colour.
 *
 * Each card is `{label, value, change?, color?, icon?}`, and the SPA renders
 * them as a horizontal grid of stat cards.
 */
class StatsOverviewWidget extends Widget
{
    /** @var list<array<string, mixed>> */
    private array $stats = [];

    public function widgetType(): string
    {
        return 'stats';
    }

    /**
     * Adds one card.
     */
    public function stat(string $label, mixed $value, ?string $color = null, ?string $icon = null): static
    {
        $this->stats[] = [
            'label' => $label,
            'value' => $value,
            'color' => $color,
            'icon' => $icon,
        ];

        return $this;
    }

    /**
     * The trend — the delta — of the card added last.
     * direction: 'up' | 'down' | 'flat'.
     */
    public function trend(float $delta, string $direction = 'up'): static
    {
        if ($this->stats !== []) {
            $last = array_key_last($this->stats);
            $this->stats[$last]['change'] = ['delta' => $delta, 'direction' => $direction];
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return ['stats' => $this->stats];
    }
}
