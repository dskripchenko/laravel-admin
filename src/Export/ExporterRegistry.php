<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export;

use InvalidArgumentException;

/**
 * Registry экспортёров по format-key.
 *
 * Default содержит CsvExporter; XlsxExporter / PdfExporter регистрируются
 * conditionally в AdminServiceProvider при наличии соответствующих
 * composer-пакетов (openspout, mpdf, dompdf).
 */
final class ExporterRegistry
{
    /** @var array<string, Exporter> format => Exporter */
    private array $exporters = [];

    public function add(Exporter $exporter): void
    {
        $this->exporters[$exporter->format()] = $exporter;
    }

    public function get(string $format): Exporter
    {
        if (! isset($this->exporters[$format])) {
            throw new InvalidArgumentException(
                "Exporter for format `{$format}` is not registered. Available: ".implode(', ', $this->formats()),
            );
        }

        return $this->exporters[$format];
    }

    /**
     * @return list<string>
     */
    public function formats(): array
    {
        return array_keys($this->exporters);
    }

    public function has(string $format): bool
    {
        return isset($this->exporters[$format]);
    }

    public function clear(): void
    {
        $this->exporters = [];
    }
}
