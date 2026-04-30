<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Контракт экспортёра.
 *
 * Реализации:
 *   - CsvExporter (built-in, без deps);
 *   - XlsxExporter (требует openspout/openspout, suggest);
 *   - PdfExporter (требует mpdf/mpdf или dompdf/dompdf — через PdfRenderer).
 *
 * Каждый экспортёр получает iterable $rows (массив или Generator с
 * chunk-обработкой) и список $columns в формате `[name => label]`.
 */
interface Exporter
{
    /**
     * Идентификатор формата ('csv', 'xlsx', 'pdf').
     */
    public function format(): string;

    /**
     * MIME-тип ответа.
     */
    public function mimeType(): string;

    /**
     * Расширение файла (без точки).
     */
    public function extension(): string;

    /**
     * Сформировать StreamedResponse с экспортом.
     *
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $columns  name => label
     */
    public function export(iterable $rows, array $columns, string $filenameWithoutExt): StreamedResponse;
}
