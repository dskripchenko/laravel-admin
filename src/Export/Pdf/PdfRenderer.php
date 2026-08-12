<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export\Pdf;

/**
 * The contract of a PDF renderer.
 *
 * The implementations:
 *   - MpdfRenderer, the default, with UTF-8 and CSS support out of the box.
 *   - DompdfRenderer, the fallback, for simple HTML.
 *
 * Which one is used follows config('admin.exports.pdf.driver'), or a runtime
 * binding in the container.
 */
interface PdfRenderer
{
    /**
     * Renders an HTML string into a PDF binary.
     *
     * @param  array<string, mixed>  $options  Driver-specific: format, margin and so on.
     */
    public function render(string $html, array $options = []): string;
}
