<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Closure;

/**
 * A block of markdown, rendered by the SPA's built-in safe renderer.
 *
 * The source is escaped before any markup is produced, so raw HTML in it is
 * shown as text and never executed; links and images are limited to http(s),
 * mailto, relative and anchor targets.
 *
 * Supported: headings (with anchor ids), paragraphs, emphasis, inline code,
 * fenced code blocks (highlighted by language), tables, block quotes and
 * callouts (`> **Note**`, `> [!WARNING]`), lists, rules, links and images.
 *
 *     Layout::markdown(file_get_contents($path))
 *         ->toc()
 *         ->linkBase('/admin/screens/')   // screen slugs are flat
 *
 * The text is sent as is: picking the right language version is the caller's
 * business.
 */
final class Markdown extends Layout
{
    /** @var string|Closure(): string */
    private string|Closure $source = '';

    /**
     * @param  string|callable(): string  $markdown  The source, or a callable resolved at serialization.
     */
    public static function make(string|callable $markdown): self
    {
        $instance = new self;
        $instance->source = is_string($markdown) ? $markdown : Closure::fromCallable($markdown);

        return $instance;
    }

    public function type(): string
    {
        return 'markdown';
    }

    /**
     * Shows a table of contents built from the headings, levels 2 to $depth.
     */
    public function toc(bool $enabled = true, int $depth = 3): self
    {
        $this->props['toc'] = $enabled;
        $this->props['tocDepth'] = max(2, min(6, $depth));

        return $this;
    }

    /**
     * The caption above the table of contents; "On this page" by default.
     */
    public function tocLabel(string $label): self
    {
        $this->props['tocLabel'] = $label;

        return $this;
    }

    /**
     * The base relative links are resolved against, the way a browser resolves
     * them against `<base href>`: with '/admin/screens/docs/' a link to
     * `concepts/menu.md` opens '/admin/screens/docs/concepts/menu'. The `.md`
     * extension is dropped unless $stripExtension is false. Links that start
     * with `/`, `#` or a scheme are left alone.
     */
    public function linkBase(string $base, bool $stripExtension = true): self
    {
        $this->props['linkBase'] = $base;
        $this->props['stripMdExtension'] = $stripExtension;

        return $this;
    }

    /**
     * The base relative image paths are resolved against: with '/docs-assets/'
     * the image `img/menu.png` loads from '/docs-assets/img/menu.png'.
     */
    public function imageBase(string $base): self
    {
        $this->props['imageBase'] = $base;

        return $this;
    }

    /**
     * Wraps the text in a card instead of drawing it straight on the page.
     */
    public function card(bool $card = true): self
    {
        $this->props['card'] = $card;

        return $this;
    }

    public function source(): string
    {
        $source = $this->source;

        return $source instanceof Closure ? (string) $source() : $source;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        // The text travels once, at the top level: the `props` copy exists for
        // older consumers of the small props, and a long document would
        // double the payload for nothing.
        $array['markdown'] = $this->source();

        return $array;
    }
}
