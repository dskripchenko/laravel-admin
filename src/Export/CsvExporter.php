<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The CSV exporter, built on fputcsv, with no external dependencies.
 *
 * Its parameters come from config('admin.exports.csv'): the delimiter, `;` by
 * default, the enclosure, and the UTF-8 BOM for Excel.
 */
final class CsvExporter implements Exporter
{
    public function format(): string
    {
        return 'csv';
    }

    public function mimeType(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function export(iterable $rows, array $columns, string $filenameWithoutExt): StreamedResponse
    {
        $delimiter = (string) config('admin.exports.csv.delimiter', ',');
        $enclosure = (string) config('admin.exports.csv.enclosure', '"');
        $bom = (bool) config('admin.exports.csv.bom', true);
        $filename = $filenameWithoutExt.'.'.$this->extension();

        return new StreamedResponse(
            function () use ($rows, $columns, $delimiter, $enclosure, $bom): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }
                if ($bom) {
                    fwrite($handle, "\xEF\xBB\xBF");
                }

                fputcsv($handle, array_values($columns), $delimiter, $enclosure, '\\');

                foreach ($rows as $row) {
                    $line = [];
                    foreach (array_keys($columns) as $col) {
                        $value = data_get($row, $col);
                        $line[] = is_scalar($value) || $value === null
                            ? (string) $value
                            : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                    fputcsv($handle, $line, $delimiter, $enclosure, '\\');
                }

                fclose($handle);
            },
            200,
            [
                'Content-Type' => $this->mimeType(),
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }
}
