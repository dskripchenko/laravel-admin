<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Uploads;

/**
 * Простой HTML-санитайзер на whitelist'е тегов и атрибутов.
 *
 * Без сторонних зависимостей (никаких HTMLPurifier / spatie/html-element).
 * Использует DOMDocument + рекурсивный walk.
 *
 * Цель — защитить от XSS при сохранении Wysiwyg-content'а: вырезает
 * `<script>`, `<style>`, on*-handlers, javascript:-href'ы, неразрешённые теги.
 *
 * Whitelist — список тегов и для каждого — допустимые атрибуты. Дефолт
 * соответствует Tiptap default-extensions из P14.1.
 */
final class HtmlSanitizer
{
    /** @var array<string, list<string>> tag => allowed attrs */
    private array $allowed;

    /**
     * Теги, которые удаляются вместе со всем содержимым (XSS-vector'ы).
     *
     * @var list<string>
     */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option', 'meta', 'link'];

    /**
     * @param  array<string, list<string>>|null  $allowed  null = дефолт.
     */
    public function __construct(?array $allowed = null)
    {
        $this->allowed = $allowed ?? self::defaultWhitelist();
    }

    /**
     * Дефолтный whitelist: совпадает с Tiptap StarterKit + image + table.
     *
     * @return array<string, list<string>>
     */
    public static function defaultWhitelist(): array
    {
        $href = ['href', 'target', 'rel', 'title'];
        $img = ['src', 'alt', 'title', 'width', 'height'];

        return [
            'p' => ['class'],
            'br' => [],
            'strong' => [], 'b' => [],
            'em' => [], 'i' => [],
            'u' => [], 's' => [], 'strike' => [],
            'code' => [], 'pre' => ['class'],
            'blockquote' => [],
            'h1' => ['id'], 'h2' => ['id'], 'h3' => ['id'],
            'h4' => ['id'], 'h5' => ['id'], 'h6' => ['id'],
            'ul' => [], 'ol' => ['start'], 'li' => [],
            'hr' => [],
            'a' => $href,
            'img' => $img,
            'table' => ['class'],
            'thead' => [], 'tbody' => [], 'tfoot' => [],
            'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
            'span' => ['class', 'style'],
            'mark' => [], 'sub' => [], 'sup' => [],
        ];
    }

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new \DOMDocument;
        // libxml выбрасывает warnings на неструктурный HTML — глушим.
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__sanitize_root__">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__sanitize_root__');
        if ($root === null) {
            return '';
        }

        $this->cleanNode($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return trim($result);
    }

    private function cleanNode(\DOMNode $node): void
    {
        // Сначала пройти по детям (snapshot — список меняется при removeChild).
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);

                if (! isset($this->allowed[$tag])) {
                    if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                        // <script>, <style>, <iframe>, ... — XSS-vector'ы,
                        // удаляем целиком вместе с textContent.
                        $child->parentNode?->removeChild($child);

                        continue;
                    }

                    // Прочие неразрешённые теги — заменяем на text content
                    // (теряем visual-обёртку, сохраняем текст).
                    $textOnly = $child->ownerDocument?->createTextNode($child->textContent) ?? null;
                    if ($textOnly !== null) {
                        $child->parentNode?->replaceChild($textOnly, $child);
                    } else {
                        $child->parentNode?->removeChild($child);
                    }

                    continue;
                }

                // Допустимые атрибуты — фильтруем.
                $allowedAttrs = $this->allowed[$tag];
                $existingAttrs = [];
                foreach ($child->attributes as $attr) {
                    $existingAttrs[] = $attr->name;
                }
                foreach ($existingAttrs as $name) {
                    $lower = strtolower($name);
                    if (! in_array($lower, $allowedAttrs, true)) {
                        $child->removeAttribute($name);

                        continue;
                    }
                    if ($lower === 'href' || $lower === 'src') {
                        $value = (string) $child->getAttribute($name);
                        if (self::isDangerousUrl($value)) {
                            $child->removeAttribute($name);
                        }
                    }
                }

                // Рекурсивно — в детей.
                $this->cleanNode($child);
            } elseif ($child instanceof \DOMComment) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    private static function isDangerousUrl(string $url): bool
    {
        $trimmed = ltrim($url);
        $lower = strtolower($trimmed);

        return str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, 'data:text/html')
            || str_starts_with($lower, 'vbscript:');
    }
}
