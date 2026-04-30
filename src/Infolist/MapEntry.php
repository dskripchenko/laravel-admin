<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Карта (lat/lng). Конкретный provider — в SPA (Yandex/OSM/Leaflet).
 */
final class MapEntry extends Entry
{
    public function entryType(): string
    {
        return 'map';
    }

    public function latColumn(string $column): static
    {
        $this->attributes['latColumn'] = $column;

        return $this;
    }

    public function lngColumn(string $column): static
    {
        $this->attributes['lngColumn'] = $column;

        return $this;
    }

    public function zoom(int $zoom): static
    {
        $this->attributes['zoom'] = $zoom;

        return $this;
    }

    public function height(int|string $height): static
    {
        $this->attributes['height'] = $height;

        return $this;
    }
}
