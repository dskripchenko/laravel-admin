<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The contract of an exporter.
 *
 * The implementations:
 *   - CsvExporter, built in, with no dependencies;
 *   - XlsxExporter, which needs openspout/openspout;
 *   - PdfExporter, which needs mpdf/mpdf or dompdf/dompdf, through a
 *     PdfRenderer.
 *
 * Every exporter receives an iterable of $rows — an array, or a generator
 * working in chunks — and the $columns as `[name => label]`.
 */
interface Exporter
{
    /**
     * The format's identifier: 'csv', 'xlsx', 'pdf'.
     */
    public function format(): string;

    /**
     * The response's MIME type.
     */
    public function mimeType(): string;

    /**
     * The file extension, without the dot.
     */
    public function extension(): string;

    /**
     * Builds the StreamedResponse carrying the export.
     *
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $columns  name => label
     */
    public function export(iterable $rows, array $columns, string $filenameWithoutExt): StreamedResponse;
}
