<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export;

use Dskripchenko\LaravelAdmin\Export\Pdf\PdfRenderer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF-экспорт через PdfRenderer (MpdfRenderer / DompdfRenderer / custom).
 *
 * HTML собирается простой таблицей; для кастомного шаблона — реализовать
 * собственный Exporter с blade view.
 */
final class PdfExporter implements Exporter
{
    public function __construct(private readonly PdfRenderer $renderer) {}

    public function format(): string
    {
        return 'pdf';
    }

    public function mimeType(): string
    {
        return 'application/pdf';
    }

    public function extension(): string
    {
        return 'pdf';
    }

    public function export(iterable $rows, array $columns, string $filenameWithoutExt): StreamedResponse
    {
        $filename = $filenameWithoutExt.'.'.$this->extension();
        $renderer = $this->renderer;

        return new StreamedResponse(
            function () use ($rows, $columns, $renderer): void {
                $html = self::buildHtml($rows, $columns);
                echo $renderer->render($html);
            },
            200,
            [
                'Content-Type' => $this->mimeType(),
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $columns
     */
    private static function buildHtml(iterable $rows, array $columns): string
    {
        $head = '';
        foreach ($columns as $label) {
            $head .= '<th style="border:1px solid #ccc;padding:4px 8px;background:#f6f6f6;text-align:left">'
                .htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
                .'</th>';
        }

        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>';
            foreach (array_keys($columns) as $col) {
                $value = data_get($row, $col);
                $rendered = is_scalar($value) || $value === null
                    ? (string) $value
                    : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
                $body .= '<td style="border:1px solid #ccc;padding:4px 8px">'
                    .htmlspecialchars($rendered, ENT_QUOTES, 'UTF-8')
                    .'</td>';
            }
            $body .= '</tr>';
        }

        return '<html><head><meta charset="UTF-8"></head><body>'
            .'<table style="border-collapse:collapse;width:100%;font-family:sans-serif;font-size:12px">'
            .'<thead><tr>'.$head.'</tr></thead>'
            .'<tbody>'.$body.'</tbody>'
            .'</table></body></html>';
    }
}
