<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Import;

use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Читает первые N rows из CSV/XLSX-файла для preview-шага wizard'а.
 *
 * Возвращает headers + sample rows + total estimated count (для CSV — точный,
 * для XLSX — `null`, чтобы избежать чтения всего файла).
 */
final class ImportPreviewService
{
    public function __construct(private readonly int $sampleSize = 20) {}

    /**
     * @return array{headers: list<string>, sample: list<array<string, mixed>>, total: int|null, format: string}
     */
    public function preview(string $diskName, string $path): array
    {
        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            throw new RuntimeException("Import file not found: {$path}");
        }

        $localPath = $disk->path($path);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv', 'tsv', 'txt' => $this->previewCsv($localPath, $extension),
            'xlsx' => $this->previewXlsx($localPath),
            default => throw new RuntimeException(
                "Unsupported import file extension: `{$extension}`. Use csv, tsv or xlsx.",
            ),
        };
    }

    /**
     * @return array{headers: list<string>, sample: list<array<string, mixed>>, total: int|null, format: string}
     */
    private function previewCsv(string $localPath, string $extension): array
    {
        $delimiter = $extension === 'tsv' ? "\t" : self::sniffCsvDelimiter($localPath);

        $handle = fopen($localPath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open file `{$localPath}`");
        }

        // Strip BOM если есть.
        $first = fread($handle, 3);
        if ($first !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = self::stringList(fgetcsv($handle, 0, $delimiter, '"', '\\'));
        $sample = [];
        $total = 0;

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $total++;
            if (count($sample) < $this->sampleSize) {
                $sample[] = self::combine($headers, self::stringList($row));
            }
        }
        fclose($handle);

        return [
            'headers' => $headers,
            'sample' => $sample,
            'total' => $total,
            'format' => 'csv',
        ];
    }

    /**
     * @return array{headers: list<string>, sample: list<array<string, mixed>>, total: int|null, format: string}
     */
    private function previewXlsx(string $localPath): array
    {
        if (! class_exists(XlsxReader::class)) {
            throw new RuntimeException(
                'XLSX preview requires openspout/openspout — composer require openspout/openspout',
            );
        }

        $reader = new XlsxReader;
        $reader->open($localPath);

        $headers = [];
        $sample = [];
        $count = 0;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                /** @var \OpenSpout\Common\Entity\Row $row */
                $values = array_map(
                    static fn ($cell): string => (string) $cell->getValue(),
                    $row->getCells(),
                );
                if ($rowIndex === 1) {
                    $headers = array_values(array_filter($values, static fn ($v): bool => $v !== ''));

                    continue;
                }
                $count++;
                if (count($sample) < $this->sampleSize) {
                    $sample[] = self::combine($headers, $values);
                }
            }
            break; // только первый sheet
        }
        $reader->close();

        return [
            'headers' => $headers,
            'sample' => $sample,
            'total' => $count > 0 ? $count : null,
            'format' => 'xlsx',
        ];
    }

    /**
     * Определяем delimiter по first non-empty line (auto-detect между ',', ';', '\t').
     */
    private static function sniffCsvDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ',';
        }
        $first = fread($handle, 3);
        if ($first !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        $line = fgets($handle);
        fclose($handle);
        if (! is_string($line)) {
            return ',';
        }

        $candidates = [',', ';', "\t", '|'];
        $best = ',';
        $bestCount = 0;
        foreach ($candidates as $cand) {
            $count = substr_count($line, $cand);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $cand;
            }
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $row): array
    {
        if (! is_array($row)) {
            return [];
        }

        return array_values(array_map(
            static fn ($v): string => is_scalar($v) ? (string) $v : '',
            $row,
        ));
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $values
     * @return array<string, mixed>
     */
    private static function combine(array $headers, array $values): array
    {
        $combined = [];
        foreach ($headers as $i => $header) {
            $combined[$header] = $values[$i] ?? null;
        }

        return $combined;
    }
}
