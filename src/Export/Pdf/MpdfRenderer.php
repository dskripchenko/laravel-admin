<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export\Pdf;

use Mpdf\Mpdf;
use RuntimeException;

/**
 * The mPDF adapter. It supports UTF-8 and a wider range of CSS.
 *
 * The default options come from config('admin.exports.pdf.options.mpdf').
 */
final class MpdfRenderer implements PdfRenderer
{
    public function render(string $html, array $options = []): string
    {
        if (! class_exists(Mpdf::class)) {
            throw new RuntimeException(
                'mPDF rendering requires mpdf/mpdf — composer require mpdf/mpdf',
            );
        }

        $configDefaults = (array) config('admin.exports.pdf.options.mpdf', [
            'mode' => 'utf-8',
            'format' => 'A4',
        ]);
        $merged = array_merge($configDefaults, $options);

        $mpdf = new Mpdf($merged);
        $mpdf->WriteHTML($html);

        $output = $mpdf->Output('', 'S');

        return is_string($output) ? $output : '';
    }
}
