<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\I18n\Localize;
use Dskripchenko\LaravelAdmin\Widget\Concerns\HasListItems;

/**
 * A set of KPI cards: a title, a value and an optional descriptor — an icon, a
 * trend, a colour.
 *
 * Each card is `{label, value, change?, color?, icon?}`, and the SPA renders
 * them as a horizontal grid of stat cards — or, with compact(), as numbers in
 * one row inside one card, and with tiles() as small bordered tiles. Rows
 * added with item() make a list under the numbers, in the same card:
 *
 *     StatsOverviewWidget::make()->title('Отложенные процессы')->size(4)->compact()
 *         ->stat('В очереди', 142)
 *         ->stat('Сбои за сутки', 3, 'danger')
 *         ->stat('Зависли > 15 мин', 1, 'warning')
 *         ->item('Выпуск полиса ВЗР', '14:12', 'ошибка КИАС', 'danger', '/r/processes/15');
 */
class StatsOverviewWidget extends Widget
{
    use HasListItems;

    /** @var list<array<string, mixed>> */
    private array $stats = [];

    /** null — cards; 'compact' or 'tiles' (1.75). */
    private ?string $layout = null;

    public function widgetType(): string
    {
        return 'stats';
    }

    /**
     * Adds one card.
     *
     * The colour is a name from the panel's tone vocabulary, the same one a
     * badge, a chart and a gauge take: success, warning, danger, info,
     * primary, neutral, or a colour word — green, amber, yellow, orange, red,
     * blue, gray/grey and the like. The icon is a Lucide name (`users`,
     * `edit`, `shopping-cart`…). `$url` makes the card a link — see url().
     * `$hint` is a line under the number: the age of the oldest item of a queue.
     */
    public function stat(string $label, mixed $value, ?string $color = null, ?string $icon = null, ?string $url = null, ?string $hint = null): static
    {
        $this->stats[] = [
            'label' => $label,
            'value' => $value,
            'color' => $color,
            'icon' => $icon,
            ...($url !== null ? ['url' => $url] : []),
            // A line under the number — «старейшее 26 ч» of a queue (1.74).
            ...($hint !== null ? ['hint' => $hint] : []),
        ];

        return $this;
    }

    /**
     * Makes the card added last a link — to the list behind its number, say,
     * on the dashboard's period:
     *
     *   ->stat('Заявки', $count)->url($this->dashboardContext()->listUrl('/r/requests', 'created_at'))
     *
     * A panel path opens in place, an external address in a new tab.
     */
    public function url(?string $url): static
    {
        return $this->setOnLast('url', $url);
    }

    /**
     * The value of the card added last for the previous period, when the
     * dashboard compares (DashboardContext::comparing()): shown under the
     * number, with the trend computed from the two when trend() is not set.
     */
    public function previous(mixed $value): static
    {
        return $this->setOnLast('previous', $value);
    }

    /**
     * The trend — the delta — of the card added last.
     * direction: 'up' | 'down' | 'flat'. `$unit`: null — per cent of the
     * value; 'pp' — percentage points, for a share (see share()).
     */
    public function trend(float $delta, string $direction = 'up', ?string $unit = null): static
    {
        if ($this->stats !== []) {
            $last = array_key_last($this->stats);
            if ($unit === null && ($this->stats[$last]['share'] ?? false) === true) {
                $unit = 'pp';
            }
            $this->stats[$last]['change'] = ['delta' => $delta, 'direction' => $direction]
                + ($unit !== null ? ['unit' => $unit] : []);
        }

        return $this;
    }

    /**
     * Marks the value of the card added last as a share in per cent: its
     * change against the previous period (previous()) is shown in
     * percentage points — «▼ 0,2 п.п.» for 0.8 % → 0.6 % — instead of per
     * cent of the value («▼ 25 %»). A trend() set on such a card is in
     * percentage points too. The value itself is formatted as before; add
     * suffix('%') for the sign.
     *
     *     ->stat('Сбои оформления', 0.6, 'danger')->suffix('%')->share()->previous(0.8)
     */
    public function share(): static
    {
        if ($this->stats !== []) {
            $last = array_key_last($this->stats);
            $this->stats[$last]['share'] = true;
            if (isset($this->stats[$last]['change']) && is_array($this->stats[$last]['change'])) {
                $this->stats[$last]['change']['unit'] ??= 'pp';
            }
        }

        return $this;
    }

    /**
     * The tone of the hint of the card added last (stat(..., hint: …)): with
     * one the hint is drawn as a badge — the age of a queue's oldest item in
     * red, say. A name from the panel's tone vocabulary.
     */
    public function hintTone(?string $tone): static
    {
        return $this->setOnLast('hintTone', $tone);
    }

    /**
     * The numbers in one row inside one card, without a card of their own
     * and without a minimum width: a caption above, the number below, tinted
     * by the card's colour when it is success, warning or danger. Three
     * numbers fit a column of 4/12. Rows added with item() follow under them.
     */
    public function compact(bool $compact = true): static
    {
        $this->layout = $compact ? 'compact' : null;

        return $this;
    }

    /**
     * The numbers as small bordered tiles — a caption, the number large, and
     * the hint next to it (a badge with hintTone()) — each a link when it has
     * a url(): the tiles of the queues on a support desk.
     */
    public function tiles(bool $tiles = true): static
    {
        $this->layout = $tiles ? 'tiles' : null;

        return $this;
    }

    /**
     * Shows the value of the card added last as money in that currency (an
     * ISO 4217 code), formatted by the panel's locale — its separators and
     * the currency's sign: `$1,591,285` in English, `1 591 285 $` in
     * Russian. Pass the raw number to stat(), not a formatted string.
     */
    public function money(string $currency = 'USD', int $decimals = 0): static
    {
        return $this->setOnLast('format', [
            'style' => 'currency',
            'currency' => strtoupper($currency),
            'decimals' => max(0, $decimals),
        ]);
    }

    /**
     * The decimals of the value of the card added last; its number is
     * formatted by the panel's locale either way.
     */
    public function precision(int $decimals): static
    {
        return $this->setOnLast('precision', max(0, $decimals));
    }

    /**
     * A text before the value of the card added last.
     */
    public function prefix(string $prefix): static
    {
        return $this->setOnLast('prefix', $prefix);
    }

    /**
     * A text after the value of the card added last — a unit.
     */
    public function suffix(string $suffix): static
    {
        return $this->setOnLast('suffix', $suffix);
    }

    private function setOnLast(string $key, mixed $value): static
    {
        if ($this->stats !== []) {
            $this->stats[array_key_last($this->stats)][$key] = $value;
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        // Translated per request, like every other caption: the widget may be
        // built once and served in several locales.
        $data = ['stats' => array_map(static function (array $stat): array {
            if (is_string($stat['label'] ?? null)) {
                $stat['label'] = Localize::string($stat['label']);
            }
            if (is_string($stat['hint'] ?? null)) {
                $stat['hint'] = Localize::string($stat['hint']);
            }

            return $stat;
        }, $this->stats)];
        // The keys of 1.75 only when used: the payload of a plain set of
        // cards stays as it was.
        if ($this->layout !== null) {
            $data['layout'] = $this->layout;
        }
        if ($this->hasListItems() || $this->listEmptyTextPayload() !== null) {
            $data['items'] = $this->listItemsPayload();
            $data['emptyText'] = $this->listEmptyTextPayload();
        }

        return $data;
    }
}
