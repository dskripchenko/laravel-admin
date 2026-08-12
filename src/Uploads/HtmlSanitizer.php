<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Uploads;

/**
 * A simple HTML sanitizer over a whitelist of tags and attributes.
 *
 * It has no third-party dependencies — no HTMLPurifier, no
 * spatie/html-element — and works through DOMDocument and a recursive walk.
 *
 * The point is to keep XSS out of saved WYSIWYG content: it strips `<script>`,
 * `<style>`, the on* handlers, javascript: hrefs and any tag not on the list.
 *
 * The whitelist names the tags and, for each, the attributes allowed. The
 * default matches Tiptap's own default extensions.
 */
final class HtmlSanitizer
{
    /** @var array<string, list<string>> tag => allowed attrs */
    private array $allowed;

    /**
     * The tags removed along with everything inside them — the XSS vectors.
     *
     * @var list<string>
     */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option', 'meta', 'link'];

    /**
     * @param  array<string, list<string>>|null  $allowed  null means the default.
     */
    public function __construct(?array $allowed = null)
    {
        $this->allowed = $allowed ?? self::defaultWhitelist();
    }

    /**
     * The default whitelist: Tiptap's StarterKit plus image and table.
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
        // libxml warns about unstructured HTML; we silence it.
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
        // The children first, over a snapshot: removeChild changes the live list.
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);

                if (! isset($this->allowed[$tag])) {
                    if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                        // <script>, <style>, <iframe> and the rest are XSS
                        // vectors, removed whole along with their text.
                        $child->parentNode?->removeChild($child);

                        continue;
                    }

                    // Any other disallowed tag is replaced by its text: the
                    // visual wrapper is lost, the words are kept.
                    $textOnly = $child->ownerDocument?->createTextNode($child->textContent) ?? null;
                    if ($textOnly !== null) {
                        $child->parentNode?->replaceChild($textOnly, $child);
                    } else {
                        $child->parentNode?->removeChild($child);
                    }

                    continue;
                }

                // The attributes are filtered against the list.
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

                // Recurse into the children.
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
