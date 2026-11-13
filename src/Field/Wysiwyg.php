<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Uploads\HtmlSanitizer;

/**
 * A WYSIWYG editor, built on Tiptap on the SPA's side.
 *
 * The backend stores an HTML string; the rendering is the
 * `@tiptap/vue-3` + `@tiptap/starter-kit` wrapper in the SPA. The extensions
 * are declared here, and the SPA loads only the permitted ones.
 *
 * The presets:
 *   - 'minimal' — paragraph, bold, italic, link.
 *   - 'default' — minimal plus heading, bullet and ordered lists, code,
 *     blockquote, horizontal rule, image and link.
 *   - 'full' — default plus table, textAlign, textColor, highlight, codeBlock,
 *     youtube and mention.
 *
 * Engines: `->driver('quill' | 'tinymce' | 'default', $options)` switches
 * the field to Quill 2 or a self-hosted TinyMCE — in the prebuilt application
 * they are lazy chunks; a host with its own build registers them with
 * registerWysiwygDriver(). The options go to the SPA as `driverConfig`:
 *   - quill: toolbar (a Quill toolbar, or Orchid's group names text, quote,
 *     color, header, list, format, media), modules, formats, theme,
 *     output 'html' | 'delta';
 *   - tinymce: script (the URL of a self-hosted tinymce.min.js; by default
 *     config admin.wysiwyg.tinymce.script), height, plugins, toolbar,
 *     menubar, language, init (any other TinyMCE option).
 * Quill and TinyMCE keep the HTML in their own dialect, so the sanitizer's
 * whitelist widens to it for such a field (see sanitizerWhitelist()).
 */
final class Wysiwyg extends Field
{
    /** @var list<string> built-in presets */
    private const PRESETS = ['minimal', 'default', 'full'];

    /** @var list<string> the engines the SPA knows */
    public const DRIVERS = ['default', 'quill', 'tinymce'];

    /** @var list<string> Quill's output formats */
    private const QUILL_OUTPUTS = ['html', 'delta'];

    public function fieldType(): string
    {
        return 'wysiwyg';
    }

    /**
     * The default extensions of a preset.
     *
     * @return list<string>
     */
    public static function defaultExtensions(string $preset = 'default'): array
    {
        return match ($preset) {
            'minimal' => ['paragraph', 'bold', 'italic', 'link'],
            'full' => [
                'paragraph', 'heading', 'bold', 'italic', 'underline', 'strike',
                'link', 'bulletList', 'orderedList', 'listItem', 'code', 'codeBlock',
                'blockquote', 'horizontalRule', 'image', 'table', 'tableRow',
                'tableCell', 'tableHeader', 'textAlign', 'textColor', 'highlight',
                'youtube', 'mention',
            ],
            default => [
                'paragraph', 'heading', 'bold', 'italic', 'link',
                'bulletList', 'orderedList', 'listItem', 'code', 'codeBlock',
                'blockquote', 'horizontalRule', 'image',
            ],
        };
    }

    public function preset(string $preset): static
    {
        if (! in_array($preset, self::PRESETS, true)) {
            throw new \InvalidArgumentException(
                'Wysiwyg preset must be one of: '.implode(', ', self::PRESETS),
            );
        }
        $this->attributes['extensions'] = self::defaultExtensions($preset);

        return $this;
    }

    /**
     * An explicit list of extensions, overriding the preset.
     *
     * @param  list<string>  $extensions
     */
    public function extensions(array $extensions): static
    {
        $this->attributes['extensions'] = $extensions;

        return $this;
    }

    public function withExtension(string $extension): static
    {
        $existing = (array) ($this->attributes['extensions'] ?? self::defaultExtensions());
        if (! in_array($extension, $existing, true)) {
            $existing[] = $extension;
        }
        $this->attributes['extensions'] = array_values($existing);

        return $this;
    }

    public function withoutExtension(string $extension): static
    {
        $existing = (array) ($this->attributes['extensions'] ?? self::defaultExtensions());
        $this->attributes['extensions'] = array_values(
            array_filter($existing, static fn ($e): bool => $e !== $extension),
        );

        return $this;
    }

    public function height(int|string $height): static
    {
        $this->attributes['height'] = $height;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->attributes['placeholder'] = $placeholder;

        return $this;
    }

    /**
     * The toolbar's configuration: `floating`, `sticky`, or an array of groups.
     *
     * @param  string|array<int, list<string>>  $toolbar
     */
    public function toolbar(string|array $toolbar): static
    {
        $this->attributes['toolbar'] = $toolbar;

        return $this;
    }

    /**
     * Turns on image uploads, through the uploads controller.
     *
     * @param  string  $endpoint  A custom endpoint; '/api/admin/uploads/image' by default.
     */
    public function uploadImages(bool $enable = true, string $endpoint = '/api/admin/uploads/image'): static
    {
        $this->attributes['uploadImages'] = $enable;
        if ($enable) {
            $this->attributes['uploadEndpoint'] = $endpoint;
        }

        return $this;
    }

    /**
     * Turns the server-side HTML sanitization through HtmlSanitizer on or off.
     *
     * It is on by default, as protection against XSS; switch it off only for
     * content you trust.
     */
    public function sanitize(bool $sanitize = true): static
    {
        $this->attributes['sanitize'] = $sanitize;

        return $this;
    }

    public function shouldSanitize(): bool
    {
        // Quill's Delta output is JSON, not HTML: nothing to sanitize.
        if ($this->getDriver() === 'quill'
            && (($this->attributes['driverConfig']['output'] ?? 'html') === 'delta')) {
            return false;
        }

        return (bool) ($this->attributes['sanitize'] ?? true);
    }

    /**
     * The editor engine: 'default' (@dskripchenko/wysiwyg), 'quill' or
     * 'tinymce', with its options (see the class docblock). The options are
     * merged over those set before.
     *
     * @param  array<string, mixed>  $options
     */
    public function driver(string $driver, array $options = []): static
    {
        if (! in_array($driver, self::DRIVERS, true)) {
            throw new \InvalidArgumentException(
                'Wysiwyg driver must be one of: '.implode(', ', self::DRIVERS),
            );
        }
        if ($driver === 'quill' && isset($options['output'])) {
            self::assertQuillOutput($options['output']);
        }
        if ($driver === 'default') {
            unset($this->attributes['driver'], $this->attributes['driverConfig']);

            return $this;
        }
        $previous = ($this->attributes['driver'] ?? null) === $driver
            ? (array) ($this->attributes['driverConfig'] ?? [])
            : [];
        $this->attributes['driver'] = $driver;
        $this->attributes['driverConfig'] = array_replace($previous, $options);

        return $this;
    }

    public function getDriver(): string
    {
        $driver = $this->attributes['driver'] ?? 'default';

        return is_string($driver) ? $driver : 'default';
    }

    /**
     * Quill's output: 'html' (the default, the editor root's innerHTML, as
     * Orchid stores it) or 'delta' (the Delta as JSON). Switches to Quill.
     */
    public function output(string $output): static
    {
        self::assertQuillOutput($output);

        return $this->driver('quill', ['output' => $output]);
    }

    /**
     * Quill's modules (toolbar, clipboard, keyboard…). Switches to Quill.
     *
     * @param  array<string, mixed>  $modules
     */
    public function modules(array $modules): static
    {
        return $this->driver('quill', ['modules' => $modules]);
    }

    /**
     * TinyMCE's plugins, as a list or a space-separated string. Switches to TinyMCE.
     *
     * @param  string|list<string>  $plugins
     */
    public function plugins(string|array $plugins): static
    {
        return $this->driver('tinymce', ['plugins' => is_array($plugins) ? implode(' ', $plugins) : $plugins]);
    }

    /**
     * The tag => attributes whitelist the sanitizer applies to this field:
     * the default one, widened to the markup of the field's engine.
     *
     * @return array<string, list<string>>
     */
    public function sanitizerWhitelist(): array
    {
        $whitelist = HtmlSanitizer::defaultWhitelist();
        $extra = match ($this->getDriver()) {
            // Quill 2: lists are <ol><li data-list>, with a <span class="ql-ui">
            // marker; alignment, indent and direction are ql-* classes; code
            // blocks are <div class="ql-code-block">.
            'quill' => [
                'p' => ['class'], 'li' => ['class', 'data-list'],
                'span' => ['class', 'style', 'contenteditable'],
                'h1' => ['class'], 'h2' => ['class'], 'h3' => ['class'],
                'h4' => ['class'], 'h5' => ['class'], 'h6' => ['class'],
                'blockquote' => ['class'], 'pre' => ['class', 'spellcheck'],
                'div' => ['class', 'spellcheck', 'data-language'],
                'a' => ['href', 'target', 'rel'],
                'img' => ['class', 'style'],
            ],
            // TinyMCE: alignment, indents, widths and colours are inline styles.
            'tinymce' => [
                'p' => ['class', 'style'], 'div' => ['class', 'style'],
                'h1' => ['style'], 'h2' => ['style'], 'h3' => ['style'],
                'h4' => ['style'], 'h5' => ['style'], 'h6' => ['style'],
                'ul' => ['style'], 'ol' => ['style', 'type'], 'li' => ['style'],
                'blockquote' => ['style'], 'pre' => ['class', 'style'],
                'img' => ['class', 'style'],
                'table' => ['style', 'border', 'cellpadding', 'cellspacing', 'width'],
                'tr' => ['style'], 'th' => ['class', 'scope'], 'td' => ['class'],
                'caption' => [], 'colgroup' => [], 'col' => ['style', 'width', 'span'],
                'figure' => ['class'], 'figcaption' => [],
                'em' => [], 'strong' => [], 'span' => ['class', 'style'],
                'hr' => [], 'sub' => [], 'sup' => [], 'code' => [],
            ],
            default => [],
        };
        foreach ($extra as $tag => $attributes) {
            $whitelist[$tag] = array_values(array_unique(array_merge($whitelist[$tag] ?? [], $attributes)));
        }

        return $whitelist;
    }

    /**
     * Whether the sanitizer narrows style and class to the default editor's
     * own (a cell's text-align, a code block's language). Off for Quill and
     * TinyMCE, whose markup carries more.
     */
    public function sanitizerConstrains(): bool
    {
        return $this->getDriver() === 'default';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        if ($this->getDriver() === 'tinymce') {
            $config = (array) ($array['attributes']['driverConfig'] ?? []);
            $script = config('admin.wysiwyg.tinymce.script');
            if (! isset($config['script']) && is_string($script) && $script !== '') {
                $config['script'] = $script;
            }
            // ->height() of the field, unless the engine's options name one.
            if (! isset($config['height']) && isset($this->attributes['height'])) {
                $config['height'] = $this->attributes['height'];
            }
            $array['attributes']['driverConfig'] = $config;
        }

        return $array;
    }

    private static function assertQuillOutput(mixed $output): void
    {
        if (! in_array($output, self::QUILL_OUTPUTS, true)) {
            throw new \InvalidArgumentException(
                'Quill output must be one of: '.implode(', ', self::QUILL_OUTPUTS),
            );
        }
    }

    /**
     * Returns the extensions actually in effect, falling back to the preset's.
     *
     * @return list<string>
     */
    public function getExtensions(): array
    {
        $configured = $this->attributes['extensions'] ?? null;
        if (is_array($configured) && $configured !== []) {
            /** @var list<string> $list */
            $list = array_values(array_filter($configured, 'is_string'));

            return $list;
        }

        return self::defaultExtensions();
    }
}
