<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export\Pdf;

use Dompdf\Dompdf;
use RuntimeException;

/**
 * Dompdf-адаптер — простой fallback. Меньше CSS-фич, но без зависимости
 * от mPDF (полезно для embedded окружений).
 */
final class DompdfRenderer implements PdfRenderer
{
    public function render(string $html, array $options = []): string
    {
        if (! class_exists(Dompdf::class)) {
            throw new RuntimeException(
                'Dompdf rendering requires dompdf/dompdf — composer require dompdf/dompdf',
            );
        }

        $configDefaults = (array) config('admin.exports.pdf.options.dompdf', [
            'paper' => 'a4',
            'orientation' => 'portrait',
        ]);
        $merged = array_merge($configDefaults, $options);

        $dompdf = new Dompdf;
        $dompdf->loadHtml($html);
        if (isset($merged['paper'], $merged['orientation'])) {
            $dompdf->setPaper((string) $merged['paper'], (string) $merged['orientation']);
        }
        $dompdf->render();

        return $dompdf->output() ?: '';
    }
}
