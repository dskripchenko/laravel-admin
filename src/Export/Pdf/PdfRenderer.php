<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Export\Pdf;

/**
 * Контракт PDF-рендерера.
 *
 * Реализации:
 *   - MpdfRenderer (default) — UTF-8/CSS support из коробки.
 *   - DompdfRenderer (fallback) — простая HTML-разметка.
 *
 * Backend выбирается через config('admin.exports.pdf.driver') либо
 * через runtime-bind в DI.
 */
interface PdfRenderer
{
    /**
     * Отрендерить HTML-строку в PDF binary.
     *
     * @param  array<string, mixed>  $options  Driver-specific (format, margin, ...).
     */
    public function render(string $html, array $options = []): string;
}
