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
     * The attributes whose value is an address: a `javascript:` one is
     * removed whatever the whitelist says.
     *
     * @var list<string>
     */
    private const URL_ATTRIBUTES = ['href', 'src', 'srcset', 'action', 'formaction', 'poster', 'background', 'cite', 'data', 'longdesc', 'usemap', 'xlink:href', 'ping'];

    /**
     * @param  array<string, list<string>>|null  $allowed  null means the default.
     * @param  bool  $constrain  Narrow a code block's class and a table cell's
     *                           style to the default editor's own (see constrain());
     *                           off for the markup of Quill and TinyMCE.
     */
    public function __construct(?array $allowed = null, private readonly bool $constrain = true)
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
            // A code block's language (`language-mermaid`): the editor draws the
            // block by it. Only that class is kept (see constrain()).
            'code' => ['class'], 'pre' => ['class'],
            'blockquote' => [],
            'h1' => ['id'], 'h2' => ['id'], 'h3' => ['id'],
            'h4' => ['id'], 'h5' => ['id'], 'h6' => ['id'],
            'ul' => [], 'ol' => ['start'], 'li' => [],
            'hr' => [],
            'a' => $href,
            'img' => $img,
            'table' => ['class'],
            'thead' => [], 'tbody' => [], 'tfoot' => [],
            // A column's alignment, `text-align` only (see constrain()).
            'tr' => [], 'th' => ['colspan', 'rowspan', 'style'], 'td' => ['colspan', 'rowspan', 'style'],
            'span' => ['class', 'style'],
            'mark' => [], 'sub' => [], 'sup' => [],
        ];
    }

    /**
     * Whether the tag is one the sanitizer removes along with its content
     * when it is not whitelisted — the tags a field never whitelists.
     */
    public static function isDroppedTag(string $tag): bool
    {
        return in_array(strtolower($tag), self::DROP_WITH_CONTENT, true);
    }

    /**
     * Whether the attribute may never be kept: an event handler (`on*`),
     * or `srcdoc`, a whole document in an attribute.
     */
    public static function isForbiddenAttribute(string $attribute): bool
    {
        $attribute = strtolower($attribute);

        return str_starts_with($attribute, 'on') || $attribute === 'srcdoc';
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

        // DOMDocument writes a no-break space as the bare character; editors
        // (and the browser's innerHTML) write &nbsp;. Keeping the entity
        // leaves an editor's HTML byte for byte as it was.
        return trim(str_replace("\u{00A0}", '&nbsp;', $result));
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
                    // A handler goes even when a whitelist names it.
                    if (! in_array($lower, $allowedAttrs, true) || self::isForbiddenAttribute($lower)) {
                        $child->removeAttribute($name);

                        continue;
                    }
                    if (in_array($lower, self::URL_ATTRIBUTES, true)) {
                        $value = (string) $child->getAttribute($name);
                        $dangerous = $lower === 'srcset'
                            ? self::hasDangerousCandidate($value)
                            : self::isDangerousUrl($value);
                        if ($dangerous) {
                            $child->removeAttribute($name);
                        }

                        continue;
                    }
                    if (! $this->constrain) {
                        continue;
                    }
                    $constrained = self::constrain($tag, $lower, (string) $child->getAttribute($name));
                    if ($constrained === null) {
                        $child->removeAttribute($name);
                    } elseif ($constrained !== (string) $child->getAttribute($name)) {
                        $child->setAttribute($name, $constrained);
                    }
                }

                // Recurse into the children.
                $this->cleanNode($child);
            } elseif ($child instanceof \DOMComment) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    /**
     * The value an attribute may keep on a tag, or null to drop it: a code
     * block's class keeps only its `language-…` token, a table cell's style
     * only its `text-align`. Other attributes pass unchanged.
     */
    private static function constrain(string $tag, string $attribute, string $value): ?string
    {
        if ($tag === 'code' && $attribute === 'class') {
            $languages = array_filter(
                preg_split('/\s+/', trim($value)) ?: [],
                static fn (string $token): bool => (bool) preg_match('/^language-[\w+#.-]+$/', $token),
            );

            return $languages === [] ? null : implode(' ', $languages);
        }
        if (($tag === 'td' || $tag === 'th') && $attribute === 'style') {
            return preg_match('/(?:^|;)\s*text-align\s*:\s*(left|right|center)\s*(?:;|$)/i', $value, $m)
                ? 'text-align: '.strtolower($m[1])
                : null;
        }

        return $value;
    }

    /** Whether any address of a srcset list is a dangerous one. */
    private static function hasDangerousCandidate(string $srcset): bool
    {
        foreach (explode(',', $srcset) as $candidate) {
            $url = preg_split('/\s+/', trim($candidate))[0] ?? '';
            if (self::isDangerousUrl($url)) {
                return true;
            }
        }

        return false;
    }

    private static function isDangerousUrl(string $url): bool
    {
        // Browsers skip whitespace and control characters inside a scheme:
        // `java\tscript:` runs as `javascript:`.
        $lower = strtolower((string) preg_replace('/[\x00-\x20\x7F]+/', '', $url));

        return str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, 'data:text/html')
            || str_starts_with($lower, 'vbscript:');
    }
}
