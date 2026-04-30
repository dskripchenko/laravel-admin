<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export\Pdf;

use Mpdf\Mpdf;
use RuntimeException;

/**
 * mPDF-адаптер. Поддерживает UTF-8 и расширенный CSS.
 *
 * Default options берутся из config('admin.exports.pdf.options.mpdf').
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
