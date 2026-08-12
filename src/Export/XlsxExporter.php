<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The XLSX export, through openspout.
 *
 * AdminServiceProvider registers it only when the `openspout/openspout`
 * package is installed; without it this class is never loaded and the format
 * list holds csv alone.
 *
 * openspout writes in a stream, so it scales to hundreds of thousands of rows
 * without a noticeable memory footprint.
 */
final class XlsxExporter implements Exporter
{
    public function format(): string
    {
        return 'xlsx';
    }

    public function mimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function extension(): string
    {
        return 'xlsx';
    }

    public function export(iterable $rows, array $columns, string $filenameWithoutExt): StreamedResponse
    {
        if (! class_exists(Writer::class)) {
            throw new RuntimeException(
                'XLSX export requires openspout/openspout — composer require openspout/openspout',
            );
        }

        $filename = $filenameWithoutExt.'.'.$this->extension();

        return new StreamedResponse(
            function () use ($rows, $columns): void {
                $writer = new Writer;
                $writer->openToFile('php://output');

                $writer->addRow(Row::fromValues(array_values($columns)));

                foreach ($rows as $row) {
                    $values = [];
                    foreach (array_keys($columns) as $col) {
                        $value = data_get($row, $col);
                        $values[] = is_scalar($value) || $value === null
                            ? $value
                            : json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                    $writer->addRow(Row::fromValues($values));
                }

                $writer->close();
            },
            200,
            [
                'Content-Type' => $this->mimeType(),
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }
}
