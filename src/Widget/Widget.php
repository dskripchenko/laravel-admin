<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;
use Illuminate\Support\Str;

/**
 * The abstract widget — a component of a dashboard.
 *
 * Every widget has:
 *   - a `slug`, by default the kebab-cased class basename without the 'Widget'
 *     suffix;
 *   - a `widgetType()` — the UI type: stats, chart, table, markdown and so on;
 *   - a `data()` — the payload for the SPA, which may be computed lazily
 *     through the data endpoint;
 *   - a `view()` — the display configuration: the size, the refresh interval
 *     and the rest.
 *
 * The permission gating and the size are common to every widget.
 *
 * @phpstan-consistent-constructor
 */
abstract class Widget implements Renderable
{
    /**
     * The size on the dashboard's grid, in columns, 1..12.
     */
    protected int $size = 6;

    /**
     * The height on the dashboard's grid, in rows, 1..6; null lets the frontend pick by type.
     */
    protected ?int $rowSpan = null;

    protected ?string $title = null;

    protected ?int $refreshSeconds = null;

    /** @var list<string>|string|null */
    protected array|string|null $permission = null;

    /** @var bool|callable(): bool */
    protected $visibility = true;

    /**
     * The widget's UI type: stats, chart, recent_list, table, markdown, iframe, heatmap or gauge.
     */
    abstract public function widgetType(): string;

    /**
     * The computed payload — the widget's actual content.
     *
     * It may throw, or return an empty array, when the data is to be loaded
     * lazily through WidgetController.fetch.
     *
     * @return array<string, mixed>
     */
    abstract public function data(): array;

    public static function make(): static
    {
        return new static;
    }

    public static function slug(): string
    {
        $base = class_basename(static::class);
        if (str_ends_with($base, 'Widget')) {
            $base = substr($base, 0, -strlen('Widget'));
        }

        return Str::kebab($base);
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * The size on the dashboard's grid: 1..12, where 12 is the full width.
     */
    public function size(int $columns): static
    {
        $this->size = max(1, min($columns, 12));

        return $this;
    }

    /**
     * The height on the dashboard's grid: 1..6, where 1 is about 140px, 2
     * about 296px and so on. Left unset, the frontend picks by type: 2 for a
     * chart, 1 for a stat.
     */
    public function rowSpan(int $rows): static
    {
        $this->rowSpan = max(1, min($rows, 6));

        return $this;
    }

    public function refresh(int $seconds): static
    {
        $this->refreshSeconds = $seconds;

        return $this;
    }

    /**
     * @param  list<string>|string|null  $permission
     */
    public function permission(array|string|null $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * @return list<string>|string|null
     */
    public function getPermission(): array|string|null
    {
        return $this->permission;
    }

    /**
     * @param  bool|callable(): bool  $cond
     */
    public function canSee(bool|callable $cond): static
    {
        $this->visibility = $cond;

        return $this;
    }

    public function isVisible(): bool
    {
        return is_callable($this->visibility)
            ? (bool) ($this->visibility)()
            : (bool) $this->visibility;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => 'widget',
            'slug' => static::slug(),
            'type' => $this->widgetType(),
            'title' => is_string($this->title) ? \Dskripchenko\LaravelAdmin\I18n\Localize::string($this->title) : $this->title,
            'size' => $this->size,
            'rowSpan' => $this->rowSpan,
            'refresh' => $this->refreshSeconds,
            'permission' => $this->permission,
            'data' => $this->data(),
        ];
    }
}
