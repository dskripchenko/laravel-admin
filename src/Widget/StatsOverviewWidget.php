<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

/**
 * Набор KPI-карточек: title, value, optional descriptor (icon, trend, color).
 *
 * Каждая карточка — `{label, value, change?, color?, icon?}`. SPA рендерит
 * как горизонтальный grid из stat-cards.
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
     * Добавить одну карточку.
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
     * Тренд (delta) для последней добавленной карточки.
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
