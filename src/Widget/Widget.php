<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;
use Illuminate\Support\Str;

/**
 * Абстрактный widget — компонент Dashboard'а.
 *
 * Каждый widget имеет:
 *   - `slug` (по умолчанию kebab-class-basename без 'Widget' suffix);
 *   - `widgetType()` — UI-тип (stats/chart/table/markdown/...);
 *   - `data()` — payload для SPA, может быть computed lazy через
 *      data-endpoint (см. WidgetController в P8.3);
 *   - `view()` — конфиг отображения (size, refresh interval, ...).
 *
 * Permission gating и size — общие для всех виджетов.
 *
 * @phpstan-consistent-constructor
 */
abstract class Widget implements Renderable
{
    /**
     * Размер на dashboard-сетке (1..12 колонок).
     */
    protected int $size = 6;

    protected ?string $title = null;

    protected ?int $refreshSeconds = null;

    /** @var list<string>|string|null */
    protected array|string|null $permission = null;

    /** @var bool|callable(): bool */
    protected $visibility = true;

    /**
     * UI-тип виджета — stats/chart/recent_list/table/markdown/iframe/heatmap/gauge.
     */
    abstract public function widgetType(): string;

    /**
     * Computed payload — основное содержимое виджета.
     *
     * Может бросать или возвращать пустой массив, если данные нужно лениво
     * загрузить через WidgetController.fetch.
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
     * Размер на dashboard-сетке: 1..12 (12 = full width).
     */
    public function size(int $columns): static
    {
        $this->size = max(1, min($columns, 12));

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
            'title' => $this->title,
            'size' => $this->size,
            'refresh' => $this->refreshSeconds,
            'permission' => $this->permission,
            'data' => $this->data(),
        ];
    }
}
